<?php

namespace App\Services\SocialFetch;

use App\Enums\FetchStatus;
use App\Models\SocialAccount;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class InstagramFetcher implements PlatformFetcher
{
    private const BASE = 'https://graph.facebook.com';

    public function platform(): string
    {
        return 'instagram';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.instagram.token')) && filled(config('services.instagram.business_id'));
    }

    public function fetch(SocialAccount $account, CarbonImmutable $since, int $limit): FetchedAccount
    {
        $username = ltrim(trim((string) $account->username), '@');
        if ($username === '') {
            throw new FetchException(FetchStatus::NotFound, 'Username Instagram kosong.');
        }

        $limit = max(1, min($limit, 50));
        $fields = "business_discovery.username({$username}){id,followers_count,follows_count,media_count,"
            ."media.limit({$limit}){id,caption,like_count,comments_count,media_type,media_product_type,permalink,timestamp}}";

        $data = $this->get($fields)['business_discovery'] ?? null;
        if (! is_array($data) || ! isset($data['id'])) {
            throw new FetchException(FetchStatus::NotFound, 'Akun Instagram tidak ditemukan.');
        }

        $posts = [];
        foreach ($data['media']['data'] ?? [] as $m) {
            $id = $m['id'] ?? null;
            $ts = $m['timestamp'] ?? null;
            if (! $id || ! $ts) {
                continue;
            }
            $at = CarbonImmutable::parse($ts);
            if ($at->lt($since)) {
                continue;
            }
            $posts[] = new FetchedPost(
                externalId: (string) $id,
                url: (string) ($m['permalink'] ?? ''),
                content: (string) ($m['caption'] ?? ''),
                mediaType: $this->mediaType($m),
                likes: (int) ($m['like_count'] ?? 0),
                comments: (int) ($m['comments_count'] ?? 0),
                views: 0,
                postedAt: $at,
            );
        }

        return new FetchedAccount(
            externalId: (string) $data['id'],
            followers: (int) ($data['followers_count'] ?? 0),
            following: isset($data['follows_count']) ? (int) $data['follows_count'] : null,
            postCount: (int) ($data['media_count'] ?? 0),
            posts: $posts,
        );
    }

    private function mediaType(array $m): string
    {
        if (($m['media_product_type'] ?? null) === 'REELS') {
            return 'reel';
        }

        return ($m['media_type'] ?? null) === 'VIDEO' ? 'video' : 'image';
    }

    private function get(string $fields): array
    {
        $url = self::BASE.'/'.config('services.instagram.version', 'v23.0').'/'.config('services.instagram.business_id');

        try {
            $response = Http::timeout(20)
                ->retry(2, 500, fn ($e) => $e instanceof ConnectionException, throw: false)
                ->get($url, ['fields' => $fields, 'access_token' => config('services.instagram.token')]);
        } catch (ConnectionException) {
            throw new FetchException(FetchStatus::Error, 'Tidak dapat terhubung ke Instagram Graph API.');
        }

        if ($response->failed()) {
            throw $this->errorFrom($response);
        }

        return $response->json() ?? [];
    }

    private function errorFrom(Response $response): FetchException
    {
        $code = $response->json('error.code');
        $code = is_numeric($code) ? (int) $code : null;

        if (in_array($code, [100, 110], true)) {
            return new FetchException(FetchStatus::Unsupported, 'Akun tidak ditemukan atau bukan akun Business/Creator');
        }

        $status = match (true) {
            $code === 190 => FetchStatus::AuthError,
            in_array($code, [4, 17, 32, 613], true) => FetchStatus::Quota,
            default => FetchStatus::Error,
        };
        $detail = $code !== null ? "kode {$code}" : 'tanpa kode';

        return new FetchException($status, "Instagram API error HTTP {$response->status()} ({$detail}).");
    }
}
