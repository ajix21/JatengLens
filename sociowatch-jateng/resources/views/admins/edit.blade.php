@extends('layouts.app')
@section('title', 'Edit Admin — SocioWatch Jateng')
@section('page-title', 'Edit Data Admin: ' . $admin->full_name)
@section('breadcrumb')<a href="{{ route('admins.index') }}" class="hover:text-indigo-600">Data Admin / Operator</a><span class="breadcrumb-sep">/</span><span class="text-slate-600">Edit</span>@endsection

@section('content')
<div class="max-w-2xl">
<form method="POST" action="{{ route('admins.update', $admin) }}" enctype="multipart/form-data">
@csrf @method('PUT')
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
    <h3 class="font-semibold text-gray-700 flex items-center gap-2">
        <i class="fas fa-id-card text-indigo-500"></i> Edit Data Admin/Operator
    </h3>

    <div x-data="{
        originalUrl: @js($admin->photo_url),
        previewUrl: @js($admin->photo_url),
        clientError: null,
        handleFile(event) {
            this.clientError = null;
            const file = event.target.files[0];
            if (!file) { this.previewUrl = this.originalUrl; return; }
            const allowed = ['image/jpeg', 'image/png', 'image/webp'];
            if (!allowed.includes(file.type)) {
                this.clientError = 'Hanya file gambar JPG, PNG, atau WEBP yang diizinkan.';
                event.target.value = '';
                this.previewUrl = this.originalUrl;
                return;
            }
            if (file.size > 2 * 1024 * 1024) {
                this.clientError = 'Ukuran foto melebihi batas maksimal 2 MB.';
                event.target.value = '';
                this.previewUrl = this.originalUrl;
                return;
            }
            this.previewUrl = URL.createObjectURL(file);
        }
    }">
        <label class="block text-sm font-medium text-gray-600 mb-1">Foto Admin</label>
        <div class="flex items-center gap-4">
            <div class="w-16 h-16 rounded-xl border border-gray-200 bg-gray-50 flex items-center justify-center overflow-hidden flex-shrink-0">
                <template x-if="previewUrl">
                    <img :src="previewUrl" alt="Preview foto admin" class="w-full h-full object-cover">
                </template>
                <template x-if="!previewUrl">
                    <i class="fas fa-user text-gray-300 text-2xl"></i>
                </template>
            </div>
            <div class="flex-1 space-y-1.5">
                <input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                       @change="handleFile($event)"
                       class="block w-full text-xs text-gray-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer">
                <p class="text-xs text-gray-400">Format JPG, PNG, atau WEBP (maks. 2 MB, otomatis disanitasi ulang). Kosongkan jika tidak ingin mengganti foto.</p>
                <p x-show="clientError" x-text="clientError" class="text-red-500 text-xs"></p>
                @if($admin->photo_url)
                <label class="inline-flex items-center gap-1.5 text-xs text-red-600 cursor-pointer">
                    <input type="checkbox" name="remove_photo" value="1" class="rounded border-gray-300 text-red-600 focus:ring-red-400">
                    <span>Hapus foto saat ini</span>
                </label>
                @endif
                @error('photo') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-600 mb-1">Akun Sosmed <span class="text-red-500">*</span></label>
        <select name="social_account_id" required
                class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
            <option value="">Pilih Akun...</option>
            @foreach($accounts as $acc)
            <option value="{{ $acc->id }}" {{ old('social_account_id', $admin->social_account_id) == $acc->id ? 'selected' : '' }}>
                {{ '@' . $acc->username }} ({{ $acc->platform }}) — {{ $acc->region?->name }}
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
            @error('alias') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">NIK</label>
            <input type="text" name="nik" value="{{ old('nik', $admin->nik) }}" maxlength="20" inputmode="numeric" placeholder="16 digit NIK"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
            @error('nik') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">No. KK (Kartu Keluarga)</label>
            <input type="text" name="no_kk" value="{{ old('no_kk', $admin->no_kk) }}" maxlength="20" inputmode="numeric" placeholder="16 digit No. KK"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
            @error('no_kk') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">No. HP / Telepon</label>
            <input type="tel" name="phone" value="{{ old('phone', $admin->phone) }}"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
            @error('phone') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Email</label>
            <input type="email" name="email" value="{{ old('email', $admin->email) }}"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
            @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Status</label>
            <input type="text" name="status" value="{{ old('status', $admin->status) }}" placeholder="Misal: Lajang / Menikah"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
            @error('status') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Agama</label>
            <input type="text" name="agama" value="{{ old('agama', $admin->agama) }}" placeholder="Misal: Islam"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
            @error('agama') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Tempat, Tanggal Lahir (TTL)</label>
            <input type="text" name="ttl" value="{{ old('ttl', $admin->ttl) }}" placeholder="Misal: Semarang, 12 Mei 1998"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
            @error('ttl') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Pekerjaan</label>
            <input type="text" name="occupation" value="{{ old('occupation', $admin->occupation) }}"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
            @error('occupation') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-600 mb-1">Alamat Domisili / Tempat Tinggal</label>
            <input type="text" name="alamat" value="{{ old('alamat', $admin->alamat) }}" placeholder="Alamat lengkap / domisili"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
            @error('alamat') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Afiliasi / Organisasi</label>
            <input type="text" name="affiliation" value="{{ old('affiliation', $admin->affiliation) }}"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
            @error('affiliation') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-600 mb-1">Catatan</label>
            <textarea name="notes" rows="3"
                      class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none resize-none">{{ old('notes', $admin->notes) }}</textarea>
            @error('notes') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
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
