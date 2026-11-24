<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MediaCacheService
{
    private const MAX_BYTES = 5 * 1024 * 1024; // 5 MB

    public function isLocal(?string $url): bool
    {
        return is_string($url) && str_starts_with($url, '/storage/');
    }

    /**
     * Download a remote image thumbnail (e.g. Instagram CDN signed URL that expires in 24-72h)
     * and store it permanently in public storage (`storage/app/public/thumbnails/`).
     *
     * Returns the local `/storage/thumbnails/...` path on success, or falls back to the original URL.
     */
    public function cacheThumbnail(?string $remoteUrl, string $seed = '', bool $forceInTests = false): ?string
    {
        if ($remoteUrl === null || trim($remoteUrl) === '') {
            return $remoteUrl;
        }

        $remoteUrl = trim($remoteUrl);

        if ($this->isLocal($remoteUrl)) {
            return $remoteUrl;
        }

        if (! preg_match('#^https?://#i', $remoteUrl)) {
            return $remoteUrl;
        }

        // Avoid outbound HTTP requests during unrelated unit/feature tests
        if (app()->runningUnitTests() && ! $forceInTests) {
            return $remoteUrl;
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
                    'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
                ])
                ->get($remoteUrl);

            if (! $response->successful()) {
                return $remoteUrl;
            }

            $contentType = strtolower((string) $response->header('Content-Type'));
            if (! str_starts_with($contentType, 'image/')) {
                return $remoteUrl;
            }

            $body = $response->body();
            $size = strlen($body);
            if ($size < 100 || $size > self::MAX_BYTES) {
                return $remoteUrl;
            }

            $ext = match (true) {
                str_contains($contentType, 'png') => 'png',
                str_contains($contentType, 'webp') => 'webp',
                str_contains($contentType, 'gif') => 'gif',
                default => 'jpg',
            };

            $hash = md5($seed !== '' ? $seed : $remoteUrl);
            $relativePath = "thumbnails/{$hash}.{$ext}";

            Storage::disk('public')->put($relativePath, $body);

            return '/storage/' . $relativePath;
        } catch (Throwable) {
            return $remoteUrl;
        }
    }
}
