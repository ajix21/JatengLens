@extends('layouts.app')
@section('title', 'Tambah Admin — SocioWatch Jateng')
@section('page-title', 'Tambah Data Admin / Operator')
@section('breadcrumb')<a href="{{ route('admins.index') }}" class="hover:text-indigo-600">Data Admin / Operator</a><span class="breadcrumb-sep">/</span><span class="text-slate-600">Tambah</span>@endsection

@section('content')
<div class="max-w-2xl">
<form method="POST" action="{{ route('admins.store') }}" enctype="multipart/form-data">
@csrf
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
    <h3 class="font-semibold text-gray-700 flex items-center gap-2">
        <i class="fas fa-id-card text-indigo-500"></i> Data Admin/Operator
    </h3>

    <div x-data="{
        previewUrl: null,
        clientError: null,
        isDragging: false,
        fileName: null,
        applyFile(file, inputEl) {
            this.clientError = null;
            if (!file) { this.previewUrl = null; this.fileName = null; return; }
            const allowed = ['image/jpeg', 'image/png', 'image/webp'];
            if (!allowed.includes(file.type)) {
                this.clientError = 'Hanya file gambar JPG, PNG, atau WEBP yang diizinkan.';
                if (inputEl) inputEl.value = '';
                this.previewUrl = null;
                this.fileName = null;
                return;
            }
            if (file.size > 2 * 1024 * 1024) {
                this.clientError = 'Ukuran foto melebihi batas maksimal 2 MB.';
                if (inputEl) inputEl.value = '';
                this.previewUrl = null;
                this.fileName = null;
                return;
            }
            this.fileName = file.name;
            this.previewUrl = URL.createObjectURL(file);
        },
        handleFile(event) {
            this.applyFile(event.target.files[0], event.target);
        },
        handleDrop(event) {
            this.isDragging = false;
            const file = event.dataTransfer?.files?.[0];
            const inputEl = this.$refs.photoInput;
            if (!file || !inputEl) return;
            const dt = new DataTransfer();
            dt.items.add(file);
            inputEl.files = dt.files;
            this.applyFile(file, inputEl);
        }
    }">
        <label class="block text-sm font-medium text-gray-600 mb-1.5">Foto Admin</label>
        <div class="flex items-start gap-4">
            <div class="w-16 h-16 rounded-xl border border-gray-200 bg-gray-50 flex items-center justify-center overflow-hidden flex-shrink-0">
                <template x-if="previewUrl">
                    <img :src="previewUrl" alt="Preview foto admin" class="w-full h-full object-cover">
                </template>
                <template x-if="!previewUrl">
                    <i class="fas fa-user text-gray-300 text-2xl"></i>
                </template>
            </div>
            <div class="flex-1">
                <div @dragover.prevent="isDragging = true"
                     @dragleave.prevent="isDragging = false"
                     @drop.prevent="handleDrop($event)"
                     @click="$refs.photoInput.click()"
                     :class="isDragging ? 'border-indigo-500 bg-indigo-50/70' : 'border-gray-300 bg-gray-50/50 hover:border-indigo-400 hover:bg-indigo-50/30'"
                     class="border-2 border-dashed rounded-xl px-4 py-3 text-center cursor-pointer transition">
                    <input type="file" name="photo" x-ref="photoInput" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                           @change="handleFile($event)"
                           class="hidden">
                    <div class="flex items-center justify-center gap-2 text-xs text-gray-600">
                        <i class="fas fa-cloud-upload-alt text-indigo-500 text-base"></i>
                        <span><span class="font-semibold text-indigo-600">Klik untuk pilih</span> atau <strong>drag &amp; drop</strong> foto di sini</span>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-0.5" x-text="fileName ? ('File terpilih: ' + fileName) : 'JPG, PNG, atau WEBP • Maks. 2 MB (otomatis disanitasi)'"></p>
                </div>
                <p x-show="clientError" x-text="clientError" class="text-red-500 text-xs mt-1"></p>
                @error('photo') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-600 mb-1">Akun Sosmed <span class="text-red-500">*</span></label>
        <select name="social_account_id" required
                class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none {{ $errors->has('social_account_id') ? 'border-red-400' : '' }}">
            <option value="">Pilih Akun...</option>
            @foreach($accounts as $acc)
            <option value="{{ $acc->id }}" {{ old('social_account_id') == $acc->id ? 'selected' : '' }}>
                {{ '@' . $acc->username }} ({{ $acc->platform }}) — {{ $acc->region?->name }}
            </option>
            @endforeach
        </select>
        @error('social_account_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-600 mb-1">Nama Lengkap <span class="text-red-500">*</span></label>
            <input type="text" name="full_name" value="{{ old('full_name') }}" required
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none {{ $errors->has('full_name') ? 'border-red-400' : '' }}">
            @error('full_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Alias / Nama Panggilan</label>
            <input type="text" name="alias" value="{{ old('alias') }}"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
            @error('alias') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">NIK</label>
            <input type="text" name="nik" value="{{ old('nik') }}" maxlength="20" inputmode="numeric" placeholder="16 digit NIK"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
            @error('nik') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">No. KK (Kartu Keluarga)</label>
            <input type="text" name="no_kk" value="{{ old('no_kk') }}" maxlength="20" inputmode="numeric" placeholder="16 digit No. KK"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
            @error('no_kk') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">No. HP / Telepon</label>
            <input type="tel" name="phone" value="{{ old('phone') }}"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
            @error('phone') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Email</label>
            <input type="email" name="email" value="{{ old('email') }}"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
            @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Status</label>
            <input type="text" name="status" value="{{ old('status') }}" placeholder="Misal: Lajang / Menikah"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
            @error('status') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Agama</label>
            <input type="text" name="agama" value="{{ old('agama') }}" placeholder="Misal: Islam"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
            @error('agama') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Tempat, Tanggal Lahir (TTL)</label>
            <input type="text" name="ttl" value="{{ old('ttl') }}" placeholder="Misal: Semarang, 12 Mei 1998"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
            @error('ttl') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Pekerjaan</label>
            <input type="text" name="occupation" value="{{ old('occupation') }}"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
            @error('occupation') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-600 mb-1">Alamat Domisili / Tempat Tinggal</label>
            <input type="text" name="alamat" value="{{ old('alamat') }}" placeholder="Alamat lengkap / domisili"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
            @error('alamat') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Afiliasi / Organisasi</label>
            <input type="text" name="affiliation" value="{{ old('affiliation') }}"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
            @error('affiliation') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-600 mb-1">Catatan</label>
            <textarea name="notes" rows="3" placeholder="Catatan tambahan tentang admin ini..."
                      class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none resize-none">{{ old('notes') }}</textarea>
            @error('notes') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="flex gap-3 pt-2">
        <button type="submit"
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-lg text-sm font-medium transition">
            <i class="fas fa-save mr-2"></i>Simpan
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
