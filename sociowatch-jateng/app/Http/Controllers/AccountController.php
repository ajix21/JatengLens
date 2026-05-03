<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Region;
use App\Models\SocialAccount;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    private array $platforms = ['instagram', 'twitter', 'facebook', 'tiktok', 'youtube'];

    private array $rules = [
        'platform'        => 'required|in:instagram,twitter,facebook,tiktok,youtube',
        'username'        => 'required|string|max:255',
        'display_name'    => 'required|string|max:255',
        'profile_url'     => 'nullable|url|max:500',
        'profile_picture' => 'nullable|url|max:500',
        'followers_count' => 'nullable|integer|min:0',
        'following_count' => 'nullable|integer|min:0',
        'post_count'      => 'nullable|integer|min:0',
        'bio'             => 'nullable|string',
        'category_id'     => 'required|exists:categories,id',
        'region_id'       => 'required|exists:regions,id',
        'is_active'       => 'boolean',
        'notes'           => 'nullable|string',
        'admins'                      => 'nullable|array',
        'admins.*.full_name'          => 'required_with:admins.*|string|max:255',
        'admins.*.alias'              => 'nullable|string|max:255',
        'admins.*.nik'                => 'nullable|string|max:20',
        'admins.*.phone'              => 'nullable|string|max:30',
        'admins.*.email'              => 'nullable|email|max:255',
        'admins.*.occupation'         => 'nullable|string|max:255',
        'admins.*.affiliation'        => 'nullable|string|max:255',
        'admins.*.notes'              => 'nullable|string',
    ];

    public function index(Request $request)
    {
        $query = SocialAccount::with(['category:id,name,color', 'region:id,name']);

        if ($request->search) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('username', 'like', "%{$s}%")
                  ->orWhere('display_name', 'like', "%{$s}%");
            });
        }
        if ($request->platform)    { $query->where('platform', $request->platform); }
        if ($request->category_id) { $query->where('category_id', $request->category_id); }
        if ($request->region_id)   { $query->where('region_id', $request->region_id); }
        if ($request->status !== null && $request->status !== '') {
            $query->where('is_active', (bool) $request->status);
        }

        $accounts   = $query->latest()->paginate(15)->withQueryString();
        $categories = Category::orderBy('name')->get();
        $regions    = Region::orderBy('name')->get();
        $platforms  = $this->platforms;

        return view('accounts.index', compact('accounts', 'categories', 'regions', 'platforms'));
    }

    public function create()
    {
        $categories = Category::orderBy('name')->get();
        $regions    = Region::orderBy('name')->get();
        $platforms  = $this->platforms;
        return view('accounts.create', compact('categories', 'regions', 'platforms'));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules);
        $data['is_active']       = $request->boolean('is_active', true);
        $data['followers_count'] = $data['followers_count'] ?? 0;
        $data['following_count'] = $data['following_count'] ?? 0;
        $data['post_count']      = $data['post_count'] ?? 0;

        $account = SocialAccount::create($data);

        foreach ($request->input('admins', []) as $adminData) {
            if (!empty($adminData['full_name'])) {
                $account->admins()->create($adminData);
            }
        }

        return redirect()->route('accounts.show', $account)
            ->with('success', 'Akun berhasil ditambahkan.');
    }

    public function show(SocialAccount $account)
    {
        $account->load(['category', 'region', 'admins', 'activityLogs' => function ($q) {
            $q->latest('logged_at')->take(20);
        }]);
        return view('accounts.show', compact('account'));
    }

    public function edit(SocialAccount $account)
    {
        $account->load('admins');
        $categories = Category::orderBy('name')->get();
        $regions    = Region::orderBy('name')->get();
        $platforms  = $this->platforms;
        return view('accounts.edit', compact('account', 'categories', 'regions', 'platforms'));
    }

    public function update(Request $request, SocialAccount $account)
    {
        $data = $request->validate($this->rules);
        $data['is_active']       = $request->boolean('is_active', true);
        $data['followers_count'] = $data['followers_count'] ?? 0;
        $data['following_count'] = $data['following_count'] ?? 0;
        $data['post_count']      = $data['post_count'] ?? 0;

        // Log followers change
        if ($account->followers_count !== (int) $data['followers_count']) {
            $account->activityLogs()->create([
                'activity_type' => 'followers_update',
                'old_value'     => (string) $account->followers_count,
                'new_value'     => (string) $data['followers_count'],
                'logged_at'     => now(),
            ]);
        }

        $account->update($data);
        $account->admins()->delete();
        foreach ($request->input('admins', []) as $adminData) {
            if (!empty($adminData['full_name'])) {
                $account->admins()->create($adminData);
            }
        }

        return redirect()->route('accounts.show', $account)
            ->with('success', 'Data akun berhasil diperbarui.');
    }

    public function destroy(SocialAccount $account)
    {
        $account->delete();
        return redirect()->route('accounts.index')
            ->with('success', 'Akun berhasil dihapus.');
    }
}
