<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Region;
use App\Models\SocialAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MapController extends Controller
{
    // ──────────────────────────────────────────────
    //  GET /map  →  halaman peta
    // ──────────────────────────────────────────────
    public function index()
    {
        $categories   = Category::orderBy('name')->get();
        $regions      = Region::orderBy('name')->get();
        $platforms    = ['instagram', 'twitter', 'facebook', 'tiktok', 'youtube'];
        $maxFollowers = SocialAccount::max('followers_count') ?: 1000000;

        return view('map.index', compact('categories', 'regions', 'platforms', 'maxFollowers'));
    }

    // ──────────────────────────────────────────────
    //  GET /map/markers  →  JSON semua akun + koordinat
    //  Digunakan oleh Leaflet marker layer
    //  Query params: categories[], platforms[], region_id,
    //                min_followers, max_followers
    // ──────────────────────────────────────────────
    public function markers(Request $request): JsonResponse
    {
        $query = SocialAccount::with([
            'category:id,name,color',
            'region:id,name,latitude,longitude,geojson_key',
            'admins:id,social_account_id,full_name',
        ])->where('is_active', true);

        $this->applyFilters($query, $request);

        $markers = $query->get()->map(fn ($acc) => [
            'id'             => $acc->id,
            'platform'       => $acc->platform,
            'username'       => $acc->username,
            'display_name'   => $acc->display_name,
            'followers_count'=> (int) $acc->followers_count,
            'profile_url'    => $acc->profile_url,
            'category' => $acc->category ? [
                'id'    => $acc->category->id,
                'name'  => $acc->category->name,
                'color' => $acc->category->color,
            ] : null,
            'region' => $acc->region ? [
                'id'        => $acc->region->id,
                'name'      => $acc->region->name,
                'latitude'  => (float) $acc->region->latitude,
                'longitude' => (float) $acc->region->longitude,
                'geojson_key' => $acc->region->geojson_key,
            ] : null,
            'admins' => $acc->admins->pluck('full_name'),
        ]);

        return response()->json([
            'count'   => $markers->count(),
            'markers' => $markers,
        ]);
    }

    // ──────────────────────────────────────────────
    //  GET /map/choropleth  →  JSON agregasi per kota/kab
    //  Digunakan oleh Leaflet choropleth (GeoJSON) layer
    //  Query params: categories[], metric (accounts|followers)
    // ──────────────────────────────────────────────
    public function choropleth(Request $request): JsonResponse
    {
        $categoryIds = $request->input('categories', []);

        $regions = Region::with([
            'socialAccounts' => function ($q) use ($categoryIds) {
                $q->where('is_active', true);
                if (!empty($categoryIds)) {
                    $q->whereIn('category_id', $categoryIds);
                }
            },
            'socialAccounts.category:id,name,color',
        ])->get();

        $data = $regions->map(function ($region) {
            $accounts = $region->socialAccounts;

            $breakdown = $accounts
                ->groupBy('category_id')
                ->map(fn ($grp) => [
                    'name'  => $grp->first()->category->name ?? '-',
                    'count' => $grp->count(),
                    'color' => $grp->first()->category->color ?? '#888888',
                ])
                ->values();

            return [
                'id'                 => $region->id,
                'name'               => $region->name,
                'type'               => $region->type,
                'geojson_key'        => $region->geojson_key,
                'latitude'           => (float) $region->latitude,
                'longitude'          => (float) $region->longitude,
                'account_count'      => $accounts->count(),
                'total_followers'    => (int) $accounts->sum('followers_count'),
                'category_breakdown' => $breakdown,
            ];
        });

        return response()->json([
            'count'   => $data->count(),
            'regions' => $data,
        ]);
    }

    // ──────────────────────────────────────────────
    //  GET /map/region/{id}  →  JSON daftar akun di kota/kab tertentu
    //  Digunakan oleh panel detail setelah klik choropleth
    // ──────────────────────────────────────────────
    public function regionAccounts(int $id): JsonResponse
    {
        $region = Region::findOrFail($id);

        $accounts = SocialAccount::with([
            'category:id,name,color',
            'admins:id,social_account_id,full_name',
        ])
        ->where('region_id', $id)
        ->where('is_active', true)
        ->orderByDesc('followers_count')
        ->get()
        ->map(fn ($acc) => [
            'id'             => $acc->id,
            'platform'       => $acc->platform,
            'username'       => $acc->username,
            'display_name'   => $acc->display_name,
            'followers_count'=> (int) $acc->followers_count,
            'profile_url'    => $acc->profile_url,
            'category' => $acc->category ? [
                'id'    => $acc->category->id,
                'name'  => $acc->category->name,
                'color' => $acc->category->color,
            ] : null,
            'admins' => $acc->admins->pluck('full_name'),
        ]);

        return response()->json([
            'region'   => [
                'id'        => $region->id,
                'name'      => $region->name,
                'type'      => $region->type,
                'latitude'  => (float) $region->latitude,
                'longitude' => (float) $region->longitude,
            ],
            'count'    => $accounts->count(),
            'accounts' => $accounts,
        ]);
    }

    // ──────────────────────────────────────────────
    //  Private helper — terapkan filter request ke query
    // ──────────────────────────────────────────────
    private function applyFilters($query, Request $request): void
    {
        if ($cats = $request->input('categories', [])) {
            $query->whereIn('category_id', (array) $cats);
        }
        if ($plats = $request->input('platforms', [])) {
            $query->whereIn('platform', (array) $plats);
        }
        if ($regionId = $request->input('region_id')) {
            $query->where('region_id', $regionId);
        }
        if (($min = (int) $request->input('min_followers', 0)) > 0) {
            $query->where('followers_count', '>=', $min);
        }
        if ($max = $request->input('max_followers')) {
            $query->where('followers_count', '<=', (int) $max);
        }
    }
}
