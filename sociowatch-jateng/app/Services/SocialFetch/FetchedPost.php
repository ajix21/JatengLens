<?php

namespace App\Services\SocialFetch;

use Carbon\CarbonImmutable;

final readonly class FetchedPost
{
    /** @param string $mediaType one of text,image,video,reel,story */
    public function __construct(
        public string $externalId,
        public string $url,
        public string $content,
        public string $mediaType,
        public int $likes,
        public int $comments,
        public int $views,
        public CarbonImmutable $postedAt,
        public ?string $mediaUrl = null,
    ) {}
}
