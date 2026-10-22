<?php

namespace App\Services\SocialFetch;

use App\Enums\FetchStatus;
use App\Models\FetchLog;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Services\KeywordDetectionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class SocialFetchService
{
    private const LIMIT = 50;
    private const MESSAGE_MAX = 500;
    private const FIRST_WINDOW_DAYS = 30;
    private const ENGAGEMENT_DAYS = 14;

    /** @var array<string, PlatformFetcher> */
    private array $fetchers = [];

    /** @var list<string> */
    private array $skippedPlatforms = [];

    /** @param iterable<PlatformFetcher> $fetchers */
    public function __construct(iterable $fetchers, private KeywordDetectionService $detector)
    {
        foreach ($fetchers as $fetcher) {
            $this->fetchers[$fetcher->platform()] = $fetcher;
        }
    }

    /** @return list<string> */
    public function skippedPlatforms(): array
    {
        return $this->skippedPlatforms;
    }

    public function eligibleAccounts(?string $platform, ?int $accountId): Builder
    {
        return SocialAccount::query()
            ->whereIn('platform', array_keys($this->fetchers))
            ->where('is_active', true)
            ->where('auto_fetch', true)
            ->when($platform, fn (Builder $q) => $q->where('platform', $platform))
            ->when($accountId, fn (Builder $q) => $q->where('id', $accountId))
            ->orderBy('platform')
            ->orderBy('id');
    }

    /** @return Collection<int, FetchResult> */
    public function fetchAll(?string $platform = null, ?int $accountId = null, bool $dryRun = false): Collection
    {
        $this->skippedPlatforms = [];
        $results = collect();

        $configured = [];
        foreach ($this->fetchers as $name => $fetcher) {
            if ($platform !== null && $platform !== $name) {
                continue;
            }
            if ($fetcher->isConfigured()) {
                $configured[] = $name;
            } else {
                $this->skippedPlatforms[] = $name;
            }
        }
        sort($this->skippedPlatforms);

        /** @var array<string, array{FetchStatus, string}> $fatal */
        $fatal = [];

        $accounts = $this->eligibleAccounts($platform, $accountId)
            ->whereIn('platform', $configured)
            ->get();

        foreach ($accounts as $account) {
            if (isset($fatal[$account->platform])) {
                [$status, $message] = $fatal[$account->platform];
                $this->recordFailure($account, $status, $message, $dryRun);
                $results->push(new FetchResult($account, $status, 0, 0, $message));
                continue;
            }

            $result = $this->process($account, $this->fetchers[$account->platform], $dryRun);
            $results->push($result);

            if ($result->status->isPlatformFatal()) {
                $fatal[$account->platform] = [$result->status, (string) $result->message];
            }
        }

        return $results;
    }

    public function fetchAccount(SocialAccount $account, bool $dryRun = false): FetchResult
    {
        $fetcher = $this->fetchers[$account->platform] ?? null;
        if ($fetcher === null) {
            throw new InvalidArgumentException("Platform {$account->platform} tidak didukung untuk auto-fetch.");
        }

        if (! $fetcher->isConfigured()) {
            return new FetchResult($account, FetchStatus::AuthError, 0, 0, 'kredensial belum diatur');
        }

        return $this->process($account, $fetcher, $dryRun);
    }

    private function process(SocialAccount $account, PlatformFetcher $fetcher, bool $dryRun): FetchResult
    {
        $since = CarbonImmutable::now()->subDays(self::FIRST_WINDOW_DAYS);
        if ($account->last_fetched_at !== null) {
            $resume = CarbonImmutable::instance($account->last_fetched_at)->subDay();
            $since = $resume->lt($since) ? $resume : $since;
        }

        try {
            $fetched = $fetcher->fetch($account, $since, self::LIMIT);
            [$new, $updated] = $dryRun
                ? $this->countOnly($account, $fetched)
                : DB::transaction(function () use ($account, $fetched) {
                    $counts = $this->persist($account, $fetched);
                    FetchLog::create([
                        'social_account_id' => $account->id,
                        'status' => FetchStatus::Ok,
                        'new_posts' => $counts[0],
                        'updated_posts' => $counts[1],
                        'message' => null,
                        'fetched_at' => now(),
                    ]);

                    return $counts;
                });
        } catch (FetchException $e) {
            $message = $this->sanitize($e->getMessage());
            $this->recordFailure($account, $e->status, $message, $dryRun);

            return new FetchResult($account, $e->status, 0, 0, $message);
        } catch (Throwable $e) {
            $message = $this->sanitize($e->getMessage());
            $this->recordFailure($account, FetchStatus::Error, $message, $dryRun);

            return new FetchResult($account, FetchStatus::Error, 0, 0, $message);
        }

        return new FetchResult($account, FetchStatus::Ok, $new, $updated, null);
    }

    /** @return array{int, int} */
    private function persist(SocialAccount $account, FetchedAccount $fetched): array
    {
        $new = 0;
        $updated = 0;
        $tz = config('app.timezone');

        foreach ($fetched->posts as $dto) {
            $post = $this->findExisting($account, $dto);

            if ($post) {
                if (! $this->isRecent($post)) {
                    // Postingan lama: hanya tautkan external_id, jangan sentuh engagement/konten.
                    if ($post->external_id === null) {
                        $post->external_id = $dto->externalId;
                        $post->save();
                    }
                    continue;
                }
                $post->fill([
                    'likes_count' => $dto->likes,
                    'comments_count' => $dto->comments,
                    'views_count' => $dto->views,
                    'external_id' => $dto->externalId,
                ]);
                $contentChanged = $post->content !== $dto->content;
                if ($contentChanged) {
                    $post->content = $dto->content;
                }
                $post->save();
                if ($contentChanged) {
                    $post->keywords()->sync($this->detector->detect($post->content));
                }
                $updated++;
            } else {
                $post = Post::create([
                    'social_account_id' => $account->id,
                    'platform' => $account->platform,
                    'external_id' => $dto->externalId,
                    'post_url' => $dto->url,
                    'content' => $dto->content,
                    'media_type' => $dto->mediaType,
                    'likes_count' => $dto->likes,
                    'comments_count' => $dto->comments,
                    'shares_count' => 0,
                    'views_count' => $dto->views,
                    'posted_at' => $dto->postedAt->setTimezone($tz),
                    'is_flagged' => false,
                ]);
                $post->keywords()->sync($this->detector->detect($post->content));
                $new++;
            }
        }

        $attrs = [
            'followers_count' => $fetched->followers,
            'post_count' => $fetched->postCount,
            'external_id' => $fetched->externalId,
            'last_fetched_at' => now(),
            'last_fetch_status' => FetchStatus::Ok,
            'last_fetch_error' => null,
        ];
        if ($fetched->following !== null) {
            $attrs['following_count'] = $fetched->following;
        }
        $account->forceFill($attrs)->save();

        return [$new, $updated];
    }

    /** @return array{int, int} */
    private function countOnly(SocialAccount $account, FetchedAccount $fetched): array
    {
        $new = 0;
        $updated = 0;
        foreach ($fetched->posts as $dto) {
            $post = $this->findExisting($account, $dto);
            if ($post === null) {
                $new++;
            } elseif ($this->isRecent($post)) {
                $updated++;
            }
        }

        return [$new, $updated];
    }

    private function isRecent(Post $post): bool
    {
        return $post->posted_at !== null
            && $post->posted_at->gte(CarbonImmutable::now()->subDays(self::ENGAGEMENT_DAYS));
    }

    private function findExisting(SocialAccount $account, FetchedPost $dto): ?Post
    {
        $byId = Post::where('platform', $account->platform)
            ->where('external_id', $dto->externalId)
            ->first();
        if ($byId) {
            return $byId;
        }

        $key = PostUrlKey::from($dto->url);

        $candidates = Post::where('social_account_id', $account->id)
            ->whereNull('external_id')
            ->orderBy('id')
            ->get(['id', 'post_url']);
        $match = $candidates->first(fn (Post $p) => $p->post_url === $dto->url
                || ($key !== null && PostUrlKey::from($p->post_url) === $key));

        return $match ? Post::find($match->id) : null;
    }

    private function recordFailure(SocialAccount $account, FetchStatus $status, string $message, bool $dryRun): void
    {
        if ($dryRun) {
            return;
        }

        $account->discardChanges();
        $account->forceFill([
            'last_fetch_status' => $status,
            'last_fetch_error' => $message,
        ])->save();

        FetchLog::create([
            'social_account_id' => $account->id,
            'status' => $status,
            'new_posts' => 0,
            'updated_posts' => 0,
            'message' => $message,
            'fetched_at' => now(),
        ]);
    }

    private function sanitize(string $message): string
    {
        foreach (['services.youtube.key', 'services.instagram.token'] as $key) {
            $secret = config($key);
            if (is_string($secret) && $secret !== '') {
                $message = str_replace($secret, '***', $message);
            }
        }

        return mb_substr($message, 0, self::MESSAGE_MAX);
    }
}
