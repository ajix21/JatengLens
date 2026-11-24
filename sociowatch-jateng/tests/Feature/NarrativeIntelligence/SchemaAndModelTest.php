<?php

namespace Tests\Feature\NarrativeIntelligence;

use App\Models\NarrativeBriefing;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchemaAndModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_posts_table_has_sentiment_columns_with_defaults(): void
    {
        $account = SocialAccount::factory()->youtube()->create();
        $post = Post::factory()->for($account, 'socialAccount')->create();
        $fresh = $post->fresh();

        $this->assertEquals('netral', $fresh->sentiment);
        $this->assertFalse($fresh->sentiment_manual);

        $badge = Post::sentimentBadge('sensitif');
        $this->assertEquals('Sensitif', $badge['label']);
        $this->assertEquals('#ef4444', $badge['hex']);
    }

    public function test_narrative_briefing_casts_json_attributes_and_links_generator(): void
    {
        $user = User::factory()->create();

        $briefing = NarrativeBriefing::create([
            'period_start'    => now()->subDay(),
            'period_end'      => now(),
            'window_label'    => '24 Jam Terakhir',
            'source'          => 'gemini',
            'status'          => 'ok',
            'executive_summary' => 'Test summary',
            'issue_clusters'  => [['topic' => 'Infrastruktur']],
            'recommendations' => ['Tingkatkan komunikasi publik'],
            'stats_snapshot'  => ['total_posts' => 10],
            'generated_by'    => $user->id,
        ]);
        $fresh = $briefing->fresh();

        $this->assertIsArray($fresh->issue_clusters);
        $this->assertIsArray($fresh->recommendations);
        $this->assertIsArray($fresh->stats_snapshot);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $fresh->period_start);
        $this->assertEquals($user->id, $fresh->generator->id);
    }
}
