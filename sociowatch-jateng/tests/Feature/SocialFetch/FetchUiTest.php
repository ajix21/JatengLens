<?php

namespace Tests\Feature\SocialFetch;

use App\Enums\FetchStatus;
use App\Models\Category;
use App\Models\Region;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\KeywordDetectionService;
use App\Services\SocialFetch\FetchedAccount;
use App\Services\SocialFetch\SocialFetchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FetchUiTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    private function bindFakes(): void
    {
        $yt = new FakeFetcher('youtube');
        $yt->default = new FetchedAccount('EXT', 100, null, 5, []);
        $this->app->instance(
            SocialFetchService::class,
            new SocialFetchService([$yt], app(KeywordDetectionService::class))
        );
    }

    public function test_editor_can_fetch_now_and_sees_result(): void
    {
        $this->bindFakes();
        $acc = SocialAccount::factory()->youtube()->create();

        $this->actingAs($this->user('admin'))
            ->post(route('accounts.fetch', $acc))
            ->assertRedirect()
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'postingan baru'));
    }

    public function test_viewer_cannot_fetch_now(): void
    {
        $this->bindFakes();
        $acc = SocialAccount::factory()->youtube()->create();

        $this->actingAs($this->user('viewer'))
            ->post(route('accounts.fetch', $acc))
            ->assertForbidden();
    }

    public function test_fetch_now_rejects_other_platforms(): void
    {
        $this->bindFakes();
        $acc = SocialAccount::factory()->create(['platform' => 'twitter']);

        $this->actingAs($this->user('admin'))
            ->post(route('accounts.fetch', $acc))
            ->assertSessionHas('error', 'Platform ini tidak mendukung pengambilan otomatis.');
    }

    public function test_show_page_displays_status_and_button(): void
    {
        $yt = SocialAccount::factory()->youtube()->create([
            'last_fetch_status' => FetchStatus::Quota,
            'last_fetched_at' => now()->subHour(),
        ]);
        $tw = SocialAccount::factory()->create(['platform' => 'twitter']);
        $admin = $this->user('admin');

        $this->actingAs($admin)->get(route('accounts.show', $yt))
            ->assertOk()
            ->assertSee('Ambil sekarang')
            ->assertSee(FetchStatus::Quota->label());
        $this->actingAs($admin)->get(route('accounts.show', $tw))
            ->assertOk()
            ->assertDontSee('Ambil sekarang');
    }

    public function test_auto_fetch_checkbox_persists_on_update(): void
    {
        $acc = SocialAccount::factory()->youtube()->create(['auto_fetch' => true]);

        $this->actingAs($this->user('admin'))->put(route('accounts.update', $acc), [
            'platform' => 'youtube',
            'username' => $acc->username,
            'display_name' => $acc->display_name,
            'category_id' => $acc->category_id,
            'region_id' => $acc->region_id,
        ])->assertRedirect();

        $this->assertFalse($acc->fresh()->auto_fetch);
    }

    private function updatePayload(SocialAccount $acc, array $override = []): array
    {
        return array_merge([
            'platform' => $acc->platform,
            'username' => $acc->username,
            'display_name' => $acc->display_name,
            'profile_url' => $acc->profile_url,
            'category_id' => $acc->category_id,
            'region_id' => $acc->region_id,
            'auto_fetch' => 1,
        ], $override);
    }

    public function test_changing_username_profile_url_or_platform_clears_external_id(): void
    {
        foreach ([['username' => 'baru_user'], ['profile_url' => 'https://youtube.com/@baru'], ['platform' => 'instagram']] as $change) {
            $acc = SocialAccount::factory()->youtube()->create(['external_id' => 'UCold', 'profile_url' => 'https://youtube.com/@lama']);

            $this->actingAs($this->user('admin'))
                ->put(route('accounts.update', $acc), $this->updatePayload($acc, $change))
                ->assertRedirect();

            $this->assertNull($acc->fresh()->external_id, json_encode($change));
        }
    }

    public function test_unchanged_identity_keeps_external_id(): void
    {
        $acc = SocialAccount::factory()->youtube()->create(['external_id' => 'UCkeep']);

        $this->actingAs($this->user('admin'))
            ->put(route('accounts.update', $acc), $this->updatePayload($acc, ['display_name' => 'Nama Baru']))
            ->assertRedirect();

        $this->assertSame('UCkeep', $acc->fresh()->external_id);
    }

    public function test_status_shows_last_success_wording_when_not_ok(): void
    {
        $yt = SocialAccount::factory()->youtube()->create([
            'last_fetch_status' => FetchStatus::Quota,
            'last_fetched_at' => now()->subHours(3),
        ]);

        $this->actingAs($this->user('admin'))->get(route('accounts.show', $yt))
            ->assertSee('terakhir berhasil');
    }

    public function test_fetch_now_raises_time_limit(): void
    {
        $this->bindFakes();
        $acc = SocialAccount::factory()->youtube()->create();
        set_time_limit(30);

        $this->actingAs($this->user('admin'))->post(route('accounts.fetch', $acc));

        $this->assertSame('120', ini_get('max_execution_time'));
    }

    public function test_status_page_for_superadmin_only_and_hides_secrets(): void
    {
        config([
            'services.youtube.key' => 'SECRET-YT-KEY-123',
            'services.instagram.token' => 'SECRET-IG-TOKEN-456',
            'services.instagram.business_id' => '17841000',
        ]);

        $this->actingAs($this->user('superadmin'))->get(route('auto-fetch.index'))
            ->assertOk()
            ->assertSee('Auto-Fetch')
            ->assertDontSee('SECRET-YT-KEY-123')
            ->assertDontSee('SECRET-IG-TOKEN-456');

        $this->actingAs($this->user('admin'))->get(route('auto-fetch.index'))
            ->assertForbidden();
    }

    public function test_status_page_warns_on_instagram_auth_error(): void
    {
        SocialAccount::factory()->instagram()->create([
            'last_fetch_status' => FetchStatus::AuthError,
            'last_fetch_error' => 'token ditolak',
            'last_fetched_at' => now(),
        ]);

        $this->actingAs($this->user('superadmin'))->get(route('auto-fetch.index'))
            ->assertOk()
            ->assertSee('Token Instagram ditolak — perbarui token (lihat docs/setup-auto-fetch.md).');
    }
}
