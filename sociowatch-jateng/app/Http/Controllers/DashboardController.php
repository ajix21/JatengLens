<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Region;
use App\Models\SocialAccount;

class DashboardController extends Controller
{
    public function index()
    {
        $totalAccounts    = SocialAccount::count();
        $totalFollowers   = SocialAccount::sum('followers_count');
        $regionsCovered   = SocialAccount::distinct('region_id')->count('region_id');
        $activeCategories = Category::whereHas('socialAccounts')->count();

        $accountsPerCategory = Category::withCount('socialAccounts')
            ->orderByDesc('social_accounts_count')
            ->get();

        $topRegions = Region::withCount('socialAccounts')
            ->orderByDesc('social_accounts_count')
            ->take(10)
            ->get();

        $mapAccounts = SocialAccount::with([
            'region:id,name,latitude,longitude',
            'category:id,name,color',
        ])->where('is_active', true)->get([
            'id', 'username', 'display_name', 'platform',
            'followers_count', 'category_id', 'region_id',
        ]);

        $latestAccounts = SocialAccount::with([
            'category:id,name,color',
            'region:id,name',
        ])->latest()->take(5)->get();

        return view('dashboard.index', compact(
            'totalAccounts', 'totalFollowers', 'regionsCovered', 'activeCategories',
            'accountsPerCategory', 'topRegions', 'mapAccounts', 'latestAccounts'
        ));
    }
}
