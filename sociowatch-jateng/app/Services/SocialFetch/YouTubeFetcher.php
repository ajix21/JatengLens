<?php

namespace App\Services\SocialFetch;

use App\Enums\FetchStatus;
use App\Models\SocialAccount;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class YouTubeFetcher implements PlatformFetcher
{
    private const BASE = 'https://www.googleapis.com/youtube/v3';

    public function platform(): string
    {
        return 'youtube';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.youtube.key'));
    }

    public function fetch(SocialAccount $account, CarbonImmutable $since, int $limit): FetchedAccount
    {
        $channelParam = $this->channelLookup($account);

        $channel = $this->get('channels', $channelParam + ['part' => 'statistics,contentDetails'])['items'][0] ?? null;
        if (! $channel) {
            throw new FetchException(FetchStatus::NotFound, 'Channel YouTube tidak ditemukan.');
        }

        $channelId = (string) $channel['id'];
        $uploads = $channel['contentDetails']['relatedPlaylists']['uploads'] ?? null;
        $posts = $uploads ? $this->posts($uploads, $since, $limit) : [];

        return new FetchedAccount(
            externalId: $channelId,
            followers: (int) ($channel['statistics']['subscriberCount'] ?? 0),
            following: null,
            postCount: (int) ($channel['statistics']['videoCount'] ?? 0),
            posts: $posts,
        );
    }

    /** @return array<string,string> */
    private function channelLookup(SocialAccount $account): array
    {
        if (filled($account->external_id)) {
            return ['id' => $account->external_id];
        }
        $url = (string) $account->profile_url;
        if (preg_match('~/channel/(UC[\w-]+)~', $url, $m)) {
            return ['id' => $m[1]];
        }
        if (preg_match('~/@([\w.-]+)~', $url, $m)) {
            return ['forHandle' => '@'.$m[1]];
        }

        return ['forHandle' => '@'.ltrim((string) $account->username, '@')];
    }

    /** @return list<FetchedPost> */
    private function posts(string $playlistId, CarbonImmutable $since, int $limit): array
    {
        $items = $this->get('playlistItems', [
            'part' => 'contentDetails',
            'playlistId' => $playlistId,
            'maxResults' => max(1, min($limit, 50)),
        ])['items'] ?? [];

        $published = [];
        foreach ($items as $item) {
            $id = $item['contentDetails']['videoId'] ?? null;
            $at = $item['contentDetails']['videoPublishedAt'] ?? null;
            if (! $id || ! $at) {
                continue;
            }
            $at = CarbonImmutable::parse($at);
            if ($at->gte($since)) {
                $published[$id] = $at;
            }
        }
        if ($published === []) {
            return [];
        }

        $videos = $this->get('videos', [
            'part' => 'snippet,statistics',
            'id' => implode(',', array_keys($published)),
        ])['items'] ?? [];

        $posts = [];
        foreach ($videos as $video) {
            $id = $video['id'] ?? null;
            if (! $id || ! isset($published[$id])) {
                continue;
            }
            $title = trim((string) ($video['snippet']['title'] ?? ''));
            $desc = trim((string) ($video['snippet']['description'] ?? ''));
            $stats = $video['statistics'] ?? [];
            $posts[] = new FetchedPost(
                externalId: $id,
                url: 'https://www.youtube.com/watch?v='.$id,
                content: $desc === '' ? $title : "{$title}\n\n{$desc}",
                mediaType: 'video',
                likes: (int) ($stats['likeCount'] ?? 0),
                comments: (int) ($stats['commentCount'] ?? 0),
                views: (int) ($stats['viewCount'] ?? 0),
                postedAt: $published[$id],
            );
        }

        return $posts;
    }

    private function get(string $endpoint, array $query): array
    {
        try {
            $response = Http::timeout(20)
                ->retry(2, 500, fn ($e) => $e instanceof ConnectionException, throw: false)
                ->get(self::BASE.'/'.$endpoint, $query + ['key' => config('services.youtube.key')]);
        } catch (ConnectionException) {
            throw new FetchException(FetchStatus::Error, 'Tidak dapat terhubung ke YouTube API.');
        }

        if ($response->failed()) {
            throw $this->errorFrom($response);
        }

        return $response->json() ?? [];
    }

    private function errorFrom(Response $response): FetchException
    {
        $reason = (string) $response->json('error.errors.0.reason', '');
        $status = match ($reason) {
            'quotaExceeded', 'dailyLimitExceeded', 'rateLimitExceeded' => FetchStatus::Quota,
            'keyInvalid', 'accessNotConfigured', 'ipRefererBlocked', 'keyExpired' => FetchStatus::AuthError,
            default => FetchStatus::Error,
        };
        $detail = $reason !== '' ? $reason : 'tanpa alasan';

        return new FetchException($status, "YouTube API error HTTP {$response->status()} ({$detail}).");
    }
}
