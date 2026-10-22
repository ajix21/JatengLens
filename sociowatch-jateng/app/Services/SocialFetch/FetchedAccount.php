<?php

namespace App\Services\SocialFetch;

final readonly class FetchedAccount
{
    /** @param list<FetchedPost> $posts */
    public function __construct(
        public string $externalId,
        public int $followers,
        public ?int $following,
        public int $postCount,
        public array $posts,
    ) {}
}
