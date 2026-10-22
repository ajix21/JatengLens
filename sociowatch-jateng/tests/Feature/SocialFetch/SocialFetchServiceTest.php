<?php

namespace Tests\Feature\SocialFetch;

use App\Enums\FetchStatus;
use App\Models\FetchLog;
use App\Models\Keyword;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Services\KeywordDetectionService;
use App\Services\SocialFetch\FetchedAccount;
use App\Services\SocialFetch\FetchedPost;
use App\Services\SocialFetch\FetchException;
use App\Services\SocialFetch\PlatformFetcher;
use App\Services\SocialFetch\SocialFetchService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialFetchServiceTest extends TestCase
{
    use RefreshDatabase;

    private function fp(string $id, int $likes = 7, string $content = 'Halo dunia'): FetchedPost
    {
        return new FetchedPost($id, "https://x.test/$id", $content, 'video', $likes, 2, 3,
            CarbonImmutable::parse('2026-10-01 10:00:00', 'UTC'));
    }

    private function fetched(array $posts, string $ext = 'EXT1'): FetchedAccount
    {
        return new FetchedAccount($ext, 1500, null, 42, $posts);
    }

    private function service(PlatformFetcher ...$fetchers): SocialFetchService
    {
        return new SocialFetchService($fetchers, new KeywordDetectionService());
    }

    public function test_creates_posts_updates_account_and_logs(): void
    {
        $acc = SocialAccount::factory()->youtube()->create(['auto_fetch' => true]);
        $f = new FakeFetcher('youtube');
        $f->default = $this->fetched([$this->fp('v1'), $this->fp('v2')]);

        $res = $this->service($f)->fetchAccount($acc);

        $acc->refresh();
        $this->assertSame(FetchStatus::Ok, $res->status);
        $this->assertSame(2, $res->newPosts);
        $this->assertSame(1500, $acc->followers_count);
        $this->assertSame(42, $acc->post_count);
        $this->assertSame('EXT1', $acc->external_id);
        $this->assertSame(FetchStatus::Ok, $acc->last_fetch_status);
        $this->assertNotNull($acc->last_fetched_at);
        $this->assertNull($acc->last_fetch_error);
        $this->assertSame(2, Post::whereNotNull('external_id')->count());
        $p = Post::first();
        $this->assertSame('youtube', $p->platform);
        $this->assertFalse($p->is_flagged);
        $this->assertSame(0, $p->shares_count);
        $this->assertSame(1, FetchLog::count());
        $this->assertSame(2, FetchLog::first()->new_posts);
    }

    public function test_second_run_updates_engagement_without_duplicates(): void
    {
        $acc = SocialAccount::factory()->youtube()->create();
        $f = new FakeFetcher('youtube');
        $f->default = $this->fetched([$this->fp('v1', 7), $this->fp('v2', 7)]);
        $svc = $this->service($f);
        $svc->fetchAccount($acc);
        $postedAt = Post::orderBy('id')->first()->posted_at->toDateTimeString();

        $f->default = $this->fetched([$this->fp('v1', 20), $this->fp('v2', 20)]);
        $svc->fetchAccount($acc->fresh());

        $this->assertSame(2, Post::count());
        $first = Post::orderBy('id')->first();
        $this->assertSame(20, $first->likes_count);
        $this->assertSame($postedAt, $first->posted_at->toDateTimeString());
        $log = FetchLog::orderByDesc('id')->first();
        $this->assertSame(2, $log->updated_posts);
        $this->assertSame(0, $log->new_posts);
    }

    public function test_links_existing_manual_post_by_url(): void
    {
        $acc = SocialAccount::factory()->youtube()->create();
        Post::factory()->for($acc, 'socialAccount')->create(['post_url' => 'https://x.test/v1', 'external_id' => null]);
        $f = new FakeFetcher('youtube');
        $f->default = $this->fetched([$this->fp('v1')]);

        $res = $this->service($f)->fetchAccount($acc);

        $this->assertSame(1, Post::count());
        $this->assertSame('v1', Post::first()->external_id);
        $this->assertSame(1, $res->updatedPosts);
        $this->assertSame(0, $res->newPosts);
    }

    public function test_detects_keywords_on_new_posts(): void
    {
        $kw = Keyword::factory()->create(['word' => 'banjir']);
        $acc = SocialAccount::factory()->youtube()->create();
        $f = new FakeFetcher('youtube');
        $f->default = $this->fetched([$this->fp('v1', 1, 'Banjir di Demak')]);

        $this->service($f)->fetchAccount($acc);

        $post = Post::first();
        $this->assertSame(1, $post->keywords()->count());
        $this->assertSame(1, $post->keywords->first()->pivot->occurrence_count);
        $this->assertSame($kw->id, $post->keywords->first()->id);
    }

    public function test_does_not_overwrite_curated_fields(): void
    {
        $acc = SocialAccount::factory()->youtube()->create([
            'display_name' => 'Nama Kurasi', 'bio' => 'Bio kurasi', 'notes' => 'Catatan',
        ]);
        $f = new FakeFetcher('youtube');
        $f->default = $this->fetched([$this->fp('v1')]);

        $this->service($f)->fetchAccount($acc);

        $acc->refresh();
        $this->assertSame('Nama Kurasi', $acc->display_name);
        $this->assertSame('Bio kurasi', $acc->bio);
        $this->assertSame('Catatan', $acc->notes);
    }

    public function test_first_run_uses_30_day_window_then_14(): void
    {
        $acc = SocialAccount::factory()->youtube()->create(['last_fetched_at' => null]);
        $f = new FakeFetcher('youtube');
        $f->default = $this->fetched([]);
        $svc = $this->service($f);

        $svc->fetchAccount($acc);
        $svc->fetchAccount($acc->fresh());

        $this->assertEqualsWithDelta(now()->subDays(30)->timestamp, $f->sinces[0]->timestamp, 5);
        $this->assertEqualsWithDelta(now()->subDays(14)->timestamp, $f->sinces[1]->timestamp, 5);
    }

    public function test_auth_error_stops_only_that_platform(): void
    {
        $ig1 = SocialAccount::factory()->instagram()->create(['auto_fetch' => true]);
        $ig2 = SocialAccount::factory()->instagram()->create(['auto_fetch' => true]);
        $yt = SocialAccount::factory()->youtube()->create(['auto_fetch' => true]);
        $ig = new FakeFetcher('instagram');
        $ig->queue = [fn () => throw new FetchException(FetchStatus::AuthError, 'token ditolak')];
        $yf = new FakeFetcher('youtube');
        $yf->default = $this->fetched([]);

        $results = $this->service($ig, $yf)->fetchAll();

        $this->assertSame(1, $ig->calls);
        $this->assertSame(FetchStatus::AuthError, $ig1->fresh()->last_fetch_status);
        $this->assertSame(FetchStatus::AuthError, $ig2->fresh()->last_fetch_status);
        $this->assertSame('token ditolak', $ig2->fresh()->last_fetch_error);
        $this->assertSame(FetchStatus::Ok, $yt->fresh()->last_fetch_status);
        $this->assertCount(3, $results);
        $this->assertSame(3, FetchLog::count());
        $this->assertNull($ig1->fresh()->last_fetched_at);
    }

    public function test_unexpected_exception_is_isolated(): void
    {
        $a = SocialAccount::factory()->youtube()->create(['auto_fetch' => true]);
        $b = SocialAccount::factory()->youtube()->create(['auto_fetch' => true]);
        $f = new FakeFetcher('youtube');
        $f->queue = [fn () => throw new \RuntimeException('boom')];
        $f->default = $this->fetched([]);

        $this->service($f)->fetchAll();

        $this->assertSame(FetchStatus::Error, $a->fresh()->last_fetch_status);
        $this->assertSame(FetchStatus::Ok, $b->fresh()->last_fetch_status);
    }

    public function test_error_message_never_contains_credentials(): void
    {
        config(['services.youtube.key' => 'SECRETKEY123']);
        $acc = SocialAccount::factory()->youtube()->create();
        $f = new FakeFetcher('youtube');
        $f->queue = [fn () => throw new FetchException(FetchStatus::Error, 'bad key=SECRETKEY123')];

        $res = $this->service($f)->fetchAccount($acc);

        $this->assertStringNotContainsString('SECRETKEY123', (string) $res->message);
        $this->assertStringNotContainsString('SECRETKEY123', (string) $acc->fresh()->last_fetch_error);
        $this->assertStringContainsString('***', $acc->fresh()->last_fetch_error);
        $log = FetchLog::first();
        $this->assertStringNotContainsString('SECRETKEY123', $log->message);
        $this->assertStringContainsString('***', $log->message);
    }

    public function test_skips_unconfigured_platform_and_ineligible_accounts(): void
    {
        SocialAccount::factory()->instagram()->create(['auto_fetch' => true]);
        SocialAccount::factory()->create(['platform' => 'twitter', 'auto_fetch' => true]);
        SocialAccount::factory()->youtube()->create(['auto_fetch' => false]);
        SocialAccount::factory()->youtube()->create(['auto_fetch' => true, 'is_active' => false]);
        $ig = new FakeFetcher('instagram', false);
        $yf = new FakeFetcher('youtube');
        $svc = $this->service($ig, $yf);

        $results = $svc->fetchAll();

        $this->assertCount(0, $results);
        $this->assertSame(0, $ig->calls + $yf->calls);
        $this->assertSame(['instagram'], $svc->skippedPlatforms());
        $this->assertSame(0, FetchLog::count());
    }

    public function test_fetch_account_unconfigured_returns_auth_error(): void
    {
        $acc = SocialAccount::factory()->instagram()->create();
        $res = $this->service(new FakeFetcher('instagram', false))->fetchAccount($acc);
        $this->assertSame(FetchStatus::AuthError, $res->status);
        $this->assertSame('kredensial belum diatur', $res->message);
        $this->assertSame(FetchStatus::AuthError, $acc->fresh()->last_fetch_status);
    }

    public function test_fetch_account_rejects_unsupported_platform(): void
    {
        $acc = SocialAccount::factory()->create(['platform' => 'twitter']);
        $this->expectException(\InvalidArgumentException::class);
        $this->service(new FakeFetcher('youtube'))->fetchAccount($acc);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $acc = SocialAccount::factory()->youtube()->create(['followers_count' => 5]);
        $f = new FakeFetcher('youtube');
        $f->default = $this->fetched([$this->fp('v1'), $this->fp('v2')]);

        $res = $this->service($f)->fetchAccount($acc, true);

        $this->assertSame(2, $res->newPosts);
        $this->assertSame(0, Post::count());
        $this->assertSame(0, FetchLog::count());
        $acc->refresh();
        $this->assertSame(5, $acc->followers_count);
        $this->assertNull($acc->last_fetched_at);
        $this->assertNull($acc->last_fetch_status);
    }
}
