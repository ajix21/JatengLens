<?php

namespace Tests\Feature\SocialFetch;

use App\Enums\FetchStatus;
use App\Models\SocialAccount;
use App\Services\SocialFetch\FetchException;
use App\Services\SocialFetch\InstagramFetcher;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InstagramFetcherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.instagram.token' => 'TESTTOKEN',
            'services.instagram.business_id' => '1789',
            'services.instagram.version' => 'v23.0',
        ]);
        Http::preventStrayRequests();
    }

    private function body(array $media): array
    {
        return ['business_discovery' => [
            'id' => '555', 'followers_count' => 2000, 'follows_count' => 10, 'media_count' => 300,
            'media' => ['data' => $media],
        ]];
    }

    private function ts(CarbonImmutable $d): string
    {
        return $d->format('Y-m-d\TH:i:sO');
    }

    private function fetch(?SocialAccount $account = null, int $days = 7, int $limit = 50)
    {
        return (new InstagramFetcher)->fetch(
            $account ?? SocialAccount::factory()->instagram()->make(),
            CarbonImmutable::now()->subDays($days),
            $limit,
        );
    }

    public function test_maps_business_discovery_response(): void
    {
        $y = CarbonImmutable::now()->subDay();
        Http::fake(['graph.facebook.com/v23.0/1789*' => Http::response($this->body([
            ['id' => 'a', 'caption' => 'Halo', 'like_count' => 5, 'comments_count' => 1, 'media_type' => 'IMAGE',
                'media_product_type' => 'FEED', 'permalink' => 'https://www.instagram.com/p/A/', 'timestamp' => $this->ts($y)],
            ['id' => 'b', 'caption' => 'Reel', 'like_count' => 9, 'comments_count' => 2, 'media_type' => 'VIDEO',
                'media_product_type' => 'REELS', 'permalink' => 'https://www.instagram.com/reel/B/', 'timestamp' => $this->ts($y)],
            ['id' => 'c', 'caption' => 'Album', 'like_count' => 3, 'comments_count' => 0, 'media_type' => 'CAROUSEL_ALBUM',
                'media_product_type' => 'FEED', 'permalink' => 'https://www.instagram.com/p/C/', 'timestamp' => $this->ts($y)],
        ]))]);

        $r = $this->fetch();

        $this->assertSame('555', $r->externalId);
        $this->assertSame(2000, $r->followers);
        $this->assertSame(10, $r->following);
        $this->assertSame(300, $r->postCount);
        $this->assertSame(['image', 'reel', 'image'], array_map(fn ($p) => $p->mediaType, $r->posts));
        $this->assertSame('https://www.instagram.com/p/A/', $r->posts[0]->url);
        $this->assertSame('Halo', $r->posts[0]->content);
        $this->assertSame(5, $r->posts[0]->likes);
        $this->assertSame(1, $r->posts[0]->comments);
        $this->assertSame(0, $r->posts[0]->views);
        $this->assertInstanceOf(CarbonImmutable::class, $r->posts[0]->postedAt);

        Http::assertSent(function (Request $req) {
            $f = $req['fields'];

            return str_contains($f, 'business_discovery.username(nu_jateng)')
                && str_contains($f, 'media.limit(50)')
                && $req['access_token'] === 'TESTTOKEN';
        });
    }

    public function test_maps_media_without_caption_or_likes(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response($this->body([
            ['id' => 'a', 'comments_count' => 1, 'media_type' => 'IMAGE', 'permalink' => 'https://www.instagram.com/p/A/',
                'timestamp' => $this->ts(CarbonImmutable::now()->subDay())],
        ]))]);

        $r = $this->fetch();

        $this->assertSame('', $r->posts[0]->content);
        $this->assertSame(0, $r->posts[0]->likes);
    }

    public function test_filters_media_older_than_since(): void
    {
        $now = CarbonImmutable::now();
        Http::fake(['graph.facebook.com/*' => Http::response($this->body([
            ['id' => 'new', 'media_type' => 'IMAGE', 'permalink' => 'u1', 'timestamp' => $this->ts($now->subDay())],
            ['id' => 'old', 'media_type' => 'IMAGE', 'permalink' => 'u2', 'timestamp' => $this->ts($now->subDays(40))],
        ]))]);

        $r = $this->fetch();

        $this->assertCount(1, $r->posts);
        $this->assertSame('new', $r->posts[0]->externalId);
    }

    public function test_strips_leading_at_from_username(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response($this->body([]))]);

        $this->fetch(SocialAccount::factory()->instagram()->make(['username' => '@nu_jateng']), 1, 5);

        Http::assertSent(fn (Request $req) => str_contains($req['fields'], 'username(nu_jateng)'));
    }

    public function test_empty_username_is_not_found_without_request(): void
    {
        try {
            $this->fetch(SocialAccount::factory()->instagram()->make(['username' => '@']), 1, 5);
            $this->fail('FetchException expected');
        } catch (FetchException $e) {
            $this->assertSame(FetchStatus::NotFound, $e->status);
        }
        Http::assertNothingSent();
    }

    public function test_is_configured_requires_token_and_business_id(): void
    {
        $this->assertTrue((new InstagramFetcher)->isConfigured());
        config(['services.instagram.business_id' => null]);
        $this->assertFalse((new InstagramFetcher)->isConfigured());
        $this->assertSame('instagram', (new InstagramFetcher)->platform());
    }

    #[DataProvider('errorCodes')]
    public function test_error_codes_map_to_statuses(int $code, FetchStatus $expected, ?string $message): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['code' => $code, 'message' => 'raw TESTTOKEN']], 400)]);

        try {
            $this->fetch(null, 1, 5);
            $this->fail('FetchException expected');
        } catch (FetchException $e) {
            $this->assertSame($expected, $e->status);
            if ($message) {
                $this->assertSame($message, $e->getMessage());
            }
            $this->assertStringNotContainsString('TESTTOKEN', $e->getMessage());
        }
    }

    public static function errorCodes(): array
    {
        $m = 'Akun tidak ditemukan atau bukan akun Business/Creator';

        return [
            '190' => [190, FetchStatus::AuthError, null],
            '4' => [4, FetchStatus::Quota, null],
            '17' => [17, FetchStatus::Quota, null],
            '32' => [32, FetchStatus::Quota, null],
            '613' => [613, FetchStatus::Quota, null],
            '100' => [100, FetchStatus::Unsupported, $m],
            '110' => [110, FetchStatus::Unsupported, $m],
            'other' => [999, FetchStatus::Error, null],
        ];
    }

    public function test_non_json_failure_maps_to_error_without_token(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response('boom TESTTOKEN', 500)]);

        try {
            $this->fetch(null, 1, 5);
            $this->fail('FetchException expected');
        } catch (FetchException $e) {
            $this->assertSame(FetchStatus::Error, $e->status);
            $this->assertStringNotContainsString('TESTTOKEN', $e->getMessage());
        }
    }

    public function test_connection_exception_maps_to_error_without_token(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error access_token=TESTTOKEN'));

        try {
            $this->fetch(null, 1, 5);
            $this->fail('FetchException expected');
        } catch (FetchException $e) {
            $this->assertSame(FetchStatus::Error, $e->status);
            $this->assertStringNotContainsString('TESTTOKEN', $e->getMessage());
            $this->assertNull($e->getPrevious());
        }
    }
}
