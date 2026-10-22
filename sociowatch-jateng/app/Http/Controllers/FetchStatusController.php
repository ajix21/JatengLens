<?php

namespace App\Http\Controllers;

use App\Enums\FetchStatus;
use App\Models\FetchLog;
use App\Models\SocialAccount;
use App\Services\SocialFetch\InstagramFetcher;
use App\Services\SocialFetch\YouTubeFetcher;
use Carbon\Carbon;

class FetchStatusController extends Controller
{
    public function index()
    {
        $platforms = [];
        foreach ([
            'youtube'   => ['YouTube', app(YouTubeFetcher::class)],
            'instagram' => ['Instagram', app(InstagramFetcher::class)],
        ] as $key => [$label, $fetcher]) {
            $statuses = SocialAccount::where('platform', $key)
                ->whereNotNull('last_fetch_status')
                ->pluck('last_fetch_status');
            $lastRun = FetchLog::whereHas('socialAccount', fn ($q) => $q->where('platform', $key))->max('fetched_at');

            $platforms[$key] = [
                'label'      => $label,
                'configured' => $fetcher->isConfigured(),
                'last_run'   => $lastRun ? Carbon::parse($lastRun) : null,
                'ok'         => $statuses->filter(fn ($s) => $s === FetchStatus::Ok)->count(),
                'failed'     => $statuses->filter(fn ($s) => $s !== FetchStatus::Ok)->count(),
            ];
        }

        $failed = SocialAccount::whereIn('platform', ['youtube', 'instagram'])
            ->whereNotNull('last_fetch_status')
            ->where('last_fetch_status', '!=', FetchStatus::Ok->value)
            ->orderByDesc('last_fetched_at')
            ->get();

        $igAuthError = $failed->contains(
            fn ($a) => $a->platform === 'instagram' && $a->last_fetch_status === FetchStatus::AuthError
        );

        $igDaysLeft = null;
        $expires = config('services.instagram.token_expires_at');
        if ($expires) {
            try {
                $igDaysLeft = (int) now()->startOfDay()->diffInDays(Carbon::parse($expires)->startOfDay(), false);
            } catch (\Throwable) {
                $igDaysLeft = null;
            }
        }

        return view('settings.auto-fetch', compact('platforms', 'failed', 'igAuthError', 'igDaysLeft'));
    }
}
