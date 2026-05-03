@extends('layouts.app')
@section('title', 'Tambah Akun — SocioWatch Jateng')
@section('page-title', 'Tambah Akun Sosmed')

@section('content')
<form method="POST" action="{{ route('accounts.store') }}" x-data="adminSubForm({{ json_encode([['full_name'=>'','alias'=>'','nik'=>'','phone'=>'','email'=>'','occupation'=>'','affiliation'=>'','notes'=>'']]) }})">
@csrf
<div class="max-w-4xl space-y-6">

    {{-- INFORMASI AKUN --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="font-semibold text-gray-700 mb-5 flex items-center gap-2">
            <i class="fas fa-share-alt text-indigo-500"></i> Informasi Akun Sosmed
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">Platform <span class="text-red-500">*</span></label>
                @php $pIcons=['instagram'=>['fa-instagram','text-pink-500'],'twitter'=>['fa-twitter','text-sky-500'],'facebook'=>['fa-facebook','text-blue-600'],'tiktok'=>['fa-tiktok','text-gray-800'],'youtube'=>['fa-youtube','text-red-600']]; @endphp
                <select name="platform" required class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none {{ $errors->has('platform') ? 'border-red-400' : '' }}">
                    <option value="">Pilih Platform...</option>
                    @foreach($platforms as $p)
                    <option value="{{ $p }}" {{ old('platform') == $p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
                    @endforeach
                </select>
                @error('platform') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">Username <span class="text-red-500">*</span></label>
                <input type="text" name="username" value="{{ old('username') }}" required placeholder="contoh: username_akun"
                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none {{ $errors->has('username') ? 'border-red-400' : '' }}">
                @error('username') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">Nama Tampilan <span class="text-red-500">*</span></label>
                <input type="text" name="display_name" value="{{ old('display_name') }}" required
                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none {{ $errors->has('display_name') ? 'border-red-400' : '' }}">
                @error('display_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">URL Profil</label>
                <input type="url" name="profile_url" value="{{ old('profile_url') }}" placeholder="https://..."
                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                @error('profile_url') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">Kategori <span class="text-red-500">*</span></label>
                <select name="category_id" required class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none {{ $errors->has('category_id') ? 'border-red-400' : '' }}">
                    <option value="">Pilih Kategori...</option>
                    @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
                @error('category_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">Kota/Kabupaten <span class="text-red-500">*</span></label>
                <select name="region_id" required class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none {{ $errors->has('region_id') ? 'border-red-400' : '' }}">
                    <option value="">Pilih Wilayah...</option>
                    @foreach($regions as $reg)
                    <option value="{{ $reg->id }}" {{ old('region_id') == $reg->id ? 'selected' : '' }}>{{ $reg->name }}</option>
                    @endforeach
                </select>
                @error('region_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">Followers</label>
                <input type="number" name="followers_count" value="{{ old('followers_count', 0) }}" min="0"
                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">Following</label>
                <input type="number" name="following_count" value="{{ old('following_count', 0) }}" min="0"
                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">Jumlah Postingan</label>
                <input type="number" name="post_count" value="{{ old('post_count', 0) }}" min="0"
                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
            </div>

            <div class="flex items-center gap-3 pt-2">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}
                           class="sr-only peer">
                    <div class="w-10 h-6 bg-gray-200 peer-checked:bg-indigo-600 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                </label>
                <span class="text-sm text-gray-600 font-medium">Akun Aktif</span>
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-600 mb-1">Bio</label>
                <textarea name="bio" rows="2" placeholder="Deskripsi singkat akun..."
                          class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none resize-none">{{ old('bio') }}</textarea>
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-600 mb-1">Catatan Internal</label>
                <textarea name="notes" rows="2" placeholder="Catatan monitoring..."
                          class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none resize-none">{{ old('notes') }}</textarea>
            </div>

        </div>
    </div>

    {{-- ADMIN / OPERATOR --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-5">
            <h3 class="font-semibold text-gray-700 flex items-center gap-2">
                <i class="fas fa-id-card text-indigo-500"></i> Data Admin/Operator Akun
            </h3>
            <button type="button" @click="addAdmin()"
                    class="text-sm bg-indigo-50 hover:bg-indigo-100 text-indigo-700 px-3 py-1.5 rounded-lg transition flex items-center gap-1.5">
                <i class="fas fa-plus text-xs"></i> Tambah Admin
            </button>
        </div>

        <template x-for="(admin, index) in admins" :key="index">
            <div class="border border-gray-100 rounded-xl p-4 mb-4 bg-gray-50 relative">
                <button type="button" @click="removeAdmin(index)"
                        class="absolute top-3 right-3 text-red-400 hover:text-red-600 hover:bg-red-50 w-7 h-7 rounded-lg flex items-center justify-center text-xs transition">
                    <i class="fas fa-times"></i>
                </button>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">
                    Admin #<span x-text="index + 1"></span>
                </p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Nama Lengkap <span class="text-red-500">*</span></label>
                        <input type="text" :name="`admins[${index}][full_name]`" x-model="admin.full_name"
                               class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-300 focus:outline-none bg-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Alias</label>
                        <input type="text" :name="`admins[${index}][alias]`" x-model="admin.alias"
                               class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-300 focus:outline-none bg-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">NIK</label>
                        <input type="text" :name="`admins[${index}][nik]`" x-model="admin.nik" maxlength="20"
                               class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-300 focus:outline-none bg-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">No. HP</label>
                        <input type="text" :name="`admins[${index}][phone]`" x-model="admin.phone"
                               class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-300 focus:outline-none bg-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Email</label>
                        <input type="email" :name="`admins[${index}][email]`" x-model="admin.email"
                               class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-300 focus:outline-none bg-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Pekerjaan</label>
                        <input type="text" :name="`admins[${index}][occupation]`" x-model="admin.occupation"
                               class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-300 focus:outline-none bg-white">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Afiliasi / Organisasi</label>
                        <input type="text" :name="`admins[${index}][affiliation]`" x-model="admin.affiliation"
                               class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-300 focus:outline-none bg-white">
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Catatan</label>
                        <textarea :name="`admins[${index}][notes]`" x-model="admin.notes" rows="2"
                                  class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-300 focus:outline-none resize-none bg-white"></textarea>
                    </div>
                </div>
            </div>
        </template>

        <div x-show="admins.length === 0" class="text-center py-6 text-gray-400 text-sm border border-dashed border-gray-200 rounded-xl">
            <i class="fas fa-user-plus text-2xl mb-2 block"></i>
            Belum ada admin. Klik "Tambah Admin" untuk menambah data operator akun.
        </div>
    </div>

    {{-- ACTIONS --}}
    <div class="flex items-center gap-3">
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium transition">
            <i class="fas fa-save mr-2"></i>Simpan Akun
        </button>
        <a href="{{ route('accounts.index') }}" class="border border-gray-200 text-gray-600 hover:bg-gray-50 px-6 py-2.5 rounded-lg text-sm font-medium transition">
            Batal
        </a>
    </div>

</div>
</form>
@endsection

@push('scripts')
<script>
function adminSubForm(initialAdmins) {
    return {
        admins: initialAdmins,
        addAdmin() {
            this.admins.push({ full_name:'', alias:'', nik:'', phone:'', email:'', occupation:'', affiliation:'', notes:'' });
        },
        removeAdmin(index) {
            this.admins.splice(index, 1);
        }
    };
}
</script>
@endpush
