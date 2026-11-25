<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LogsActivity;
use App\Models\AccountAdmin;
use App\Models\SocialAccount;
use App\Services\SecureImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    use LogsActivity;

    public function __construct(
        private readonly SecureImageUploadService $imageUploadService
    ) {}
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
            'photo'             => 'nullable|file|image|mimetypes:image/jpeg,image/png,image/webp|mimes:jpg,jpeg,png,webp|max:2048|dimensions:min_width=50,min_height=50,max_width=4096,max_height=4096',
            'alias'             => 'nullable|string|max:255',
            'nik'               => 'nullable|string|max:20',
            'no_kk'             => 'nullable|string|max:20',
            'status'            => 'nullable|string|max:100',
            'agama'             => 'nullable|string|max:50',
            'ttl'               => 'nullable|string|max:255',
            'occupation'        => 'nullable|string|max:255',
            'alamat'            => 'nullable|string',
            'phone'             => 'nullable|string|max:30',
            'email'             => 'nullable|email|max:255',
            'affiliation'       => 'nullable|string|max:255',
            'notes'             => 'nullable|string',
        ]);

        if ($request->hasFile('photo')) {
            $data['photo'] = $this->imageUploadService->storeSafeImage($request->file('photo'), 'admin-photos', 'photo');
        } else {
            unset($data['photo']);
        }

        $admin = AccountAdmin::create($data);
        $this->logActivity('create', 'AccountAdmin', $admin->id);
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
            'photo'             => 'nullable|file|image|mimetypes:image/jpeg,image/png,image/webp|mimes:jpg,jpeg,png,webp|max:2048|dimensions:min_width=50,min_height=50,max_width=4096,max_height=4096',
            'remove_photo'      => 'nullable|boolean',
            'alias'             => 'nullable|string|max:255',
            'nik'               => 'nullable|string|max:20',
            'no_kk'             => 'nullable|string|max:20',
            'status'            => 'nullable|string|max:100',
            'agama'             => 'nullable|string|max:50',
            'ttl'               => 'nullable|string|max:255',
            'occupation'        => 'nullable|string|max:255',
            'alamat'            => 'nullable|string',
            'phone'             => 'nullable|string|max:30',
            'email'             => 'nullable|email|max:255',
            'affiliation'       => 'nullable|string|max:255',
            'notes'             => 'nullable|string',
        ]);

        unset($data['remove_photo'], $data['photo']);

        if ($request->hasFile('photo')) {
            $newPhotoPath = $this->imageUploadService->storeSafeImage($request->file('photo'), 'admin-photos', 'photo');
            if ($admin->photo) {
                Storage::disk('public')->delete($admin->photo);
            }
            $data['photo'] = $newPhotoPath;
        } elseif ($request->boolean('remove_photo') && $admin->photo) {
            Storage::disk('public')->delete($admin->photo);
            $data['photo'] = null;
        }

        $admin->update($data);
        $this->logActivity('update', 'AccountAdmin', $admin->id);
        return redirect()->route('admins.index')
            ->with('success', 'Data admin berhasil diperbarui.');
    }

    public function destroy(AccountAdmin $admin)
    {
        $id = $admin->id;
        if ($admin->photo) {
            Storage::disk('public')->delete($admin->photo);
        }
        $admin->delete();
        $this->logActivity('delete', 'AccountAdmin', $id);
        return redirect()->route('admins.index')
            ->with('success', 'Data admin berhasil dihapus.');
    }
}
