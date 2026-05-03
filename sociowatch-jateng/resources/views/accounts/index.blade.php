@extends('layouts.app')
@section('title', 'Manajemen Akun — SocioWatch Jateng')
@section('page-title', 'Manajemen Akun Sosmed')

@section('content')
<div class="space-y-5">

    {{-- TOOLBAR --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <form method="GET" action="{{ route('accounts.index') }}" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-gray-500 mb-1">Cari</label>
                <div class="relative">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Username / nama..."
                           class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Platform</label>
                <select name="platform" class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                    <option value="">Semua</option>
                    @foreach($platforms as $p)
                    <option value="{{ $p }}" {{ request('platform') == $p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Kategori</label>
                <select name="category_id" class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                    <option value="">Semua</option>
                    @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Wilayah</label>
                <select name="region_id" class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                    <option value="">Semua</option>
                    @foreach($regions as $reg)
                    <option value="{{ $reg->id }}" {{ request('region_id') == $reg->id ? 'selected' : '' }}>{{ $reg->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                <select name="status" class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                    <option value="">Semua</option>
                    <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Aktif</option>
                    <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Nonaktif</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-4 py-2 rounded-lg transition">
                    <i class="fas fa-search mr-1"></i>Filter
                </button>
                <a href="{{ route('accounts.index') }}" class="border border-gray-200 hover:bg-gray-50 text-gray-600 text-sm px-4 py-2 rounded-lg transition">
                    Reset
                </a>
            </div>
            <a href="{{ route('accounts.create') }}" class="ml-auto bg-emerald-600 hover:bg-emerald-700 text-white text-sm px-4 py-2 rounded-lg transition flex items-center gap-2">
                <i class="fas fa-plus"></i> Tambah Akun
            </a>
        </form>
    </div>

    {{-- TABLE --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wide">Platform / Akun</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wide">Kategori</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wide">Wilayah</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wide">Followers</th>
                        <th class="text-center px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wide">Status</th>
                        <th class="text-center px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wide">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($accounts as $acc)
                    @php
                    $pIcon = ['instagram'=>['fa-instagram','text-pink-500'],'twitter'=>['fa-twitter','text-sky-500'],'facebook'=>['fa-facebook','text-blue-600'],'tiktok'=>['fa-tiktok','text-gray-700'],'youtube'=>['fa-youtube','text-red-600']][$acc->platform] ?? ['fa-globe','text-gray-400'];
                    @endphp
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
                                    <i class="fab {{ $pIcon[0] }} {{ $pIcon[1] }} text-lg"></i>
                                </div>
                                <div class="min-w-0">
                                    <a href="{{ route('accounts.show', $acc) }}" class="font-medium text-gray-800 hover:text-indigo-600 truncate block">{{ $acc->display_name }}</a>
                                    <span class="text-xs text-gray-400">@{{ $acc->username }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium text-white"
                                  style="background:{{ $acc->category->color ?? '#888' }}">
                                {{ $acc->category->name ?? '-' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $acc->region->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-right font-medium text-gray-700">
                            {{ number_format($acc->followers_count) }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($acc->is_active)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Aktif
                            </span>
                            @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
                                <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> Nonaktif
                            </span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-1">
                                <a href="{{ route('map.index', ['region_id' => $acc->region_id]) }}"
                                   title="Lihat di Peta"
                                   class="p-1.5 text-blue-500 hover:bg-blue-50 rounded-lg transition">
                                    <i class="fas fa-map-marker-alt text-xs"></i>
                                </a>
                                <a href="{{ route('accounts.show', $acc) }}" title="Detail"
                                   class="p-1.5 text-indigo-500 hover:bg-indigo-50 rounded-lg transition">
                                    <i class="fas fa-eye text-xs"></i>
                                </a>
                                <a href="{{ route('accounts.edit', $acc) }}" title="Edit"
                                   class="p-1.5 text-amber-500 hover:bg-amber-50 rounded-lg transition">
                                    <i class="fas fa-pen text-xs"></i>
                                </a>
                                <form method="POST" action="{{ route('accounts.destroy', $acc) }}" class="inline"
                                      onsubmit="return confirm('Hapus akun ini?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" title="Hapus"
                                            class="p-1.5 text-red-400 hover:bg-red-50 rounded-lg transition">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-gray-400">
                            <i class="fas fa-inbox text-3xl mb-3 block"></i>
                            Tidak ada data akun yang ditemukan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($accounts->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $accounts->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
