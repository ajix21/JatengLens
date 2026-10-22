<?php

namespace Tests\Feature\SocialFetch;

use App\Models\SocialAccount;
use App\Services\SocialFetch\FetchedAccount;
use App\Services\SocialFetch\PlatformFetcher;
use Carbon\CarbonImmutable;

class FakeFetcher implements PlatformFetcher
{
    public int $calls = 0;
    /** @var list<CarbonImmutable> */
    public array $sinces = [];
    /** @var list<callable> one behaviour per call; falls back to $default */
    public array $queue = [];
    public ?FetchedAccount $default = null;

    public function __construct(private string $platform, public bool $configured = true) {}

    public function platform(): string { return $this->platform; }

    public function isConfigured(): bool { return $this->configured; }

    public function fetch(SocialAccount $account, CarbonImmutable $since, int $limit): FetchedAccount
    {
        $this->calls++;
        $this->sinces[] = $since;
        $next = array_shift($this->queue);
        if ($next) {
            return $next($account);
        }
        return $this->default;
    }
}
