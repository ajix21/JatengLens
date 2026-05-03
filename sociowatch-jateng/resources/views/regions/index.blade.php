@extends('layouts.app')
@section('title', 'Rekapitulasi Wilayah — SocioWatch Jateng')
@section('page-title', 'Rekapitulasi Wilayah Jawa Tengah')

@section('content')
<div class="space-y-5">

    {{-- Summary cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        @php
        $totalAkun      = $regions->sum('social_accounts_count');
        $totalFollowers = $regions->sum('total_followers');
        $kabupatenCount = $regions->where('type', 'kabupaten')->count();
        $kotaCount      = $regions->where('type', 'kota')->count();
        @endphp
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 text-center">
            <p class="text-2xl font-bold text-gray-800">35</p>
            <p class="text-xs text-gray-400 mt-0.5">Total Wilayah</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 text-center">
            <p class="text-2xl font-bold text-indigo-600">{{ number_format($totalAkun) }}</p>
            <p class="text-xs text-gray-400 mt-0.5">Total Akun</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 text-center">
            <p class="text-xl font-bold text-emerald-600">{{ number_format($totalFollowers) }}</p>
            <p class="text-xs text-gray-400 mt-0.5">Total Followers</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 text-center">
            <p class="text-2xl font-bold text-amber-600">{{ $regions->where('social_accounts_count', '>', 0)->count() }}</p>
            <p class="text-xs text-gray-400 mt-0.5">Wilayah Terdampak</p>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wide">Nama Wilayah</th>
                        <th class="text-center px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wide">Tipe</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wide">Jumlah Akun</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wide">Total Followers</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wide">Kategori Dominan</th>
                        <th class="text-center px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wide">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($regions as $region)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3">
                            <a href="{{ route('regions.show', $region) }}"
                               class="font-medium text-gray-800 hover:text-indigo-600 transition">
                                {{ $region->name }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium
                                {{ $region->type === 'kota' ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700' }}">
                                {{ ucfirst($region->type) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if($region->social_accounts_count > 0)
                            <span class="font-semibold text-indigo-700">{{ $region->social_accounts_count }}</span>
                            @else
                            <span class="text-gray-300">0</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right text-gray-600">
                            {{ $region->total_followers > 0 ? number_format($region->total_followers) : '—' }}
                        </td>
                        <td class="px-4 py-3">
                            @if($region->dominant_category)
                            <span class="inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full text-white font-medium"
                                  style="background:{{ $region->dominant_category->color }}">
                                {{ $region->dominant_category->name }}
                            </span>
                            @else
                            <span class="text-gray-300 text-xs">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-1">
                                <a href="{{ route('regions.show', $region) }}" title="Detail Wilayah"
                                   class="p-1.5 text-indigo-500 hover:bg-indigo-50 rounded-lg transition">
                                    <i class="fas fa-eye text-xs"></i>
                                </a>
                                <a href="{{ route('map.index', ['region_id' => $region->id]) }}" title="Lihat di Peta"
                                   class="p-1.5 text-blue-500 hover:bg-blue-50 rounded-lg transition">
                                    <i class="fas fa-map-marker-alt text-xs"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
