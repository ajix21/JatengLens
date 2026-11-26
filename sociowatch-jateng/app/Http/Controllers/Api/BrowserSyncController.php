<?php

namespace App\Http\Controllers\Api;

use App\Enums\FetchStatus;
use App\Http\Controllers\Controller;
use App\Models\FetchLog;
use App\Models\SocialAccount;
use App\Services\SocialFetch\FetchedAccount;
use App\Services\SocialFetch\FetchedPost;
use App\Services\SocialFetch\SocialFetchService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BrowserSyncController extends Controller
{
    public function __construct(
        private readonly SocialFetchService $fetchService,
    ) {}

    /**
     * Get list of Instagram & Facebook accounts that need browser-side sync.
     * By default returns Instagram accounts marked as unsupported/extension-managed
     * PLUS all active Facebook accounts.
     * Pass ?all=1 to include all active Instagram accounts as well.
     * Pass ?platform=instagram or ?platform=facebook to filter by platform.
     */
    public function unsupportedAccounts(Request $request): JsonResponse
    {
        $platformFilter = strtolower(trim((string) $request->query('platform', '')));
        $platforms = in_array($platformFilter, ['instagram', 'facebook'], true)
            ? [$platformFilter]
            : ['instagram', 'facebook'];

        $query = SocialAccount::whereIn('platform', $platforms)
            ->where('is_active', true)
            ->with(['category:id,name', 'region:id,name']);

        if (! $request->boolean('all')) {
            $query->where(function ($q) {
                $q->where('platform', 'facebook')
                  ->orWhere(function ($ig) {
                      $ig->where('platform', 'instagram')
                         ->where(function ($sub) {
                             $sub->where('last_fetch_status', FetchStatus::Unsupported)
                                 ->orWhereNull('last_fetch_status')
                                 ->orWhere('auto_fetch', false)
                                 ->orWhereHas('fetchLogs', fn ($fl) => $fl->where('message', 'like', '%Browser Extension%'));
                         });
                  });
            });
        }

        // Urutkan stale-first (paling lama belum di-sync / belum pernah di-sync) + batas aman default 45 akun/sesi
        // agar akun IG/FB browser tidak terkena checkpoint/ban.
        $limit = $request->has('limit')
            ? max(1, min(150, (int) $request->query('limit')))
            : ($request->boolean('all') ? 150 : 45);

        $totalEligible = (clone $query)->count();

        $accounts = $query
            ->orderByRaw('last_fetched_at IS NULL DESC')
            ->orderBy('last_fetched_at')
            ->orderBy('platform')
            ->orderBy('username')
            ->limit($limit)
            ->get()
            ->map(fn($acc) => [
            'id' => $acc->id,
            'platform' => $acc->platform,
            'username' => $acc->username,
            'display_name' => $acc->display_name,
            'profile_url' => $acc->profile_url ?: ($acc->platform === 'facebook'
                ? "https://www.facebook.com/{$acc->username}"
                : "https://www.instagram.com/{$acc->username}/"),
            'followers_count' => $acc->followers_count,
            'following_count' => $acc->following_count,
            'post_count' => $acc->post_count,
            'last_fetched_at' => $acc->last_fetched_at?->toIso8601String(),
            'last_fetch_status' => $acc->last_fetch_status?->value,
            'category' => $acc->category?->name,
            'region' => $acc->region?->name,
        ]);

        return response()->json([
            'success' => true,
            'count' => $accounts->count(),
            'total_eligible' => $totalEligible,
            'limit' => $limit,
            'accounts' => $accounts,
        ]);
    }

    /**
     * Ingest Instagram profile & post data collected from browser extension.
     */
    public function syncInstagram(Request $request): JsonResponse
    {
        return $this->ingestPlatformPayload($request, 'instagram', 'Browser Extension');
    }

    /**
     * Ingest Facebook profile & post data collected from browser extension.
     */
    public function syncFacebook(Request $request): JsonResponse
    {
        return $this->ingestPlatformPayload($request, 'facebook', 'Browser Extension (Facebook)');
    }

    private function ingestPlatformPayload(Request $request, string $platform, string $sourceLabel): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'nullable|integer',
            'username' => 'required_without:account_id|string|max:150',
            'profile' => 'nullable|array',
            'profile.external_id' => 'nullable|string|max:100',
            'profile.followers_count' => 'nullable|integer|min:0',
            'profile.following_count' => 'nullable|integer|min:0',
            'profile.post_count' => 'nullable|integer|min:0',
            'profile.bio' => 'nullable|string',
            'profile.profile_pic_url' => 'nullable|string',
            'posts' => 'nullable|array',
            'posts.*.external_id' => 'nullable|string|max:150',
            'posts.*.url' => 'required|string|url',
            'posts.*.content' => 'nullable|string',
            'posts.*.media_type' => 'nullable|string|in:text,image,video,reel,story,carousel',
            'posts.*.likes_count' => 'nullable|integer|min:0',
            'posts.*.comments_count' => 'nullable|integer|min:0',
            'posts.*.views_count' => 'nullable|integer|min:0',
            'posts.*.posted_at' => 'required|string',
            'posts.*.media_url' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi data gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $account = null;
        if ($request->filled('account_id')) {
            $account = SocialAccount::where('id', $request->input('account_id'))
                ->where('platform', $platform)
                ->first();
        }

        if (! $account && $request->filled('username')) {
            $username = ltrim(trim((string) $request->input('username')), '@');
            $account = SocialAccount::where('platform', $platform)
                ->where('username', $username)
                ->first();
        }

        if (! $account) {
            $platformLabel = ucfirst($platform);
            return response()->json([
                'success' => false,
                'message' => "Akun {$platformLabel} tidak ditemukan di database SocioWatch.",
            ], 404);
        }

        $profile = $request->input('profile', []);
        $postsInput = $request->input('posts', []);
        $prefix = $platform === 'facebook' ? 'fb_' : 'ig_';

        $fetchedPosts = [];
        foreach ($postsInput as $p) {
            $url = $p['url'];
            $extId = $p['external_id'] ?? null;
            if (empty($extId)) {
                if ($platform === 'instagram' && preg_match('#/(?:p|reel)/([^/?]+)#', $url, $m)) {
                    $extId = 'ig_' . $m[1];
                } else {
                    $extId = $prefix . md5($url);
                }
            }

            $mediaType = match (strtolower((string) ($p['media_type'] ?? ($platform === 'facebook' ? 'text' : 'image')))) {
                'reel', 'video' => 'video',
                'story' => 'story',
                'carousel', 'image' => 'image',
                default => 'text',
            };

            try {
                $postedAt = is_numeric($p['posted_at'])
                    ? CarbonImmutable::createFromTimestamp((int) $p['posted_at'])
                    : CarbonImmutable::parse($p['posted_at']);
            } catch (\Throwable) {
                $postedAt = CarbonImmutable::now();
            }

            $fetchedPosts[] = new FetchedPost(
                externalId: (string) $extId,
                url: $url,
                content: (string) ($p['content'] ?? ''),
                mediaType: $mediaType,
                likes: (int) ($p['likes_count'] ?? 0),
                comments: (int) ($p['comments_count'] ?? 0),
                views: (int) ($p['views_count'] ?? 0),
                postedAt: $postedAt,
                mediaUrl: $p['media_url'] ?? null,
            );
        }

        $fetchedAccount = new FetchedAccount(
            externalId: (string) ($profile['external_id'] ?? $account->external_id ?? ($prefix . $account->username)),
            followers: (int) ($profile['followers_count'] ?? $account->followers_count ?? 0),
            following: isset($profile['following_count']) ? (int) $profile['following_count'] : $account->following_count,
            postCount: (int) ($profile['post_count'] ?? $account->post_count ?? count($fetchedPosts)),
            posts: $fetchedPosts,
        );

        if (! empty($profile['bio']) && empty($account->bio)) {
            $account->bio = $profile['bio'];
            $account->save();
        }

        $result = $this->fetchService->ingestBrowserSync($account, $fetchedAccount, $sourceLabel);

        $account->refresh();

        return response()->json([
            'success' => true,
            'message' => "Sinkronisasi berhasil: {$result->newPosts} postingan baru, {$result->updatedPosts} postingan diperbarui.",
            'account' => [
                'id' => $account->id,
                'platform' => $account->platform,
                'username' => $account->username,
                'display_name' => $account->display_name,
                'followers_count' => $account->followers_count,
                'following_count' => $account->following_count,
                'post_count' => $account->post_count,
                'last_fetch_status' => $account->last_fetch_status?->value,
                'last_fetched_at' => $account->last_fetched_at?->toIso8601String(),
            ],
            'result' => [
                'status' => $result->status->value,
                'new_posts' => $result->newPosts,
                'updated_posts' => $result->updatedPosts,
            ],
        ]);
    }

    /**
     * Report account fetch error or not_found status discovered by the browser extension.
     */
    public function reportStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'nullable|integer',
            'platform' => 'nullable|string|in:instagram,facebook',
            'username' => 'required_without:account_id|string|max:150',
            'status' => 'required|string|in:not_found,error',
            'message' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi data gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $platforms = $request->filled('platform')
            ? [$request->input('platform')]
            : ['instagram', 'facebook'];

        $account = null;
        if ($request->filled('account_id')) {
            $account = SocialAccount::where('id', $request->input('account_id'))
                ->whereIn('platform', $platforms)
                ->first();
        }
        if (! $account && $request->filled('username')) {
            $username = ltrim(trim((string) $request->input('username')), '@');
            $account = SocialAccount::whereIn('platform', $platforms)
                ->where('username', $username)
                ->first();
        }

        if (! $account) {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak ditemukan di database SocioWatch.',
            ], 404);
        }

        $statusEnum = $request->input('status') === 'not_found'
            ? FetchStatus::NotFound
            : FetchStatus::Error;
        $message = trim((string) ($request->input('message') ?: 'Tidak ditemukan di Instagram (via Ekstensi Browser)'));

        $updates = [
            'last_fetch_status' => $statusEnum,
            'last_fetch_error' => $message,
        ];

        if ($statusEnum === FetchStatus::NotFound) {
            $updates['auto_fetch'] = false;
            $existingNotes = (string) ($account->notes ?? '');
            if (stripos($existingNotes, '[TIDAK DITEMUKAN]') === false) {
                $updates['notes'] = trim(($existingNotes !== '' ? $existingNotes . ' | ' : '') . '[TIDAK DITEMUKAN] Dilaporkan tidak ada di Instagram oleh Ekstensi Browser');
            }
        }

        $account->forceFill($updates)->save();

        FetchLog::create([
            'social_account_id' => $account->id,
            'status' => $statusEnum,
            'new_posts' => 0,
            'updated_posts' => 0,
            'message' => "Browser Extension: {$message}",
            'fetched_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Status akun @{$account->username} diperbarui menjadi {$statusEnum->value}.",
            'account' => [
                'id' => $account->id,
                'username' => $account->username,
                'last_fetch_status' => $account->last_fetch_status?->value,
                'auto_fetch' => $account->auto_fetch,
            ],
        ]);
    }
}
