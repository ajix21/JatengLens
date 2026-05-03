@extends('layouts.app')
@section('title', 'Admin Akun — SocioWatch Jateng')
@section('page-title', 'Data Admin / Operator Akun')

@section('content')
<div class="space-y-5">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <form method="GET" action="{{ route('admins.index') }}" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-gray-500 mb-1">Cari</label>
                <div class="relative">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Nama, alias, NIK, HP..."
                           class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                </div>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-4 py-2 rounded-lg transition">
                    <i class="fas fa-search mr-1"></i>Cari
                </button>
                <a href="{{ route('admins.index') }}" class="border border-gray-200 text-gray-600 text-sm px-4 py-2 rounded-lg hover:bg-gray-50 transition">Reset</a>
            </div>
            <a href="{{ route('admins.create') }}" class="ml-auto bg-emerald-600 hover:bg-emerald-700 text-white text-sm px-4 py-2 rounded-lg transition flex items-center gap-2">
                <i class="fas fa-plus"></i> Tambah Admin
            </a>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wide">Nama / Identitas</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wide">Kontak</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wide">Pekerjaan / Afiliasi</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wide">Akun Sosmed</th>
                        <th class="text-center px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wide">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($admins as $admin)
                    @php
                    $pIcon = ['instagram'=>['fa-instagram','text-pink-500'],'twitter'=>['fa-twitter','text-sky-500'],'facebook'=>['fa-facebook','text-blue-600'],'tiktok'=>['fa-tiktok','text-gray-700'],'youtube'=>['fa-youtube','text-red-600']][$admin->socialAccount?->platform ?? ''] ?? ['fa-globe','text-gray-400'];
                    @endphp
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold text-sm flex-shrink-0">
                                    {{ strtoupper(substr($admin->full_name, 0, 1)) }}
                                </div>
                                <div>
                                    <p class="font-medium text-gray-800">{{ $admin->full_name }}</p>
                                    @if($admin->alias)<p class="text-xs text-gray-400">{{ $admin->alias }}</p>@endif
                                    @if($admin->nik)<p class="text-xs text-gray-400">NIK: {{ $admin->nik }}</p>@endif
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-600 text-xs space-y-0.5">
                            @if($admin->phone)<div><i class="fas fa-phone w-3 mr-1 text-gray-400"></i>{{ $admin->phone }}</div>@endif
                            @if($admin->email)<div><i class="fas fa-envelope w-3 mr-1 text-gray-400"></i>{{ $admin->email }}</div>@endif
                        </td>
                        <td class="px-4 py-3 text-gray-600 text-xs space-y-0.5">
                            @if($admin->occupation)<div>{{ $admin->occupation }}</div>@endif
                            @if($admin->affiliation)<div class="text-gray-400">{{ $admin->affiliation }}</div>@endif
                        </td>
                        <td class="px-4 py-3">
                            @if($admin->socialAccount)
                            <a href="{{ route('accounts.show', $admin->socialAccount) }}" class="flex items-center gap-2 hover:text-indigo-600 group">
                                <i class="fab {{ $pIcon[0] }} {{ $pIcon[1] }} text-base"></i>
                                <div>
                                    <p class="text-xs font-medium text-gray-700 group-hover:text-indigo-600">{{ $admin->socialAccount->display_name }}</p>
                                    <p class="text-xs text-gray-400">{{ $admin->socialAccount->region?->name }}</p>
                                </div>
                            </a>
                            @else <span class="text-gray-400 text-xs">-</span> @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-1">
                                <a href="{{ route('admins.edit', $admin) }}" title="Edit"
                                   class="p-1.5 text-amber-500 hover:bg-amber-50 rounded-lg transition">
                                    <i class="fas fa-pen text-xs"></i>
                                </a>
                                <form method="POST" action="{{ route('admins.destroy', $admin) }}" class="inline"
                                      onsubmit="return confirm('Hapus data admin ini?')">
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
                        <td colspan="5" class="px-4 py-12 text-center text-gray-400">
                            <i class="fas fa-inbox text-3xl mb-3 block"></i>
                            Tidak ada data admin/operator ditemukan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($admins->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $admins->links() }}</div>
        @endif
    </div>
</div>
@endsection
