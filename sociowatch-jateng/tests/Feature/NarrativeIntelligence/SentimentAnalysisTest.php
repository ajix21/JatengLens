<?php

namespace Tests\Feature\NarrativeIntelligence;

use App\Models\Keyword;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\KeywordDetectionService;
use App\Services\SentimentAnalysisService;
use App\Services\SocialFetch\FetchedAccount;
use App\Services\SocialFetch\FetchedPost;
use App\Services\SocialFetch\SocialFetchService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\SocialFetch\FakeFetcher;
use Tests\TestCase;

class SentimentAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_classifies_sensitive_negative_positive_and_neutral_content(): void
    {
        Keyword::factory()->create(['word' => 'demo buruh', 'category' => 'sensitif']);
        $service = new SentimentAnalysisService();

        $this->assertSame(
            'sensitif',
            $service->analyze('Aksi demo buruh di depan kantor gubernur berakhir ricuh')
        );

        $this->assertSame(
            'negatif',
            $service->analyze('Warga mengeluhkan jalan rusak dan banjir di wilayah Semarang')
        );

        $this->assertSame(
            'positif',
            $service->analyze('Apresiasi atas inovasi pelayanan publik yang berjalan aman dan kondusif')
        );

        $this->assertSame(
            'netral',
            $service->analyze('Rapat koordinasi mingguan dilaksanakan hari Senin pukul 09.00 WIB')
        );
    }

    public function test_negation_neutralizes_negative_and_sensitive_terms(): void
    {
        Keyword::factory()->create(['word' => 'ricuh', 'category' => 'sensitif']);
        Keyword::factory()->create(['word' => 'banjir', 'category' => 'negatif']);
        $service = new SentimentAnalysisService();

        $this->assertNotSame(
            'sensitif',
            $service->analyze('Tidak ada ricuh saat penyampaian aspirasi berlangsung')
        );
        $this->assertSame(
            'netral',
            $service->analyze('Tidak ada ricuh saat penyampaian aspirasi berlangsung')
        );

        $this->assertNotSame(
            'negatif',
            $service->analyze('Alhamdulillah wilayah ini bebas banjir tahun ini')
        );
        $this->assertSame(
            'netral',
            $service->analyze('Alhamdulillah wilayah ini bebas banjir tahun ini')
        );

        $this->assertSame(
            'netral',
            $service->analyze('Mboten wonten kerusuhan di alun-alun kota')
        );
    }

    public function test_negation_flips_positive_terms_to_negative(): void
    {
        Keyword::factory()->create(['word' => 'kondusif', 'category' => 'positif']);
        $service = new SentimentAnalysisService();

        $this->assertSame(
            'negatif',
            $service->analyze('Situasi di lapangan belum kondusif dan warga merasa tidak aman')
        );
    }

    public function test_manual_sentiment_is_preserved_during_rescan_and_autofetch(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $account = SocialAccount::factory()->youtube()->create();

        $manualPost = Post::factory()->for($account, 'socialAccount')->create([
            'external_id'      => 'yt-manual-1',
            'content'          => 'Demo ricuh di pusat kota',
            'posted_at'        => now()->subDays(2),
            'sentiment'        => 'positif',
            'sentiment_manual' => true,
        ]);

        $autoPost = Post::factory()->for($account, 'socialAccount')->create([
            'external_id'      => 'yt-auto-1',
            'content'          => 'Demo ricuh di pusat kota',
            'posted_at'        => now()->subDays(2),
            'sentiment'        => 'netral',
            'sentiment_manual' => false,
        ]);

        // Rescan keywords & sentiment
        $this->actingAs($user)
            ->post(route('keywords.rescan'))
            ->assertRedirect();

        $this->assertSame('positif', $manualPost->fresh()->sentiment);
        $this->assertTrue($manualPost->fresh()->sentiment_manual);
        $this->assertSame('sensitif', $autoPost->fresh()->sentiment);
        $this->assertFalse($autoPost->fresh()->sentiment_manual);

        // Auto-fetch updates content for both and inserts a new post
        $fetcher = new FakeFetcher('youtube');
        $fetcher->default = new FetchedAccount('EXT1', 1000, null, 10, [
            new FetchedPost(
                'yt-manual-1',
                'https://youtube.com/watch?v=yt-manual-1',
                'Kerusuhan dan bentrok susulan terjadi malam ini',
                'video',
                50,
                10,
                500,
                CarbonImmutable::now()->subDays(2),
            ),
            new FetchedPost(
                'yt-auto-1',
                'https://youtube.com/watch?v=yt-auto-1',
                'Situasi kembali aman, tertib, dan kondusif',
                'video',
                40,
                5,
                300,
                CarbonImmutable::now()->subDays(2),
            ),
            new FetchedPost(
                'yt-new-1',
                'https://youtube.com/watch?v=yt-new-1',
                'Warga mengeluhkan jalan rusak dan macet parah',
                'video',
                15,
                2,
                120,
                CarbonImmutable::now()->subDay(),
            ),
        ]);

        $fetchService = new SocialFetchService(
            [$fetcher],
            new KeywordDetectionService(),
            new SentimentAnalysisService()
        );
        $fetchService->fetchAccount($account);

        $manualPost->refresh();
        $this->assertSame('Kerusuhan dan bentrok susulan terjadi malam ini', $manualPost->content);
        $this->assertSame('positif', $manualPost->sentiment);
        $this->assertTrue($manualPost->sentiment_manual);

        $autoPost->refresh();
        $this->assertSame('positif', $autoPost->sentiment);
        $this->assertFalse($autoPost->sentiment_manual);

        $newPost = Post::where('external_id', 'yt-new-1')->firstOrFail();
        $this->assertSame('negatif', $newPost->sentiment);
        $this->assertFalse($newPost->sentiment_manual);
    }
}
