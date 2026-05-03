@extends('layouts.app')
@section('title', 'Kelola Keyword — SocioWatch Jateng')
@section('page-title', 'Kelola Keyword Pantauan')
@section('breadcrumb')<span class="text-slate-600">Keyword</span>@endsection

@section('content')
<div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

{{-- Left: list --}}
<div class="xl:col-span-2 space-y-4">

    {{-- Import errors --}}
    @if(session('import_errors'))
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
        <p class="text-sm font-semibold text-amber-800 mb-1">Beberapa baris dilewati:</p>
        <ul class="text-xs text-amber-700 list-disc list-inside">
            @foreach(session('import_errors') as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    {{-- Actions bar --}}
    <div class="flex flex-wrap items-center justify-between gap-2">
        <span class="text-sm text-slate-500">{{ $keywords->count() }} keyword terdaftar</span>
        <div class="flex gap-2">
            <form method="POST" action="{{ route('keywords.rescan') }}"
                  onsubmit="return confirm('Scan ulang semua postingan? Proses ini mungkin memakan waktu.')">
                @csrf
                <button type="submit"
                        class="flex items-center gap-1.5 text-sm border border-purple-200 text-purple-700 hover:bg-purple-50 px-3 py-1.5 rounded-lg transition">
                    <i class="fas fa-sync-alt text-xs"></i> Scan Ulang Semua Post
                </button>
            </form>

            <form method="POST" action="{{ route('keywords.batch-import') }}" enctype="multipart/form-data"
                  class="flex items-center gap-2">
                @csrf
                <input type="file" name="file" accept=".csv,.txt" class="text-xs text-gray-600">
                <button type="submit"
                        class="text-sm border border-gray-200 text-gray-600 hover:bg-gray-50 px-3 py-1.5 rounded-lg transition">
                    Import CSV
                </button>
            </form>
        </div>
    </div>

    {{-- Keyword table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                        <th class="px-4 py-3 text-left">Kata</th>
                        <th class="px-4 py-3 text-center">Kategori</th>
                        <th class="px-4 py-3 text-center">Postingan</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($keywords as $kw)
                    <tr class="hover:bg-gray-50 transition {{ !$kw->is_active ? 'opacity-50' : '' }}">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full flex-shrink-0" style="background:{{ $kw->color }}"></span>
                                <a href="{{ route('keywords.show', $kw) }}"
                                   class="font-medium text-gray-800 hover:text-indigo-600 transition">
                                    {{ $kw->word }}
                                </a>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @php $catColors = ['sensitif'=>'bg-red-100 text-red-700','negatif'=>'bg-orange-100 text-orange-700','netral'=>'bg-gray-100 text-gray-600','positif'=>'bg-green-100 text-green-700']; @endphp
                            <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $catColors[$kw->category] ?? 'bg-gray-100' }}">
                                {{ ucfirst($kw->category) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center text-gray-600 text-xs font-medium">
                            {{ number_format($kw->posts_count) }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <form method="POST" action="{{ route('keywords.toggle', $kw) }}">
                                @csrf
                                <button type="submit"
                                        class="text-xs px-2 py-0.5 rounded-full font-medium {{ $kw->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $kw->is_active ? 'Aktif' : 'Nonaktif' }}
                                </button>
                            </form>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-1">
                                <button onclick="openEdit({{ $kw->id }}, '{{ addslashes($kw->word) }}', '{{ $kw->category }}', '{{ $kw->color }}')"
                                        class="p-1.5 text-amber-500 hover:bg-amber-50 rounded-lg transition">
                                    <i class="fas fa-pen text-xs"></i>
                                </button>
                                <form method="POST" action="{{ route('keywords.destroy', $kw) }}" class="inline"
                                      onsubmit="return confirm('Hapus keyword ini?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="p-1.5 text-red-400 hover:bg-red-50 rounded-lg transition">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-gray-400 text-sm">
                            Belum ada keyword.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Right: add form --}}
<div class="space-y-4">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2">
            <i class="fas fa-plus-circle text-indigo-500"></i> Tambah Keyword
        </h3>
        <form method="POST" action="{{ route('keywords.store') }}" class="space-y-3">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Kata <span class="text-red-500">*</span></label>
                <input type="text" name="word" required placeholder="contoh: demo"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                @error('word')<p class="text-red-500 text-xs mt-0.5">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Kategori</label>
                <select name="category" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                    @foreach(['sensitif'=>'Sensitif','negatif'=>'Negatif','netral'=>'Netral','positif'=>'Positif'] as $v=>$l)
                    <option value="{{ $v }}">{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Warna</label>
                <input type="color" name="color" value="#6b7280"
                       class="w-full h-9 border border-gray-200 rounded-lg px-1 py-1 cursor-pointer">
            </div>
            <button type="submit"
                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2 rounded-lg transition">
                Tambah
            </button>
        </form>
    </div>

    {{-- Category legend --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h4 class="font-semibold text-gray-700 text-sm mb-3">Distribusi Kategori</h4>
        @foreach(['sensitif','negatif','netral','positif'] as $cat)
        @php $catCount = $keywords->where('category', $cat)->count(); @endphp
        <div class="flex items-center justify-between py-1.5">
            <span class="text-xs {{ ['sensitif'=>'text-red-600','negatif'=>'text-orange-600','netral'=>'text-gray-600','positif'=>'text-green-600'][$cat] }}">
                {{ ucfirst($cat) }}
            </span>
            <span class="text-xs font-bold text-gray-700">{{ $catCount }}</span>
        </div>
        @endforeach
    </div>
</div>

</div>

{{-- Edit modal --}}
<div id="editModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,.5)">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800">Edit Keyword</h3>
            <button onclick="document.getElementById('editModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 text-xl">×</button>
        </div>
        <form id="editForm" method="POST" action="">
            @csrf @method('PUT')
            <div class="px-6 py-4 space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Kata</label>
                    <input type="text" name="word" id="editWord" required
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Kategori</label>
                    <select name="category" id="editCategory" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                        @foreach(['sensitif','negatif','netral','positif'] as $c)
                        <option value="{{ $c }}">{{ ucfirst($c) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Warna</label>
                    <input type="color" name="color" id="editColor"
                           class="w-full h-9 border border-gray-200 rounded-lg px-1 py-1 cursor-pointer">
                </div>
            </div>
            <div class="flex gap-3 px-6 pb-5">
                <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')"
                        class="flex-1 border border-gray-200 text-gray-600 text-sm font-medium py-2 rounded-lg hover:bg-gray-50 transition">Batal</button>
                <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2 rounded-lg transition">Simpan</button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
function openEdit(id, word, category, color) {
    document.getElementById('editForm').action = '/keywords/' + id;
    document.getElementById('editWord').value = word;
    document.getElementById('editCategory').value = category;
    document.getElementById('editColor').value = color;
    document.getElementById('editModal').classList.remove('hidden');
}
</script>
@endpush
