<?php

namespace Tests\Feature\NarrativeIntelligence;

use App\Models\Category;
use App\Models\Keyword;
use App\Models\NarrativeBriefing;
use App\Models\Post;
use App\Models\Region;
use App\Models\SocialAccount;
use App\Services\NarrativeBriefingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NarrativeBriefingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_heuristic_briefing_when_gemini_key_is_empty(): void
    {
        config(['services.gemini.key' => null]);
        Http::preventStrayRequests();

        $region = Region::factory()->create(['name' => 'Kota Semarang']);
        $cat    = Category::factory()->create(['name' => 'Mahasiswa']);
        $acc    = SocialAccount::factory()->youtube()->create(['region_id' => $region->id, 'category_id' => $cat->id]);
        $kw     = Keyword::factory()->create(['word' => 'banjir', 'category' => 'negatif', 'is_active' => true]);

        $post = Post::factory()->for($acc, 'socialAccount')->create([
            'content'     => 'Keluhan banjir di jalan protokol Kota Semarang',
            'sentiment'   => 'negatif',
            'likes_count' => 150,
            'posted_at'   => now()->subHours(3),
        ]);
        $post->keywords()->sync([$kw->id => ['occurrence_count' => 2]]);

        $briefing = app(NarrativeBriefingService::class)->generate(24);

        $this->assertSame('heuristic', $briefing->source);
        $this->assertSame('ok', $briefing->status);
        $this->assertNotEmpty($briefing->executive_summary);
        $this->assertNotEmpty($briefing->issue_clusters);
        $this->assertContains('Kota Semarang', $briefing->issue_clusters[0]['regions']);
        $this->assertSame(1, $briefing->stats_snapshot['total_posts']);
    }

    public function test_generates_gemini_briefing_and_enriches_cluster_metrics_from_db(): void
    {
        config([
            'services.gemini.key'      => 'SECRET_GEMINI_KEY_123',
            'services.gemini.model'    => 'gemini-2.5-flash',
            'services.gemini.endpoint' => 'https://generativelanguage.googleapis.com/v1beta',
        ]);

        $region = Region::factory()->create(['name' => 'Kabupaten Banyumas']);
        $acc    = SocialAccount::factory()->instagram()->create(['region_id' => $region->id]);
        $post   = Post::factory()->for($acc, 'socialAccount')->create([
            'content'        => 'Aksi tuntutan kenaikan UMK Banyumas',
            'sentiment'      => 'sensitif',
            'likes_count'    => 200,
            'comments_count' => 50,
            'posted_at'      => now()->subHours(2),
        ]);

        $aiPayload = [
            'executive_summary' => 'Sorotan utama hari ini adalah aksi UMK di Kabupaten Banyumas.',
            'issue_clusters'    => [
                [
                    'title'     => 'Tuntutan UMK Banyumas',
                    'sentiment' => 'sensitif',
                    'urgency'   => 'tinggi',
                    'summary'   => 'Serikat pekerja menyuarakan aspirasi UMK.',
                    'post_ids'  => [$post->id],
                ],
            ],
            'recommendations'   => ['Koordinasi dengan Disnaker Kabupaten Banyumas.'],
        ];

        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => json_encode($aiPayload)]]]],
                ],
            ], 200),
        ]);

        $briefing = app(NarrativeBriefingService::class)->generate(24);

        $this->assertSame('gemini', $briefing->source);
        $this->assertSame('ok', $briefing->status);
        $this->assertSame('Tuntutan UMK Banyumas', $briefing->issue_clusters[0]['title']);
        $this->assertSame(250, $briefing->issue_clusters[0]['total_engagement']);
        $this->assertContains('Kabupaten Banyumas', $briefing->issue_clusters[0]['regions']);
    }

    public function test_gemini_failure_falls_back_to_heuristic_and_masks_api_key(): void
    {
        config(['services.gemini.key' => 'MY_SUPER_SECRET_KEY']);

        $acc = SocialAccount::factory()->youtube()->create();
        Post::factory()->for($acc, 'socialAccount')->create(['posted_at' => now()->subHours(1)]);

        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => ['message' => 'Invalid key MY_SUPER_SECRET_KEY in request https://generativelanguage.googleapis.com?key=MY_SUPER_SECRET_KEY'],
            ], 400),
        ]);

        $briefing = app(NarrativeBriefingService::class)->generate(24);

        $this->assertSame('heuristic', $briefing->source);
        $this->assertSame('fallback', $briefing->status);
        $this->assertNotNull($briefing->error_message);
        $this->assertStringNotContainsString('MY_SUPER_SECRET_KEY', $briefing->error_message);
        $this->assertStringContainsString('***', $briefing->error_message);
    }

    public function test_adaptive_window_expands_when_last_24h_is_empty(): void
    {
        config(['services.gemini.key' => null]);
        $acc = SocialAccount::factory()->youtube()->create();
        Post::factory()->for($acc, 'socialAccount')->create([
            'content'   => 'Postingan tiga hari lalu',
            'posted_at' => now()->subDays(3),
        ]);

        $briefing = app(NarrativeBriefingService::class)->generate(24);

        $this->assertSame('7 Hari Terakhir', $briefing->window_label);
        $this->assertSame(1, $briefing->stats_snapshot['total_posts']);
    }

    public function test_artisan_command_generates_briefing_and_prunes_old_records(): void
    {
        config(['services.gemini.key' => null]);
        $old = NarrativeBriefing::create([
            'period_start'      => now()->subDays(100),
            'period_end'        => now()->subDays(99),
            'window_label'      => '24 Jam Terakhir',
            'source'            => 'heuristic',
            'status'            => 'ok',
            'executive_summary' => 'Lama',
            'issue_clusters'    => [],
            'recommendations'   => [],
            'stats_snapshot'    => ['total_posts' => 0],
        ]);
        NarrativeBriefing::whereKey($old->id)->update(['created_at' => now()->subDays(95)]);

        $this->artisan('intel:briefing --heuristic')->assertExitCode(0);

        $this->assertDatabaseMissing('narrative_briefings', ['id' => $old->id]);
        $this->assertSame(1, NarrativeBriefing::count());
    }
}
