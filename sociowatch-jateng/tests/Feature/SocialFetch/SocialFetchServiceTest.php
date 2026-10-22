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

    private function fp(string $id, int $likes = 7, string $content = 'Halo dunia', ?string $url = null, ?CarbonImmutable $at = null): FetchedPost
    {
        return new FetchedPost($id, $url ?? "https://x.test/$id", $content, 'video', $likes, 2, 3,
            $at ?? CarbonImmutable::now()->subDays(2));
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
        Post::factory()->for($acc, 'socialAccount')->create(['post_url' => 'https://x.test/v1', 'external_id' => null, 'posted_at' => now()->subDay()]);
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

    public function test_first_run_uses_30_day_window_then_resumes_from_last_fetch(): void
    {
        $acc = SocialAccount::factory()->youtube()->create(['last_fetched_at' => null]);
        $f = new FakeFetcher('youtube');
        $f->default = $this->fetched([]);
        $svc = $this->service($f);

        $svc->fetchAccount($acc);
        $svc->fetchAccount($acc->fresh());
        $acc->fresh()->forceFill(['last_fetched_at' => now()->subDays(60)])->save();
        $svc->fetchAccount($acc->fresh());

        $this->assertEqualsWithDelta(now()->subDays(30)->timestamp, $f->sinces[0]->timestamp, 5);
        $this->assertEqualsWithDelta(now()->subDays(30)->timestamp, $f->sinces[1]->timestamp, 5);
        $this->assertEqualsWithDelta(now()->subDays(61)->timestamp, $f->sinces[2]->timestamp, 5);
    }

    public function test_late_run_creates_old_new_post_but_does_not_update_old_existing(): void
    {
        $acc = SocialAccount::factory()->youtube()->create(['last_fetched_at' => now()->subDay()]);
        $old = CarbonImmutable::now()->subDays(20);
        Post::factory()->for($acc, 'socialAccount')->create([
            'external_id' => 'old1', 'likes_count' => 5, 'posted_at' => $old, 'post_url' => 'https://x.test/old1',
        ]);
        $f = new FakeFetcher('youtube');
        $f->default = $this->fetched([
            $this->fp('old1', 99, 'Halo dunia', null, $old),
            $this->fp('old2', 11, 'Halo dunia', null, $old),
        ]);

        $res = $this->service($f)->fetchAccount($acc);

        $this->assertSame(1, $res->newPosts);
        $this->assertSame(0, $res->updatedPosts);
        $this->assertSame(5, Post::where('external_id', 'old1')->first()->likes_count);
        $this->assertSame(11, Post::where('external_id', 'old2')->first()->likes_count);
    }

    public function test_old_manual_post_still_gets_external_id_linked(): void
    {
        $acc = SocialAccount::factory()->youtube()->create();
        $old = CarbonImmutable::now()->subDays(20);
        Post::factory()->for($acc, 'socialAccount')->create([
            'external_id' => null, 'likes_count' => 5, 'posted_at' => $old, 'post_url' => 'https://x.test/v9',
        ]);
        $f = new FakeFetcher('youtube');
        $f->default = $this->fetched([$this->fp('v9', 99, 'Halo dunia', null, $old)]);

        $res = $this->service($f)->fetchAccount($acc);

        $p = Post::first();
        $this->assertSame('v9', $p->external_id);
        $this->assertSame(5, $p->likes_count);
        $this->assertSame(0, $res->updatedPosts);
        $this->assertSame(1, Post::count());
    }

    /** @return array<string, array{string, string, string}> */
    public static function urlVariants(): array
    {
        return [
            'youtu.be si' => ['youtube', 'https://youtu.be/abcDEF12345?si=xyz', 'abcDEF12345'],
            'shorts' => ['youtube', 'https://www.youtube.com/shorts/abcDEF12345', 'abcDEF12345'],
            'm.youtube' => ['youtube', 'https://m.youtube.com/watch?v=abcDEF12345&t=5s', 'abcDEF12345'],
            'no www' => ['youtube', 'http://youtube.com/watch?v=abcDEF12345', 'abcDEF12345'],
            'ig reel query' => ['instagram', 'https://instagram.com/reel/CODE_1-x/?igsh=abc', 'CODE_1-x'],
            'ig p no slash' => ['instagram', 'https://www.instagram.com/p/CODE_1-x', 'CODE_1-x'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('urlVariants')]
    public function test_links_manual_post_with_url_variant(string $platform, string $manualUrl, string $code): void
    {
        $acc = SocialAccount::factory()->{$platform}()->create();
        Post::factory()->for($acc, 'socialAccount')->create(['post_url' => $manualUrl, 'external_id' => null, 'posted_at' => now()->subDay()]);
        $canonical = $platform === 'youtube'
            ? "https://www.youtube.com/watch?v=$code"
            : "https://www.instagram.com/p/$code/";
        $f = new FakeFetcher($platform);
        $f->default = $this->fetched([$this->fp('EXTID', 7, 'Halo dunia', $canonical)]);

        $res = $this->service($f)->fetchAccount($acc);

        $this->assertSame(1, Post::count());
        $this->assertSame('EXTID', Post::first()->external_id);
        $this->assertSame(1, $res->updatedPosts);
    }

    public function test_manual_post_of_another_account_is_not_linked(): void
    {
        $other = SocialAccount::factory()->youtube()->create();
        $mine = SocialAccount::factory()->youtube()->create();
        Post::factory()->for($other, 'socialAccount')->create([
            'post_url' => 'https://youtu.be/abcDEF12345', 'external_id' => null,
        ]);
        $f = new FakeFetcher('youtube');
        $f->default = $this->fetched([$this->fp('abcDEF12345', 7, 'Halo dunia', 'https://www.youtube.com/watch?v=abcDEF12345')]);

        $res = $this->service($f)->fetchAccount($mine);

        $this->assertSame(1, $res->newPosts);
        $this->assertSame(2, Post::count());
        $this->assertNull(Post::where('social_account_id', $other->id)->first()->external_id);
    }

    public function test_quota_on_first_youtube_account_propagates_without_calling_fetcher(): void
    {
        $y1 = SocialAccount::factory()->youtube()->create(['auto_fetch' => true]);
        $y2 = SocialAccount::factory()->youtube()->create(['auto_fetch' => true]);
        $i1 = SocialAccount::factory()->instagram()->create(['auto_fetch' => true]);
        $yf = new FakeFetcher('youtube');
        $yf->queue = [fn () => throw new FetchException(FetchStatus::Quota, 'kuota habis')];
        $ig = new FakeFetcher('instagram');
        $ig->default = $this->fetched([]);

        $this->service($yf, $ig)->fetchAll();

        $this->assertSame(1, $yf->calls);
        $this->assertSame(FetchStatus::Quota, $y1->fresh()->last_fetch_status);
        $this->assertSame(FetchStatus::Quota, $y2->fresh()->last_fetch_status);
        $this->assertSame(1, $ig->calls);
        $this->assertSame(FetchStatus::Ok, $i1->fresh()->last_fetch_status);
    }

    public function test_failure_does_not_persist_rolled_back_dirty_values(): void
    {
        $acc = SocialAccount::factory()->youtube()->create(['followers_count' => 5]);
        $f = new FakeFetcher('youtube');
        $f->default = $this->fetched([$this->fp('v1')]);
        SocialAccount::saving(function (SocialAccount $a) {
            if ($a->isDirty('last_fetched_at')) {
                throw new \RuntimeException('save gagal');
            }
        });

        $res = $this->service($f)->fetchAccount($acc);

        $fresh = $acc->fresh();
        $this->assertSame(FetchStatus::Error, $res->status);
        $this->assertSame(5, $fresh->followers_count);
        $this->assertNull($fresh->external_id);
        $this->assertSame(FetchStatus::Error, $fresh->last_fetch_status);
        $this->assertSame(0, Post::count());
    }

    public function test_success_log_failure_is_isolated_and_rolled_back(): void
    {
        $a = SocialAccount::factory()->youtube()->create(['auto_fetch' => true]);
        $b = SocialAccount::factory()->youtube()->create(['auto_fetch' => true]);
        $f = new FakeFetcher('youtube');
        $f->default = $this->fetched([]);
        $n = 0;
        FetchLog::creating(function (FetchLog $l) use (&$n) {
            if ($l->status === FetchStatus::Ok && ++$n === 1) {
                throw new \RuntimeException('log gagal');
            }
        });

        $results = $this->service($f)->fetchAll();

        $this->assertCount(2, $results);
        $this->assertSame(FetchStatus::Error, $a->fresh()->last_fetch_status);
        $this->assertNull($a->fresh()->last_fetched_at);
        $this->assertSame(FetchStatus::Ok, $b->fresh()->last_fetch_status);
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

    public function test_fetch_account_unconfigured_returns_auth_error_without_recording(): void
    {
        $acc = SocialAccount::factory()->instagram()->create();
        $res = $this->service(new FakeFetcher('instagram', false))->fetchAccount($acc);
        $this->assertSame(FetchStatus::AuthError, $res->status);
        $this->assertSame('kredensial belum diatur', $res->message);
        $this->assertNull($acc->fresh()->last_fetch_status);
        $this->assertNull($acc->fresh()->last_fetch_error);
        $this->assertSame(0, FetchLog::count());
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
