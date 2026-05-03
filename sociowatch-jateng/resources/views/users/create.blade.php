@extends('layouts.app')

@section('title', 'Tambah User — SocioWatch Jateng')
@section('page-title', 'Tambah User')
@section('breadcrumb')
    <a href="{{ route('users.index') }}" class="hover:text-indigo-600">Manajemen User</a>
    <span class="breadcrumb-sep">/</span>
    <span class="text-slate-500">Tambah</span>
@endsection

@section('content')
<div class="max-w-lg">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 space-y-5">
        <form method="POST" action="{{ route('users.store') }}">
            @csrf

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nama Lengkap</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none @error('name') border-red-400 @enderror">
                @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                       class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none @error('email') border-red-400 @enderror">
                @error('email')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Role</label>
                <select name="role" required
                        class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                    <option value="viewer"     @selected(old('role')=='viewer')>Viewer</option>
                    <option value="admin"      @selected(old('role')=='admin')>Admin</option>
                    <option value="superadmin" @selected(old('role')=='superadmin')>Superadmin</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                <input type="password" name="password" required
                       class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none @error('password') border-red-400 @enderror">
                @error('password')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Konfirmasi Password</label>
                <input type="password" name="password_confirmation" required
                       class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none">
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_active" value="1" id="is_active" checked class="rounded" style="accent-color:#4F46E5">
                <label for="is_active" class="text-sm text-slate-700">Aktifkan user</label>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit"
                        class="px-5 py-2.5 rounded-xl text-white text-sm font-semibold"
                        style="background:linear-gradient(135deg,#4F46E5,#7C3AED)">
                    Simpan User
                </button>
                <a href="{{ route('users.index') }}"
                   class="px-5 py-2.5 rounded-xl text-sm font-medium text-slate-600 border border-slate-200 hover:bg-slate-50">
                    Batal
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
