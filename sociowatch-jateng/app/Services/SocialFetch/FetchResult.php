<?php

namespace App\Services\SocialFetch;

use App\Enums\FetchStatus;
use App\Models\SocialAccount;

final readonly class FetchResult
{
    public function __construct(
        public SocialAccount $account,
        public FetchStatus $status,
        public int $newPosts,
        public int $updatedPosts,
        public ?string $message,
    ) {}
}
