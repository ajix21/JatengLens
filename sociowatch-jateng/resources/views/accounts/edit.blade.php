@extends('layouts.app')
@section('title', 'Edit Akun — SocioWatch Jateng')
@section('page-title', 'Edit Akun: @' . $account->username)

@section('breadcrumb')<a href="{{ route('accounts.index') }}" class="hover:text-indigo-600">Akun</a><span class="breadcrumb-sep">/</span><a href="{{ route('accounts.show', $account) }}" class="hover:text-indigo-600">{{ $account->display_name }}</a><span class="breadcrumb-sep">/</span><span class="text-slate-600">Edit</span>@endsection

@section('content')
@php
$existingAdmins = $account->admins->map(fn($a) => [
    'id'          => $a->id,
    'photo_url'   => $a->photo_url,
    'preview_url' => $a->photo_url,
    'photo_error' => null,
    'full_name'   => $a->full_name,
    'alias'       => $a->alias,
    'nik'         => $a->nik,
    'no_kk'       => $a->no_kk,
    'status'      => $a->status,
    'agama'       => $a->agama,
    'ttl'         => $a->ttl,
    'occupation'  => $a->occupation,
    'alamat'      => $a->alamat,
    'phone'       => $a->phone,
    'email'       => $a->email,
    'affiliation' => $a->affiliation,
    'notes'       => $a->notes,
])->toArray();
if (empty($existingAdmins)) {
    $existingAdmins = [['id'=>null,'photo_url'=>null,'preview_url'=>null,'photo_error'=>null,'full_name'=>'','alias'=>'','nik'=>'','no_kk'=>'','status'=>'','agama'=>'','ttl'=>'','occupation'=>'','alamat'=>'','phone'=>'','email'=>'','affiliation'=>'','notes'=>'']];
}
@endphp
<form method="POST" action="{{ route('accounts.update', $account) }}" enctype="multipart/form-data">
@csrf @method('PUT')
<div class="max-w-4xl space-y-6">
    @if($errors->any())
    <div role="alert" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">
        <p class="font-semibold mb-1 flex items-center gap-1.5"><i class="fas fa-exclamation-circle"></i> Terdapat beberapa kesalahan pengisian:</p>
        <ul class="list-disc list-inside space-y-0.5 text-xs">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    @if($errors->any())
    <div role="alert" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">
        <p class="font-semibold mb-1 flex items-center gap-1.5"><i class="fas fa-exclamation-circle"></i> Terdapat beberapa kesalahan pengisian:</p>
        <ul class="list-disc list-inside space-y-0.5 text-xs">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="font-semibold text-gray-700 mb-5 flex items-center gap-2">
            <i class="fas fa-share-alt text-indigo-500"></i> Informasi Akun Sosmed
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">Platform <span class="text-red-500">*</span></label>
                <select name="platform" required class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none {{ $errors->has('platform') ? 'border-red-400' : '' }}">
                    @foreach($platforms as $p)
                    <option value="{{ $p }}" {{ old('platform', $account->platform) == $p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
                    @endforeach
                </select>
                @error('platform') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">Username <span class="text-red-500">*</span></label>
                <input type="text" name="username" value="{{ old('username', $account->username) }}" required
                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none {{ $errors->has('username') ? 'border-red-400' : '' }}">
                @error('username') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">Nama Tampilan <span class="text-red-500">*</span></label>
                <input type="text" name="display_name" value="{{ old('display_name', $account->display_name) }}" required
                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none {{ $errors->has('display_name') ? 'border-red-400' : '' }}">
                @error('display_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">URL Profil</label>
                <input type="url" name="profile_url" value="{{ old('profile_url', $account->profile_url) }}"
                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none {{ $errors->has('profile_url') ? 'border-red-400' : '' }}">
                @error('profile_url') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">Kategori <span class="text-red-500">*</span></label>
                <select name="category_id" required class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none {{ $errors->has('category_id') ? 'border-red-400' : '' }}">
                    @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ old('category_id', $account->category_id) == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
                @error('category_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">Kota/Kabupaten <span class="text-red-500">*</span></label>
                <select name="region_id" required class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none {{ $errors->has('region_id') ? 'border-red-400' : '' }}">
                    @foreach($regions as $reg)
                    <option value="{{ $reg->id }}" {{ old('region_id', $account->region_id) == $reg->id ? 'selected' : '' }}>{{ $reg->name }}</option>
                    @endforeach
                </select>
                @error('region_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">Followers</label>
                <input type="number" name="followers_count" value="{{ old('followers_count', $account->followers_count) }}" min="0"
                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none {{ $errors->has('followers_count') ? 'border-red-400' : '' }}">
                @error('followers_count') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">Following</label>
                <input type="number" name="following_count" value="{{ old('following_count', $account->following_count) }}" min="0"
                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none {{ $errors->has('following_count') ? 'border-red-400' : '' }}">
                @error('following_count') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">Jumlah Postingan</label>
                <input type="number" name="post_count" value="{{ old('post_count', $account->post_count) }}" min="0"
                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none {{ $errors->has('post_count') ? 'border-red-400' : '' }}">
                @error('post_count') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="flex items-center gap-3 pt-2">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $account->is_active) ? 'checked' : '' }}
                           class="sr-only peer">
                    <div class="w-10 h-6 bg-gray-200 peer-checked:bg-indigo-600 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                </label>
                <span class="text-sm text-gray-600 font-medium">Akun Aktif</span>
            </div>
            <div class="flex items-center gap-3 pt-2">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="auto_fetch" value="0">
                    <input type="checkbox" name="auto_fetch" value="1" {{ old('auto_fetch', $account->auto_fetch) ? 'checked' : '' }}
                           class="sr-only peer">
                    <div class="w-10 h-6 bg-gray-200 peer-checked:bg-indigo-600 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                </label>
                <span class="text-sm text-gray-600 font-medium">Ambil otomatis (YouTube/Instagram)</span>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-600 mb-1">Bio</label>
                <textarea name="bio" rows="2" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none resize-none {{ $errors->has('bio') ? 'border-red-400' : '' }}">{{ old('bio', $account->bio) }}</textarea>
                @error('bio') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-600 mb-1">Catatan Internal</label>
                <textarea name="notes" rows="2" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none resize-none {{ $errors->has('notes') ? 'border-red-400' : '' }}">{{ old('notes', $account->notes) }}</textarea>
                @error('notes') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    {{-- ADMIN SUB-FORM (100% CSP-safe Vanilla JS) --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-5">
            <h3 class="font-semibold text-gray-700 flex items-center gap-2">
                <i class="fas fa-id-card text-indigo-500"></i> Data Admin/Operator Akun
            </h3>
            <button type="button" id="btnAddAdmin"
                    class="text-sm bg-indigo-50 hover:bg-indigo-100 text-indigo-700 px-3 py-1.5 rounded-lg transition flex items-center gap-1.5">
                <i class="fas fa-plus text-xs"></i> Tambah Admin
            </button>
        </div>
        <div id="adminSubFormList"></div>
        <div id="adminSubFormEmpty" class="hidden text-center py-6 text-gray-400 text-sm border-2 border-dashed border-gray-200 rounded-xl">
            Belum ada data admin. Klik "Tambah Admin" untuk menambahkan.
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium transition">
            <i class="fas fa-save mr-2"></i>Simpan Perubahan
        </button>
        <a href="{{ route('accounts.show', $account) }}" class="border border-gray-200 text-gray-600 hover:bg-gray-50 px-6 py-2.5 rounded-lg text-sm font-medium transition">Batal</a>
    </div>
</div>
</form>
@endsection
@push('scripts')
<script src="{{ asset('vendor/admin-subform.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    initAdminSubForm({
        listId: 'adminSubFormList',
        addBtnId: 'btnAddAdmin',
        emptyId: 'adminSubFormEmpty',
        initialAdmins: @js(old('admins', $existingAdmins))
    });
});
</script>
@endpush