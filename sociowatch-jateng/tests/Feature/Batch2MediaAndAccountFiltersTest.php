<?php

namespace Tests\Feature;

use App\Enums\FetchStatus;
use App\Models\AccountAdmin;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\MediaCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Batch2MediaAndAccountFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_cache_service_downloads_and_stores_thumbnail_locally(): void
    {
        Storage::fake('public');
        $fakeJpeg = str_repeat("\xFF\xD8\xFF\xE0", 60); // > 100 bytes

        Http::fake([
            'https://scontent.cdninstagram.com/pic1.jpg' => Http::response($fakeJpeg, 200, [
                'Content-Type' => 'image/jpeg',
            ]),
            'https://scontent.cdninstagram.com/broken.jpg' => Http::response('Not found', 404),
        ]);

        $service = new MediaCacheService();

        $cached = $service->cacheThumbnail('https://scontent.cdninstagram.com/pic1.jpg', 'instagram_123', true);
        $this->assertNotNull($cached);
        $this->assertStringStartsWith('/storage/thumbnails/', $cached);
        $this->assertStringEndsWith('.jpg', $cached);

        $relative = str_replace('/storage/', '', $cached);
        Storage::disk('public')->assertExists($relative);

        // Already local URL returns unchanged without re-downloading
        $this->assertSame($cached, $service->cacheThumbnail($cached, 'instagram_123', true));

        // Broken remote URL gracefully falls back to original URL
        $fallback = $service->cacheThumbnail('https://scontent.cdninstagram.com/broken.jpg', 'instagram_999', true);
        $this->assertSame('https://scontent.cdninstagram.com/broken.jpg', $fallback);
    }

    public function test_accounts_index_filters_by_fetch_mode_and_admin_status(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $apiAcc = SocialAccount::factory()->instagram()->create([
            'username' => 'ig_api_account',
            'auto_fetch' => true,
            'last_fetch_status' => FetchStatus::Ok,
        ]);
        AccountAdmin::create([
            'social_account_id' => $apiAcc->id,
            'full_name' => 'Admin Satu',
        ]);

        $extAcc = SocialAccount::factory()->instagram()->create([
            'username' => 'ig_ext_account',
            'auto_fetch' => false,
            'last_fetch_status' => FetchStatus::Unsupported,
        ]);

        $notFoundAcc = SocialAccount::factory()->instagram()->create([
            'username' => 'ig_dead_account',
            'auto_fetch' => false,
            'last_fetch_status' => FetchStatus::NotFound,
        ]);

        $fbAcc = SocialAccount::factory()->create([
            'platform' => 'facebook',
            'username' => 'fb_manual_account',
            'auto_fetch' => false,
        ]);

        // Filter: fetch_mode=api
        $this->actingAs($user)->get(route('accounts.index', ['fetch_mode' => 'api']))
            ->assertOk()
            ->assertSee('@ig_api_account')
            ->assertDontSee('@ig_ext_account')
            ->assertDontSee('@ig_dead_account')
            ->assertDontSee('@fb_manual_account');

        // Filter: fetch_mode=extension
        $this->actingAs($user)->get(route('accounts.index', ['fetch_mode' => 'extension']))
            ->assertOk()
            ->assertSee('@ig_ext_account')
            ->assertDontSee('@ig_api_account')
            ->assertDontSee('@ig_dead_account');

        // Filter: fetch_mode=not_found
        $this->actingAs($user)->get(route('accounts.index', ['fetch_mode' => 'not_found']))
            ->assertOk()
            ->assertSee('@ig_dead_account')
            ->assertDontSee('@ig_api_account');

        // Filter: fetch_mode=manual
        $this->actingAs($user)->get(route('accounts.index', ['fetch_mode' => 'manual']))
            ->assertOk()
            ->assertSee('@fb_manual_account')
            ->assertDontSee('@ig_api_account');

        // Filter: admin_status=has_admin
        $this->actingAs($user)->get(route('accounts.index', ['admin_status' => 'has_admin']))
            ->assertOk()
            ->assertSee('@ig_api_account')
            ->assertDontSee('@ig_ext_account');

        // Filter: admin_status=missing
        $this->actingAs($user)->get(route('accounts.index', ['admin_status' => 'missing']))
            ->assertOk()
            ->assertSee('@ig_ext_account')
            ->assertDontSee('@ig_api_account');
    }
}
