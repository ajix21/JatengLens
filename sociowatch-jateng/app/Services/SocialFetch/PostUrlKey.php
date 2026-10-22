<?php

namespace App\Services\SocialFetch;

/** Kunci kanonik sebuah URL postingan (ID video YouTube / shortcode Instagram) agar varian URL dianggap sama. */
final class PostUrlKey
{
    public static function from(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^(www|m)\./', '', $host);
        $path = (string) parse_url($url, PHP_URL_PATH);

        if ($host === 'youtu.be' && preg_match('~^/([A-Za-z0-9_-]{6,})~', $path, $m)) {
            return 'yt:'.$m[1];
        }
        if (in_array($host, ['youtube.com', 'music.youtube.com'], true)) {
            if (preg_match('~^/(?:shorts|embed|live)/([A-Za-z0-9_-]{6,})~', $path, $m)) {
                return 'yt:'.$m[1];
            }
            parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
            if (isset($q['v']) && is_string($q['v']) && preg_match('/^[A-Za-z0-9_-]{6,}$/', $q['v'])) {
                return 'yt:'.$q['v'];
            }
        }
        if (in_array($host, ['instagram.com', 'instagr.am'], true)
            && preg_match('~/(?:p|reel|reels|tv)/([A-Za-z0-9_-]+)~', $path, $m)) {
            return 'ig:'.$m[1];
        }

        return null;
    }
}
