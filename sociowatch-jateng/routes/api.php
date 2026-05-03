<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Alert;
use App\Models\Region;
use App\Models\SocialAccount;

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', fn(Request $request) => $request->user());

    // Accounts list
    Route::get('/accounts', function () {
        return SocialAccount::with(['category', 'region'])
            ->where('is_active', true)
            ->orderBy('followers_count', 'desc')
            ->get()
            ->map(fn($a) => [
                'id'              => $a->id,
                'platform'        => $a->platform,
                'username'        => $a->username,
                'display_name'    => $a->display_name,
                'profile_url'     => $a->profile_url,
                'followers_count' => $a->followers_count,
                'following_count' => $a->following_count,
                'post_count'      => $a->post_count,
                'category'        => $a->category?->name,
                'region'          => $a->region?->name,
                'updated_at'      => $a->updated_at,
            ]);
    });

    // Regions list
    Route::get('/regions', function () {
        return Region::withCount('socialAccounts')
            ->with(['socialAccounts' => fn($q) => $q->select('id', 'region_id', 'followers_count')])
            ->orderBy('name')
            ->get()
            ->map(fn($r) => [
                'id'              => $r->id,
                'name'            => $r->name,
                'type'            => $r->type,
                'total_accounts'  => $r->social_accounts_count,
                'total_followers' => $r->socialAccounts->sum('followers_count'),
            ]);
    });

    // Unread alerts
    Route::get('/alerts/unread', function () {
        $count  = Alert::where('is_read', false)->count();
        $alerts = Alert::with('socialAccount:id,username,display_name')
            ->where('is_read', false)
            ->orderByDesc('triggered_at')
            ->take(5)
            ->get()
            ->map(fn($a) => [
                'id'                 => $a->id,
                'alert_type'         => $a->alert_type,
                'message'            => $a->message,
                'is_read'            => $a->is_read,
                'triggered_at'       => $a->triggered_at,
                'triggered_at_human' => $a->triggered_at->diffForHumans(),
                'account_username'   => $a->socialAccount?->username,
            ]);
        return response()->json(['count' => $count, 'alerts' => $alerts]);
    });

    // Update stats for an account
    Route::post('/accounts/{account}/update-stats', function (Request $request, SocialAccount $account) {
        $data = $request->validate([
            'followers_count' => 'required|integer|min:0',
            'following_count' => 'nullable|integer|min:0',
            'post_count'      => 'nullable|integer|min:0',
        ]);

        $account->update($data);

        \App\Models\FollowerSnapshot::create([
            'social_account_id' => $account->id,
            'followers_count'   => $account->followers_count,
            'following_count'   => $account->following_count,
            'post_count'        => $account->post_count,
            'recorded_at'       => now(),
        ]);

        return response()->json([
            'success'         => true,
            'followers_count' => $account->followers_count,
            'updated_at'      => $account->updated_at,
        ]);
    });
});
