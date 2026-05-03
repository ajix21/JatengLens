<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\FollowerSnapshot;
use App\Models\Keyword;
use App\Models\Post;
use App\Models\Region;
use App\Models\SocialAccount;
use Illuminate\Http\Request;
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

        // ── Content analytics ──
        $contentData = $this->contentAnalytics($day30, $day7);

        return view('analytics.index', compact(
            'growthTop', 'growthBot',
            'categories', 'regionStats', 'dailyTrend', 'heatmap',
            'totalAccounts', 'totalFollowers', 'totalAlerts7d', 'snapshotCount',
            'day30', 'day90', 'contentData'
        ));
    }

    private function contentAnalytics($day30, $day7): array
    {
        // Keyword cloud: word => [count, category, color]
        $keywordCloud = DB::table('post_keyword_pivot')
            ->join('posts', 'posts.id', '=', 'post_keyword_pivot.post_id')
            ->join('keywords', 'keywords.id', '=', 'post_keyword_pivot.keyword_id')
            ->where('keywords.is_active', true)
            ->where('posts.posted_at', '>=', $day30)
            ->selectRaw('keywords.word, keywords.category, keywords.color, SUM(post_keyword_pivot.occurrence_count) as total')
            ->groupBy('keywords.id', 'keywords.word', 'keywords.category', 'keywords.color')
            ->orderByDesc('total')
            ->take(60)
            ->get();

        // Keyword ranking (30 days)
        $keywordRanking30 = DB::table('post_keyword_pivot')
            ->join('posts', 'posts.id', '=', 'post_keyword_pivot.post_id')
            ->join('keywords', 'keywords.id', '=', 'post_keyword_pivot.keyword_id')
            ->where('keywords.is_active', true)
            ->where('posts.posted_at', '>=', $day30)
            ->selectRaw('keywords.id, keywords.word, keywords.category, keywords.color,
                          SUM(post_keyword_pivot.occurrence_count) as total_occurrences,
                          COUNT(DISTINCT posts.id) as post_count,
                          COUNT(DISTINCT posts.social_account_id) as account_count')
            ->groupBy('keywords.id', 'keywords.word', 'keywords.category', 'keywords.color')
            ->orderByDesc('total_occurrences')
            ->take(20)
            ->get();

        // Same for 7 days (for trend comparison)
        $keywordRanking7 = DB::table('post_keyword_pivot')
            ->join('posts', 'posts.id', '=', 'post_keyword_pivot.post_id')
            ->join('keywords', 'keywords.id', '=', 'post_keyword_pivot.keyword_id')
            ->where('keywords.is_active', true)
            ->where('posts.posted_at', '>=', $day7)
            ->selectRaw('keywords.id, SUM(post_keyword_pivot.occurrence_count) as total')
            ->groupBy('keywords.id')
            ->get()->keyBy('id');

        // Sentiment distribution (dominant keyword category per post)
        $sentiment = DB::table('post_keyword_pivot as pkp')
            ->join('keywords as k', 'k.id', '=', 'pkp.keyword_id')
            ->join('posts as p', 'p.id', '=', 'pkp.post_id')
            ->where('p.posted_at', '>=', $day30)
            ->selectRaw('p.id as post_id, k.category, SUM(pkp.occurrence_count) as score')
            ->groupBy('p.id', 'k.category')
            ->get()
            ->groupBy('post_id')
            ->map(fn($rows) => $rows->sortByDesc('score')->first()?->category ?? 'netral')
            ->countBy()
            ->toArray();

        // Top engagement posts (30 days)
        $topEngagement = Post::with(['socialAccount.category', 'socialAccount.region'])
            ->where('posted_at', '>=', $day30)
            ->selectRaw('*, (likes_count + comments_count + shares_count + views_count) as total_engagement')
            ->orderByDesc('total_engagement')
            ->take(10)
            ->get();

        // Keyword trend (daily for top 5 keywords, last 30 days)
        $topKeywordIds = $keywordRanking30->take(5)->pluck('id');
        $keywordTrend  = [];
        if ($topKeywordIds->isNotEmpty()) {
            $keywordTrend = DB::table('post_keyword_pivot')
                ->join('posts', 'posts.id', '=', 'post_keyword_pivot.post_id')
                ->whereIn('post_keyword_pivot.keyword_id', $topKeywordIds)
                ->where('posts.posted_at', '>=', $day30)
                ->selectRaw('post_keyword_pivot.keyword_id, DATE(posts.posted_at) as date, SUM(post_keyword_pivot.occurrence_count) as total')
                ->groupBy('post_keyword_pivot.keyword_id', DB::raw('DATE(posts.posted_at)'))
                ->orderBy('date')
                ->get()
                ->groupBy('keyword_id');
        }

        return compact(
            'keywordCloud', 'keywordRanking30', 'keywordRanking7',
            'sentiment', 'topEngagement', 'keywordTrend', 'topKeywordIds'
        );
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
