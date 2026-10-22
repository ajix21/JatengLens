<?php

namespace App\Services\SocialFetch;

use App\Models\SocialAccount;
use Carbon\CarbonImmutable;

interface PlatformFetcher
{
    public function platform(): string;

    public function isConfigured(): bool;

    /** @throws FetchException */
    public function fetch(SocialAccount $account, CarbonImmutable $since, int $limit): FetchedAccount;
}
