<?php

namespace Tests\Feature;

use App\Enums\FetchStatus;
use App\Models\FetchLog;
use App\Models\Keyword;
use App\Models\Post;
use App\Models\SocialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrowserSyncApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_unsupported_accounts_returns_eligible_accounts(): void
    {
        // 1 Unsupported account
        $unsupported = SocialAccount::factory()->instagram()->create([
            'username' => 'fmn_purwokerto',
            'is_active' => true,
            'last_fetch_status' => FetchStatus::Unsupported,
        ]);

        // 1 Account with OK status (already handled by Graph API)
        $okAccount = SocialAccount::factory()->instagram()->create([
            'username' => 'bawaslu_jateng',
            'is_active' => true,
            'last_fetch_status' => FetchStatus::Ok,
            'auto_fetch' => true,
        ]);

        // 1 Inactive account
        SocialAccount::factory()->instagram()->create([
            'username' => 'inactive_acc',
            'is_active' => false,
            'last_fetch_status' => FetchStatus::Unsupported,
        ]);

        // Default query: should only include unsupported
        $response = $this->getJson('/api/sync/unsupported-accounts');
        $response->assertOk()
            ->assertJson([
                'success' => true,
                'count' => 1,
            ])
            ->assertJsonFragment(['username' => 'fmn_purwokerto'])
            ->assertJsonMissing(['username' => 'bawaslu_jateng'])
            ->assertJsonMissing(['username' => 'inactive_acc']);

        // Query with ?all=1: should include both active accounts
        $responseAll = $this->getJson('/api/sync/unsupported-accounts?all=1');
        $responseAll->assertOk()
            ->assertJson([
                'success' => true,
                'count' => 2,
            ])
            ->assertJsonFragment(['username' => 'fmn_purwokerto'])
            ->assertJsonFragment(['username' => 'bawaslu_jateng']);
    }

    public function test_sync_instagram_ingests_posts_and_updates_account_successfully(): void
    {
        $account = SocialAccount::factory()->instagram()->create([
            'username' => 'fmn_purwokerto',
            'is_active' => true,
            'followers_count' => 100,
            'last_fetch_status' => FetchStatus::Unsupported,
        ]);

        $kwAksi = Keyword::factory()->create([
            'word' => 'aksi',
            'color' => '#ef4444',
            'is_active' => true,
        ]);

        $payload = [
            'username' => 'fmn_purwokerto',
            'profile' => [
                'external_id' => 'ig_123456',
                'followers_count' => 2500,
                'following_count' => 450,
                'post_count' => 30,
                'bio' => 'Front Mahasiswa Nasional Purwokerto',
            ],
            'posts' => [
                [
                    'external_id' => 'ig_post_001',
                    'url' => 'https://www.instagram.com/p/DB123456/',
                    'content' => 'Ratusan massa peserta aksi menggelar orasi damai menuntut keadilan.',
                    'media_type' => 'image',
                    'likes_count' => 150,
                    'comments_count' => 25,
                    'views_count' => 0,
                    'posted_at' => now()->subHours(3)->toIso8601String(),
                ],
                [
                    'external_id' => 'ig_post_002',
                    'url' => 'https://www.instagram.com/p/DB654321/',
                    'content' => 'Menjadi saksi sejarah perjalanan perjuangan.', // Note: contains 'saksi', NOT standalone 'aksi'
                    'media_type' => 'reel',
                    'likes_count' => 80,
                    'comments_count' => 5,
                    'views_count' => 500,
                    'posted_at' => now()->subDay()->toIso8601String(),
                ],
            ],
        ];

        $response = $this->postJson('/api/sync/instagram', $payload);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'account' => [
                    'username' => 'fmn_purwokerto',
                    'followers_count' => 2500,
                    'last_fetch_status' => 'ok',
                ],
                'result' => [
                    'status' => 'ok',
                    'new_posts' => 2,
                    'updated_posts' => 0,
                ],
            ]);

        // Assert Account updated
        $account->refresh();
        $this->assertSame(2500, $account->followers_count);
        $this->assertSame(FetchStatus::Ok, $account->last_fetch_status);
        $this->assertNotNull($account->last_fetched_at);
        $this->assertSame('Front Mahasiswa Nasional Purwokerto', $account->bio);

        // Assert Posts created
        $post1 = Post::where('external_id', 'ig_post_001')->first();
        $this->assertNotNull($post1);
        $this->assertSame(150, $post1->likes_count);
        $this->assertSame('image', $post1->media_type);
        // Post 1 contains 'aksi' -> must be detected
        $this->assertTrue($post1->keywords->contains('id', $kwAksi->id));

        $post2 = Post::where('external_id', 'ig_post_002')->first();
        $this->assertNotNull($post2);
        $this->assertSame('video', $post2->media_type);
        // Post 2 contains only 'saksi', NOT 'aksi' -> must NOT match!
        $this->assertFalse($post2->keywords->contains('id', $kwAksi->id));

        // Assert FetchLog created
        $log = FetchLog::where('social_account_id', $account->id)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame(FetchStatus::Ok, $log->status);
        $this->assertSame(2, $log->new_posts);
        $this->assertStringContainsString('Browser Extension', (string) $log->message);
    }

    public function test_sync_instagram_updates_existing_posts(): void
    {
        $account = SocialAccount::factory()->instagram()->create(['username' => 'fmn_unsoed']);
        $existingPost = Post::factory()->for($account, 'socialAccount')->create([
            'external_id' => 'ig_post_existing',
            'post_url' => 'https://www.instagram.com/p/EXISTING1/',
            'likes_count' => 10,
            'comments_count' => 2,
            'content' => 'Old content',
            'posted_at' => now()->subDays(2),
        ]);

        $payload = [
            'username' => 'fmn_unsoed',
            'posts' => [
                [
                    'external_id' => 'ig_post_existing',
                    'url' => 'https://www.instagram.com/p/EXISTING1/',
                    'content' => 'Old content',
                    'likes_count' => 95,
                    'comments_count' => 18,
                    'posted_at' => now()->subDays(2)->toIso8601String(),
                ],
            ],
        ];

        $response = $this->postJson('/api/sync/instagram', $payload);
        $response->assertOk()
            ->assertJson([
                'result' => [
                    'new_posts' => 0,
                    'updated_posts' => 1,
                ],
            ]);

        $existingPost->refresh();
        $this->assertSame(95, $existingPost->likes_count);
        $this->assertSame(18, $existingPost->comments_count);
    }

    public function test_sync_instagram_returns_404_if_account_not_found(): void
    {
        $response = $this->postJson('/api/sync/instagram', [
            'username' => 'non_existent_account_123',
            'posts' => [],
        ]);

        $response->assertNotFound()
            ->assertJson(['success' => false]);
    }

    public function test_report_status_marks_account_not_found_and_disables_auto_fetch(): void
    {
        $account = SocialAccount::factory()->instagram()->create([
            'username' => 'deleted_ig_user',
            'auto_fetch' => true,
            'last_fetch_status' => FetchStatus::Unsupported,
        ]);

        $response = $this->postJson('/api/sync/report-status', [
            'username' => 'deleted_ig_user',
            'status' => 'not_found',
            'message' => 'Halaman profil @deleted_ig_user tidak tersedia di Instagram.',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'account' => [
                    'username' => 'deleted_ig_user',
                    'last_fetch_status' => 'not_found',
                    'auto_fetch' => false,
                ],
            ]);

        $account->refresh();
        $this->assertSame(FetchStatus::NotFound, $account->last_fetch_status);
        $this->assertFalse($account->auto_fetch);
        $this->assertStringContainsString('[TIDAK DITEMUKAN]', (string) $account->notes);

        $log = FetchLog::where('social_account_id', $account->id)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame(FetchStatus::NotFound, $log->status);
    }

    public function test_sync_endpoints_require_valid_x_sync_token_when_configured(): void
    {
        config(['services.browser_sync.token' => 'secret-jateng-token-123']);

        // Missing token -> 401
        $this->getJson('/api/sync/unsupported-accounts')
            ->assertUnauthorized()
            ->assertJson(['success' => false]);

        // Wrong token -> 401
        $this->getJson('/api/sync/unsupported-accounts', ['X-Sync-Token' => 'wrong-token'])
            ->assertUnauthorized();

        // Valid X-Sync-Token -> 200
        $this->getJson('/api/sync/unsupported-accounts', ['X-Sync-Token' => 'secret-jateng-token-123'])
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_sync_facebook_ingests_posts_and_lists_facebook_accounts(): void
    {
        $fbAccount = SocialAccount::factory()->create([
            'platform' => 'facebook',
            'username' => 'KoranKejora1961',
            'display_name' => 'Koran Kejora 1961',
            'is_active' => true,
            'auto_fetch' => false,
        ]);

        // Facebook accounts are automatically included in unsupported-accounts list
        $this->getJson('/api/sync/unsupported-accounts?platform=facebook')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'count' => 1,
            ])
            ->assertJsonFragment([
                'platform' => 'facebook',
                'username' => 'KoranKejora1961',
            ]);

        // Ingest Facebook posts
        $response = $this->postJson('/api/sync/facebook', [
            'account_id' => $fbAccount->id,
            'username' => 'KoranKejora1961',
            'profile' => [
                'followers_count' => 1850,
            ],
            'posts' => [
                [
                    'url' => 'https://www.facebook.com/KoranKejora1961/posts/pfbid0123456',
                    'content' => 'Berita perkembangan kegiatan masyarakat hari ini di Jawa Tengah.',
                    'media_type' => 'image',
                    'likes_count' => 45,
                    'comments_count' => 12,
                    'posted_at' => now()->subHours(4)->toIso8601String(),
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'account' => [
                    'platform' => 'facebook',
                    'username' => 'KoranKejora1961',
                    'followers_count' => 1850,
                    'last_fetch_status' => 'ok',
                ],
                'result' => [
                    'new_posts' => 1,
                    'updated_posts' => 0,
                ],
            ]);

        $this->assertDatabaseHas('posts', [
            'social_account_id' => $fbAccount->id,
            'platform' => 'facebook',
            'likes_count' => 45,
            'comments_count' => 12,
        ]);
    }
}
