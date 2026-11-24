<?php

namespace Tests\Feature\NarrativeIntelligence;

use App\Models\NarrativeBriefing;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BriefingAndSentimentUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_executive_briefing_and_auto_generates_when_posts_exist(): void
    {
        config(['services.gemini.key' => null]);
        $user = User::factory()->create(['role' => 'superadmin', 'is_active' => true]);
        $acc  = SocialAccount::factory()->youtube()->create();
        Post::factory()->for($acc, 'socialAccount')->create([
            'content'   => 'Aspirasi perbaikan jalan provinsi Jawa Tengah',
            'sentiment' => 'negatif',
            'posted_at' => now()->subHours(2),
        ]);

        $res = $this->actingAs($user)->get(route('dashboard'));

        $res->assertOk();
        $res->assertSee('Ringkasan Strategis');
        $res->assertSee('Perbarui Ringkasan');
        $this->assertSame(1, NarrativeBriefing::count());
    }

    public function test_generate_briefing_endpoint_requires_can_edit_gate(): void
    {
        config(['services.gemini.key' => null]);
        $viewer = User::factory()->create(['role' => 'viewer', 'is_active' => true]);
        $admin  = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($viewer)->post(route('briefings.generate'))->assertForbidden();

        $this->actingAs($admin)->post(route('briefings.generate'))
            ->assertRedirect(route('dashboard'));
        $this->assertSame(1, NarrativeBriefing::count());
    }

    public function test_posts_index_filters_by_sentiment_and_displays_badge(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $acc  = SocialAccount::factory()->instagram()->create();

        Post::factory()->for($acc, 'socialAccount')->create([
            'content'   => 'Konten apresiasi layanan publik',
            'sentiment' => 'positif',
        ]);
        Post::factory()->for($acc, 'socialAccount')->create([
            'content'   => 'Konten kericuhan demo',
            'sentiment' => 'sensitif',
        ]);

        $res = $this->actingAs($user)->get(route('posts.index', ['sentiment' => 'sensitif']));
        $res->assertOk();
        $res->assertSee('Konten kericuhan demo');
        $res->assertDontSee('Konten apresiasi layanan publik');
        $res->assertSee('Sensitif');
    }

    public function test_analytics_page_renders_valid_js_arrays_and_sentiment_counts(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $acc  = SocialAccount::factory()->youtube()->create();
        Post::factory()->for($acc, 'socialAccount')->create([
            'sentiment' => 'negatif',
            'posted_at' => now()->subDay(),
        ]);
        Post::factory()->for($acc, 'socialAccount')->create([
            'sentiment' => 'positif',
            'posted_at' => now()->subDay(),
        ]);

        $res = $this->actingAs($user)->get(route('analytics', ['tab' => 'content']));
        $res->assertOk();
        $res->assertSee('sentimentChart');
        $res->assertSee('labels: [', false);
        $res->assertSee('backgroundColor: [', false);
        $res->assertSee('"Negatif"', false);
        $res->assertSee('"Positif"', false);
    }
}
