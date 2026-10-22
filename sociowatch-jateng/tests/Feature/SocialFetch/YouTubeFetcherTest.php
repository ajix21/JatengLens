<?php

namespace Tests\Feature\SocialFetch;

use App\Enums\FetchStatus;
use App\Models\SocialAccount;
use App\Services\SocialFetch\FetchException;
use App\Services\SocialFetch\YouTubeFetcher;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YouTubeFetcherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.youtube.key' => 'TESTKEY']);
        Http::preventStrayRequests();
    }

    private function channelJson(): array
    {
        return ['items' => [[
            'id' => 'UC123',
            'statistics' => ['subscriberCount' => '1500', 'videoCount' => '42'],
            'contentDetails' => ['relatedPlaylists' => ['uploads' => 'UU123']],
        ]]];
    }

    public function test_resolves_handle_and_maps_channel_and_videos(): void
    {
        $now = CarbonImmutable::now();
        Http::fake([
            '*/channels*' => Http::response($this->channelJson()),
            '*/playlistItems*' => Http::response(['items' => [
                ['contentDetails' => ['videoId' => 'v1', 'videoPublishedAt' => $now->subDays(2)->toIso8601ZuluString()]],
                ['contentDetails' => ['videoId' => 'v2', 'videoPublishedAt' => $now->subDays(40)->toIso8601ZuluString()]],
            ]]),
            '*/videos*' => Http::response(['items' => [[
                'id' => 'v1',
                'snippet' => ['title' => 'Judul', 'description' => 'Isi'],
                'statistics' => ['viewCount' => '100', 'likeCount' => '7', 'commentCount' => '3'],
            ]]]),
        ]);

        $account = SocialAccount::factory()->youtube()->make(['external_id' => null]);
        $result = (new YouTubeFetcher)->fetch($account, $now->subDays(30), 20);

        $this->assertSame('UC123', $result->externalId);
        $this->assertSame(1500, $result->followers);
        $this->assertNull($result->following);
        $this->assertSame(42, $result->postCount);
        $this->assertCount(1, $result->posts);
        $p = $result->posts[0];
        $this->assertSame('v1', $p->externalId);
        $this->assertSame('https://www.youtube.com/watch?v=v1', $p->url);
        $this->assertSame("Judul\n\nIsi", $p->content);
        $this->assertSame('video', $p->mediaType);
        $this->assertSame(100, $p->views);
        $this->assertSame(7, $p->likes);
        $this->assertSame(3, $p->comments);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/channels') && str_contains(urldecode($r->url()), 'forHandle=@JawaPosTV'));
    }

    public function test_uses_stored_channel_id_or_channel_url(): void
    {
        Http::fake([
            '*/channels*' => Http::response(['items' => []]),
        ]);
        $f = new YouTubeFetcher;
        $since = CarbonImmutable::now()->subDays(30);

        try {
            $f->fetch(SocialAccount::factory()->youtube()->make(['external_id' => 'UCabc']), $since, 5);
        } catch (FetchException) {
        }
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'id=UCabc'));

        try {
            $f->fetch(SocialAccount::factory()->youtube()->make([
                'external_id' => null, 'profile_url' => 'https://youtube.com/channel/UCxyz',
            ]), $since, 5);
        } catch (FetchException) {
        }
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'id=UCxyz'));
    }

    public function test_missing_channel_throws_not_found(): void
    {
        Http::fake(['*/channels*' => Http::response(['items' => []])]);

        try {
            (new YouTubeFetcher)->fetch(SocialAccount::factory()->youtube()->make(), CarbonImmutable::now()->subDay(), 5);
            $this->fail('Expected FetchException');
        } catch (FetchException $e) {
            $this->assertSame(FetchStatus::NotFound, $e->status);
        }
    }

    public function test_quota_and_bad_key_map_to_platform_fatal_statuses(): void
    {
        $account = SocialAccount::factory()->youtube()->make();
        $since = CarbonImmutable::now()->subDay();

        Http::fake(['*' => Http::sequence()
            ->push(['error' => ['errors' => [['reason' => 'quotaExceeded']]]], 403)
            ->push(['error' => ['errors' => [['reason' => 'keyInvalid']]]], 400)]);
        try {
            (new YouTubeFetcher)->fetch($account, $since, 5);
            $this->fail('Expected FetchException');
        } catch (FetchException $e) {
            $this->assertSame(FetchStatus::Quota, $e->status);
            $this->assertStringNotContainsString('TESTKEY', $e->getMessage());
        }

        try {
            (new YouTubeFetcher)->fetch($account, $since, 5);
            $this->fail('Expected FetchException');
        } catch (FetchException $e) {
            $this->assertSame(FetchStatus::AuthError, $e->status);
            $this->assertStringNotContainsString('TESTKEY', $e->getMessage());
        }
    }

    public function test_not_configured_without_key(): void
    {
        config(['services.youtube.key' => null]);
        $this->assertFalse((new YouTubeFetcher)->isConfigured());
        config(['services.youtube.key' => 'x']);
        $this->assertTrue((new YouTubeFetcher)->isConfigured());
    }
}
