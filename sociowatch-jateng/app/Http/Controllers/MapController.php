<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Region;
use App\Models\SocialAccount;
use Illuminate\Http\Request;

class MapController extends Controller
{
    public function index()
    {
        $categories  = Category::all();
        $regions     = Region::orderBy('name')->get();
        $platforms   = ['instagram', 'twitter', 'facebook', 'tiktok', 'youtube'];
        $maxFollowers = SocialAccount::max('followers_count') ?: 1000000;

        return view('map.index', compact('categories', 'regions', 'platforms', 'maxFollowers'));
    }

    public function data(Request $request)
    {
        $categoryIds = $request->input('categories', []);
        $platformIds = $request->input('platforms', []);
        $regionId    = $request->input('region_id');
        $minFollowers = (int) $request->input('min_followers', 0);
        $maxFollowers = $request->input('max_followers');

        $query = SocialAccount::with([
            'category:id,name,color',
            'region:id,name,latitude,longitude,geojson_key',
            'admins:id,social_account_id,full_name',
        ])->where('is_active', true);

        if (!empty($categoryIds)) {
            $query->whereIn('category_id', $categoryIds);
        }
        if (!empty($platformIds)) {
            $query->whereIn('platform', $platformIds);
        }
        if ($regionId) {
            $query->where('region_id', $regionId);
        }
        if ($minFollowers > 0) {
            $query->where('followers_count', '>=', $minFollowers);
        }
        if ($maxFollowers !== null) {
            $query->where('followers_count', '<=', (int) $maxFollowers);
        }

        $accounts = $query->get()->map(function ($acc) {
            return [
                'id'             => $acc->id,
                'platform'       => $acc->platform,
                'username'       => $acc->username,
                'display_name'   => $acc->display_name,
                'followers_count'=> $acc->followers_count,
                'profile_url'    => $acc->profile_url,
                'category'       => $acc->category,
                'region'         => $acc->region,
                'admins'         => $acc->admins->pluck('full_name'),
            ];
        });

        $regionStats = Region::with(['socialAccounts' => function ($q) use ($categoryIds) {
            $q->where('is_active', true);
            if (!empty($categoryIds)) {
                $q->whereIn('category_id', $categoryIds);
            }
        }, 'socialAccounts.category:id,name,color'])->get()->map(function ($region) {
            $accounts = $region->socialAccounts;
            $breakdown = $accounts->groupBy('category_id')->map(function ($grp) {
                return [
                    'name'  => $grp->first()->category->name ?? '-',
                    'count' => $grp->count(),
                    'color' => $grp->first()->category->color ?? '#888',
                ];
            })->values();

            return [
                'id'                 => $region->id,
                'name'               => $region->name,
                'geojson_key'        => $region->geojson_key,
                'account_count'      => $accounts->count(),
                'total_followers'    => $accounts->sum('followers_count'),
                'category_breakdown' => $breakdown,
            ];
        });

        return response()->json([
            'accounts'     => $accounts,
            'region_stats' => $regionStats,
        ]);
    }
}
