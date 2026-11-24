<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\NarrativeBriefing;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\TelegramAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Batch3AlertingAndTokenExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_telegram_service_is_safe_noop_when_unconfigured(): void
    {
        config(['services.telegram.bot_token' => '', 'services.telegram.chat_id' => '']);
        Http::fake();

        $service = new TelegramAlertService();
        $this->assertFalse($service->isConfigured());
        $this->assertFalse($service->sendMessage('Halo'));
        Http::assertNothingSent();
    }

    public function test_sensitive_post_sync_creates_alert_and_sends_telegram_notification(): void
    {
        config([
            'services.telegram.bot_token' => '12345:FAKE_BOT_TOKEN',
            'services.telegram.chat_id' => '-100999888',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
        ]);

        $account = SocialAccount::factory()->instagram()->create(['username' => 'aksi_jateng']);

        $response = $this->postJson('/api/sync/instagram', [
            'username' => 'aksi_jateng',
            'posts' => [
                [
                    'external_id' => 'ig_sensitif_1',
                    'url' => 'https://www.instagram.com/p/SENS1/',
                    'content' => 'Seruan aksi demo dan unjuk rasa besar besaran menuntut keadilan darurat!',
                    'likes_count' => 120,
                    'comments_count' => 15,
                    'posted_at' => now()->toIso8601String(),
                ],
                [
                    'external_id' => 'ig_netral_1',
                    'url' => 'https://www.instagram.com/p/NETR1/',
                    'content' => 'Jadwal kegiatan rapat rutin minggu ini di sekretariat.',
                    'likes_count' => 10,
                    'comments_count' => 1,
                    'posted_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        $response->assertOk();

        // Exactly 1 in-app Alert created (for the sensitive post, NOT the neutral post)
        $this->assertSame(1, Alert::where('social_account_id', $account->id)->count());
        $alert = Alert::where('social_account_id', $account->id)->first();
        $this->assertSame('keyword_spike', $alert->alert_type);
        $this->assertStringContainsString('@aksi_jateng', $alert->message);

        // Telegram API called exactly once with sensitive post alert
        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.telegram.org/bot12345:FAKE_BOT_TOKEN/sendMessage')
                && $request['chat_id'] === '-100999888'
                && str_contains($request['text'], 'POSTINGAN SENSITIF TERDETEKSI')
                && str_contains($request['text'], '@aksi_jateng');
        });
    }

    public function test_telegram_service_formats_and_sends_narrative_briefing(): void
    {
        config([
            'services.telegram.bot_token' => '12345:FAKE_BOT_TOKEN',
            'services.telegram.chat_id' => '-100999888',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $briefing = NarrativeBriefing::create([
            'period_start' => now()->subDay(),
            'period_end' => now(),
            'window_label' => '24 Jam Terakhir',
            'source' => 'gemini',
            'status' => 'ok',
            'executive_summary' => 'Terdapat peningkatan diskusi konsolidasi mahasiswa di Semarang.',
            'issue_clusters' => [
                ['title' => 'Konsolidasi Mahasiswa', 'urgency' => 'tinggi', 'summary' => 'Seruan aksi'],
            ],
            'recommendations' => ['Pantau perkembangan di Semarang'],
            'stats_snapshot' => [
                'total_posts' => 25,
                'sentiment_counts' => ['sensitif' => 4, 'negatif' => 6, 'netral' => 12, 'positif' => 3],
            ],
        ]);

        $service = new TelegramAlertService();
        $this->assertTrue($service->notifyBriefing($briefing));

        Http::assertSent(function ($request) {
            return str_contains($request['text'], 'RINGKASAN EKSEKUTIF SOCIOWATCH JATENG')
                && str_contains($request['text'], 'Konsolidasi Mahasiswa')
                && str_contains($request['text'], '[TINGGI]');
        });
    }

    public function test_dashboard_shows_instagram_token_expiry_banners(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        // 1. Healthy (30 days left) -> no warning banner
        config([
            'services.instagram.token' => 'test-token',
            'services.instagram.token_expires_at' => now()->addDays(30)->toDateString(),
        ]);
        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Token Meta Graph API Instagram');

        // 2. Expiring soon (5 days left) -> warning banner
        config([
            'services.instagram.token' => 'test-token',
            'services.instagram.token_expires_at' => now()->addDays(5)->toDateString(),
        ]);
        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Token Meta Graph API Instagram Akan Habis dalam 5 Hari');

        // 3. Expired (2 days ago) -> danger banner
        config([
            'services.instagram.token' => 'test-token',
            'services.instagram.token_expires_at' => now()->subDays(2)->toDateString(),
        ]);
        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Token Meta Graph API Instagram Sudah Kedaluwarsa');
    }
}
