<?php

namespace Tests\Feature;

use App\Models\AccountAdmin;
use App\Models\Category;
use App\Models\Keyword;
use App\Models\Post;
use App\Models\Region;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UiUxUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_has_accessible_labels_and_password_toggle(): void
    {
        $res = $this->get(route('login'));

        $res->assertOk();
        $res->assertSee('for="email"', false);
        $res->assertSee('id="email"', false);
        $res->assertSee('for="password"', false);
        $res->assertSee('id="password"', false);
        $res->assertSee('Tampilkan atau sembunyikan password', false);
    }

    public function test_global_shell_and_dashboard_have_skip_link_and_clickable_kpis(): void
    {
        config(['services.gemini.key' => null]);
        $user = User::factory()->create(['role' => 'superadmin', 'is_active' => true]);

        $res = $this->actingAs($user)->get(route('dashboard'));

        $res->assertOk();
        $res->assertSee('Lewati ke konten utama');
        $res->assertSee('id="main-content"', false);
        $res->assertSee('Cakupan Wilayah');
        $res->assertSee('aria-label="Toggle Menu Navigasi"', false);
    }

    public function test_viewer_does_not_see_edit_buttons_on_account_and_region_show(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer', 'is_active' => true]);
        $region = Region::factory()->create(['name' => 'Kota Surakarta']);
        $cat    = Category::factory()->create(['name' => 'Pemerintahan']);
        $acc    = SocialAccount::factory()->youtube()->create([
            'region_id'   => $region->id,
            'category_id' => $cat->id,
        ]);

        $accRes = $this->actingAs($viewer)->get(route('accounts.show', $acc));
        $accRes->assertOk();
        $accRes->assertDontSee(route('accounts.edit', $acc));

        $regRes = $this->actingAs($viewer)->get(route('regions.show', $region));
        $regRes->assertOk();
        $regRes->assertSee('Kota Surakarta — SocioWatch Jateng');
        $regRes->assertDontSee('{{ $region->name }}');
        $regRes->assertDontSee(route('accounts.edit', $acc));
    }

    public function test_edit_pages_render_dynamic_titles_without_literal_curly_braces(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin', 'is_active' => true]);
        $cat   = Category::factory()->create(['name' => 'Infrastruktur']);
        $acc   = SocialAccount::factory()->youtube()->create(['category_id' => $cat->id]);
        $op    = AccountAdmin::create([
            'social_account_id' => $acc->id,
            'full_name'         => 'Budi Santoso',
            'nik'               => '3374011234560001',
        ]);

        $catEdit = $this->actingAs($admin)->get(route('categories.edit', $cat));
        $catEdit->assertOk();
        $catEdit->assertSee('Edit Kategori: Infrastruktur');
        $catEdit->assertDontSee('{{ $category->name }}');

        $admEdit = $this->actingAs($admin)->get(route('admins.edit', $op));
        $admEdit->assertOk();
        $admEdit->assertSee('Edit Data Admin: Budi Santoso');
        $admEdit->assertDontSee('{{ $admin->full_name }}');

        $admIdx = $this->actingAs($admin)->get(route('admins.index'));
        $admIdx->assertOk();
        $admIdx->assertDontSee('3374011234560001');
        $admIdx->assertSee('3374********0001');
    }

    public function test_keyword_highlighting_does_not_corrupt_html_mark_tags(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $acc  = SocialAccount::factory()->youtube()->create();
        $kw1  = Keyword::factory()->create(['word' => 'style', 'color' => '#ef4444', 'is_active' => true]);
        $kw2  = Keyword::factory()->create(['word' => 'color', 'color' => '#3b82f6', 'is_active' => true]);

        $post = Post::factory()->for($acc, 'socialAccount')->create([
            'content' => 'Evaluasi style dan color desain publik Jawa Tengah.',
        ]);
        $post->keywords()->attach([$kw1->id => ['occurrence_count' => 1], $kw2->id => ['occurrence_count' => 1]]);

        $postRes = $this->actingAs($user)->get(route('posts.show', $post));
        $postRes->assertOk();
        $postRes->assertSee('<mark', false);

        $kwRes = $this->actingAs($user)->get(route('keywords.show', $kw1));
        $kwRes->assertOk();
        $kwRes->assertSee(route('accounts.show', $acc), false);
    }

    public function test_all_upgraded_pages_render_ok(): void
    {
        $super = User::factory()->create(['role' => 'superadmin', 'is_active' => true]);
        $acc   = SocialAccount::factory()->youtube()->create();
        $post  = Post::factory()->for($acc, 'socialAccount')->create(['is_flagged' => true]);

        $routes = [
            route('alerts.index'),
            route('alerts.settings'),
            route('accounts.index', ['search' => 'jateng']),
            route('accounts.create'),
            route('accounts.edit', $acc),
            route('accounts.import'),
            route('posts.index'),
            route('posts.create'),
            route('posts.edit', $post),
            route('posts.search', ['q' => 'jateng']),
            route('posts.flagged'),
            route('posts.import'),
            route('keywords.manage'),
            route('analytics'),
            route('map.index'),
            route('regions.index'),
            route('categories.index'),
            route('categories.create'),
            route('admins.create'),
            route('users.index'),
            route('users.create'),
            route('users.edit', $super),
            route('users.activity-log'),
            route('api-tokens.index'),
            route('auto-fetch.index'),
        ];

        foreach ($routes as $url) {
            $this->actingAs($super)->get($url)->assertOk();
        }
    }
}
