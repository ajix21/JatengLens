<?php

namespace App\Http\Controllers;

use App\Models\AccountAdmin;
use App\Models\SocialAccount;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        $query = AccountAdmin::with(['socialAccount.category:id,name,color', 'socialAccount.region:id,name']);

        if ($request->search) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('full_name', 'like', "%{$s}%")
                  ->orWhere('alias', 'like', "%{$s}%")
                  ->orWhere('nik', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%");
            });
        }

        $admins = $query->latest()->paginate(20)->withQueryString();
        return view('admins.index', compact('admins'));
    }

    public function create()
    {
        $accounts = SocialAccount::with('region:id,name')->orderBy('username')->get();
        return view('admins.create', compact('accounts'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'social_account_id' => 'required|exists:social_accounts,id',
            'full_name'         => 'required|string|max:255',
            'alias'             => 'nullable|string|max:255',
            'nik'               => 'nullable|string|max:20',
            'phone'             => 'nullable|string|max:30',
            'email'             => 'nullable|email|max:255',
            'occupation'        => 'nullable|string|max:255',
            'affiliation'       => 'nullable|string|max:255',
            'notes'             => 'nullable|string',
        ]);
        AccountAdmin::create($data);
        return redirect()->route('admins.index')
            ->with('success', 'Data admin berhasil ditambahkan.');
    }

    public function edit(AccountAdmin $admin)
    {
        $accounts = SocialAccount::with('region:id,name')->orderBy('username')->get();
        return view('admins.edit', compact('admin', 'accounts'));
    }

    public function update(Request $request, AccountAdmin $admin)
    {
        $data = $request->validate([
            'social_account_id' => 'required|exists:social_accounts,id',
            'full_name'         => 'required|string|max:255',
            'alias'             => 'nullable|string|max:255',
            'nik'               => 'nullable|string|max:20',
            'phone'             => 'nullable|string|max:30',
            'email'             => 'nullable|email|max:255',
            'occupation'        => 'nullable|string|max:255',
            'affiliation'       => 'nullable|string|max:255',
            'notes'             => 'nullable|string',
        ]);
        $admin->update($data);
        return redirect()->route('admins.index')
            ->with('success', 'Data admin berhasil diperbarui.');
    }

    public function destroy(AccountAdmin $admin)
    {
        $admin->delete();
        return redirect()->route('admins.index')
            ->with('success', 'Data admin berhasil dihapus.');
    }
}
