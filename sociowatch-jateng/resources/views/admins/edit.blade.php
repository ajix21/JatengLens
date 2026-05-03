@extends('layouts.app')
@section('title', 'Edit Admin — SocioWatch Jateng')
@section('page-title', 'Edit Data Admin: {{ $admin->full_name }}')

@section('content')
<div class="max-w-2xl">
<form method="POST" action="{{ route('admins.update', $admin) }}">
@csrf @method('PUT')
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
    <h3 class="font-semibold text-gray-700 flex items-center gap-2">
        <i class="fas fa-id-card text-indigo-500"></i> Edit Data Admin/Operator
    </h3>

    <div>
        <label class="block text-sm font-medium text-gray-600 mb-1">Akun Sosmed <span class="text-red-500">*</span></label>
        <select name="social_account_id" required
                class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
            <option value="">Pilih Akun...</option>
            @foreach($accounts as $acc)
            <option value="{{ $acc->id }}" {{ old('social_account_id', $admin->social_account_id) == $acc->id ? 'selected' : '' }}>
                @{{ $acc->username }} ({{ $acc->platform }}) — {{ $acc->region?->name }}
            </option>
            @endforeach
        </select>
        @error('social_account_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-600 mb-1">Nama Lengkap <span class="text-red-500">*</span></label>
            <input type="text" name="full_name" value="{{ old('full_name', $admin->full_name) }}" required
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
            @error('full_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Alias</label>
            <input type="text" name="alias" value="{{ old('alias', $admin->alias) }}"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">NIK</label>
            <input type="text" name="nik" value="{{ old('nik', $admin->nik) }}" maxlength="20"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">No. HP / Telepon</label>
            <input type="text" name="phone" value="{{ old('phone', $admin->phone) }}"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Email</label>
            <input type="email" name="email" value="{{ old('email', $admin->email) }}"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Pekerjaan</label>
            <input type="text" name="occupation" value="{{ old('occupation', $admin->occupation) }}"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Afiliasi / Organisasi</label>
            <input type="text" name="affiliation" value="{{ old('affiliation', $admin->affiliation) }}"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-600 mb-1">Catatan</label>
            <textarea name="notes" rows="3"
                      class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none resize-none">{{ old('notes', $admin->notes) }}</textarea>
        </div>
    </div>

    <div class="flex gap-3 pt-2">
        <button type="submit"
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-lg text-sm font-medium transition">
            <i class="fas fa-save mr-2"></i>Simpan Perubahan
        </button>
        <a href="{{ route('admins.index') }}"
           class="border border-gray-200 text-gray-600 hover:bg-gray-50 px-5 py-2.5 rounded-lg text-sm transition">
            Batal
        </a>
    </div>
</div>
</form>
</div>
@endsection
