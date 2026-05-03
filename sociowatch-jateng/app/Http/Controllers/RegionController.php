<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Region;
use App\Models\SocialAccount;

class RegionController extends Controller
{
    public function index()
    {
        $regions = Region::withCount('socialAccounts')
            ->with(['socialAccounts.category:id,name,color'])
            ->orderBy('name')
            ->get()
            ->map(function ($region) {
                $dominantCategory = $region->socialAccounts
                    ->groupBy('category_id')
                    ->sortByDesc(fn ($grp) => $grp->count())
                    ->first()?->first()?->category;

                $region->dominant_category   = $dominantCategory;
                $region->total_followers     = $region->socialAccounts->sum('followers_count');
                return $region;
            });

        return view('regions.index', compact('regions'));
    }

    public function show(Region $region)
    {
        $region->load([
            'socialAccounts.category:id,name,color',
            'socialAccounts.admins:id,social_account_id,full_name',
        ]);

        $accounts = $region->socialAccounts;
        $stats = [
            'total'          => $accounts->count(),
            'total_followers'=> $accounts->sum('followers_count'),
            'by_platform'    => $accounts->groupBy('platform')->map->count(),
            'by_category'    => $accounts->groupBy('category_id')->map(function ($grp) {
                return ['name' => $grp->first()->category->name ?? '-', 'count' => $grp->count(), 'color' => $grp->first()->category->color ?? '#888'];
            })->values(),
        ];

        return view('regions.show', compact('region', 'accounts', 'stats'));
    }
}
