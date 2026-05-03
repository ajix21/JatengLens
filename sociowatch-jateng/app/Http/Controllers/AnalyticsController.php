<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\FollowerSnapshot;
use App\Models\Region;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function index()
    {
        $now     = now();
        $day30   = $now->copy()->subDays(30);
        $day7    = $now->copy()->subDays(7);
        $day90   = $now->copy()->subDays(90);

        // Top 10 growth (30 days)
        $growthTop = $this->growthRankings($day30, 'desc', 10);
        $growthBot = $this->growthRankings($day30, 'asc',  10);

        // Aggregate followers by category (last 30 days)
        $categories = Category::withSum('socialAccounts as total_followers', 'followers_count')
            ->withCount('socialAccounts')
            ->get();

        // Regions top by followers
        $regionStats = Region::withSum('socialAccounts as total_followers', 'followers_count')
            ->withCount('socialAccounts')
            ->orderByDesc('total_followers')
            ->take(15)
            ->get();

        // 30-day aggregate line chart: total followers per day across all active accounts
        $dailyTrend = FollowerSnapshot::select(
                DB::raw('DATE(recorded_at) as date'),
                DB::raw('SUM(followers_count) as total')
            )
            ->where('recorded_at', '>=', $day30)
            ->groupBy(DB::raw('DATE(recorded_at)'))
            ->orderBy('date')
            ->get();

        // Activity heatmap: snapshot count per day (last 90 days)
        $heatmap = FollowerSnapshot::select(
                DB::raw('DATE(recorded_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->where('recorded_at', '>=', $day90)
            ->groupBy(DB::raw('DATE(recorded_at)'))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Summary stats
        $totalAccounts  = SocialAccount::where('is_active', true)->count();
        $totalFollowers = SocialAccount::where('is_active', true)->sum('followers_count');
        $totalAlerts7d  = \App\Models\Alert::where('triggered_at', '>=', $day7)->count();
        $snapshotCount  = FollowerSnapshot::where('recorded_at', '>=', $day30)->count();

        return view('analytics.index', compact(
            'growthTop', 'growthBot',
            'categories', 'regionStats', 'dailyTrend', 'heatmap',
            'totalAccounts', 'totalFollowers', 'totalAlerts7d', 'snapshotCount',
            'day30', 'day90'
        ));
    }

    private function growthRankings($since, string $direction, int $limit): \Illuminate\Support\Collection
    {
        $snapshotsOld = FollowerSnapshot::select('social_account_id', DB::raw('MIN(followers_count) as old_count'))
            ->where('recorded_at', '>=', $since)
            ->groupBy('social_account_id');

        return SocialAccount::with(['category', 'region'])
            ->where('is_active', true)
            ->joinSub($snapshotsOld, 'snap', fn($j) => $j->on('social_accounts.id', '=', 'snap.social_account_id'))
            ->selectRaw('social_accounts.*, snap.old_count,
                (social_accounts.followers_count - snap.old_count) as abs_change,
                CASE WHEN snap.old_count > 0
                     THEN ROUND((social_accounts.followers_count - snap.old_count) / snap.old_count * 100, 2)
                     ELSE 0
                END as pct_change')
            ->orderBy('pct_change', $direction)
            ->take($limit)
            ->get();
    }
}
