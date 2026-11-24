<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\MediaCacheService;
use Illuminate\Console\Command;

class CachePostThumbnails extends Command
{
    protected $signature = 'media:cache-thumbnails {--limit=200 : Maksimal postingan yang diproses}';
    protected $description = 'Unduh dan simpan thumbnail postingan eksternal (Instagram/YouTube CDN) ke penyimpanan lokal agar tidak expired.';

    public function handle(MediaCacheService $mediaCache): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $posts = Post::query()
            ->whereNotNull('media_url')
            ->where('media_url', '!=', '')
            ->where('media_url', 'not like', '/storage/%')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        if ($posts->isEmpty()) {
            $this->info('Semua thumbnail postingan sudah tersimpan lokal.');
            return self::SUCCESS;
        }

        $cached = 0;
        $skipped = 0;

        foreach ($posts as $post) {
            $seed = "{$post->platform}_" . ($post->external_id ?: $post->id);
            $localUrl = $mediaCache->cacheThumbnail($post->media_url, $seed);

            if ($localUrl !== $post->media_url && $mediaCache->isLocal($localUrl)) {
                $post->media_url = $localUrl;
                $post->save();
                $cached++;
            } else {
                $skipped++;
            }
        }

        $this->info("Selesai: {$cached} thumbnail tersimpan lokal, {$skipped} dilewati/expired.");
        return self::SUCCESS;
    }
}
