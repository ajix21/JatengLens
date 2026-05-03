@extends('layouts.app')
@section('title', '{{ $region->name }} — SocioWatch Jateng')
@section('page-title', $region->name)

@section('content')
<div class="space-y-5">

    {{-- Header + Mini Map --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

        {{-- Stats --}}
        <div class="xl:col-span-2 space-y-4">
            {{-- Info card --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <div class="flex flex-wrap items-center gap-4">
                    <div class="w-14 h-14 rounded-2xl bg-indigo-100 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-map-pin text-indigo-600 text-2xl"></i>
                    </div>
                    <div class="flex-1">
                        <h2 class="text-lg font-bold text-gray-800">{{ $region->name }}</h2>
                        <div class="flex flex-wrap gap-3 mt-1">
                            <span class="text-xs px-2.5 py-1 rounded-full font-medium
                                {{ $region->type === 'kota' ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700' }}">
                                {{ ucfirst($region->type) }}
                            </span>
                            <span class="text-xs text-gray-400">
                                <i class="fas fa-crosshairs mr-1"></i>
                                {{ $region->latitude }}, {{ $region->longitude }}
                            </span>
                        </div>
                    </div>
                    <div class="flex gap-2 flex-shrink-0">
                        <a href="{{ route('map.index', ['region_id' => $region->id]) }}"
                           class="border border-blue-200 text-blue-600 hover:bg-blue-50 text-sm px-3 py-1.5 rounded-lg transition flex items-center gap-1.5">
                            <i class="fas fa-map-marker-alt text-xs"></i> Lihat di Peta
                        </a>
                    </div>
                </div>

                {{-- Stat row --}}
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4 pt-4 border-t border-gray-100">
                    <div class="text-center">
                        <p class="text-2xl font-bold text-indigo-600">{{ $stats['total'] }}</p>
                        <p class="text-xs text-gray-400">Total Akun</p>
                    </div>
                    <div class="text-center">
                        <p class="text-xl font-bold text-emerald-600">{{ number_format($stats['total_followers']) }}</p>
                        <p class="text-xs text-gray-400">Total Followers</p>
                    </div>
                    <div class="text-center col-span-2">
                        <div class="flex flex-wrap justify-center gap-1.5 mt-1">
                            @foreach($stats['by_category'] as $cat)
                            <span class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full text-white"
                                  style="background:{{ $cat['color'] }}">
                                {{ $cat['name'] }}: {{ $cat['count'] }}
                            </span>
                            @endforeach
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Per Kategori</p>
                    </div>
                </div>
            </div>

            {{-- Platform breakdown --}}
            @if($stats['by_platform']->count())
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Sebaran Platform</p>
                @php
                $pIcons = ['instagram'=>['fa-instagram','bg-pink-100 text-pink-600'],'twitter'=>['fa-twitter','bg-sky-100 text-sky-600'],'facebook'=>['fa-facebook','bg-blue-100 text-blue-700'],'tiktok'=>['fa-tiktok','bg-gray-100 text-gray-800'],'youtube'=>['fa-youtube','bg-red-100 text-red-600']];
                @endphp
                <div class="flex flex-wrap gap-3">
                    @foreach($stats['by_platform'] as $platform => $count)
                    @php $pi = $pIcons[$platform] ?? ['fa-globe','bg-gray-100 text-gray-500']; @endphp
                    <div class="flex items-center gap-2 px-3 py-2 rounded-lg {{ $pi[1] }}">
                        <i class="fab {{ $pi[0] }}"></i>
                        <span class="text-sm font-semibold">{{ $count }}</span>
                        <span class="text-xs opacity-70 capitalize">{{ $platform }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- Mini Map --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="font-semibold text-gray-700 mb-3 flex items-center gap-2">
                <i class="fas fa-map text-blue-500"></i> Posisi Wilayah
            </h3>
            <div id="regionMap" class="h-52 rounded-lg overflow-hidden border border-gray-100 mb-2"></div>
            <p class="text-xs text-gray-400 text-center">Jawa Tengah — {{ ucfirst($region->type) }}</p>
        </div>
    </div>

    {{-- Accounts table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-700 flex items-center gap-2">
                <i class="fas fa-users text-indigo-500"></i>
                Daftar Akun di {{ $region->name }}
                <span class="bg-indigo-100 text-indigo-700 text-xs px-2 py-0.5 rounded-full font-semibold">{{ $accounts->count() }}</span>
            </h3>
            <a href="{{ route('accounts.create') }}" class="text-xs text-emerald-600 hover:text-emerald-800 font-medium">
                <i class="fas fa-plus mr-1"></i>Tambah Akun
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wide">Platform / Akun</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wide">Kategori</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wide">Followers</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wide">Admin / Operator</th>
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
                                    <a href="{{ route('accounts.show', $acc) }}"
                                       class="font-medium text-gray-800 hover:text-indigo-600 truncate block">
                                        {{ $acc->display_name }}
                                    </a>
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
                        <td class="px-4 py-3 text-right font-medium text-gray-700">
                            {{ number_format($acc->followers_count) }}
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            @foreach($acc->admins->take(2) as $adm)
                            <div>{{ $adm->full_name }}</div>
                            @endforeach
                            @if($acc->admins->count() > 2)
                            <div class="text-gray-400">+{{ $acc->admins->count() - 2 }} lainnya</div>
                            @endif
                            @if($acc->admins->isEmpty()) <span class="text-gray-300">—</span> @endif
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
                        <td class="px-4 py-3 text-center">
                            <a href="{{ route('accounts.show', $acc) }}"
                               class="p-1.5 text-indigo-500 hover:bg-indigo-50 rounded-lg transition inline-block">
                                <i class="fas fa-eye text-xs"></i>
                            </a>
                            <a href="{{ route('accounts.edit', $acc) }}"
                               class="p-1.5 text-amber-500 hover:bg-amber-50 rounded-lg transition inline-block">
                                <i class="fas fa-pen text-xs"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-gray-400">
                            <i class="fas fa-inbox text-3xl mb-3 block"></i>
                            Belum ada akun yang terdata di wilayah ini.
                            <a href="{{ route('accounts.create') }}" class="block mt-2 text-indigo-500 hover:underline text-sm">
                                Tambah akun sekarang
                            </a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
const regionMap = L.map('regionMap', { zoomControl: false, attributionControl: false })
    .setView([{{ $region->latitude }}, {{ $region->longitude }}], 10);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(regionMap);
L.control.zoom({ position: 'topright' }).addTo(regionMap);

@foreach($accounts as $acc)
@if($acc->category)
(function(){
    const color = '{{ $acc->category->color }}';
    const icon = L.divIcon({
        html: `<div style="background:${color};width:12px;height:12px;border-radius:50%;border:2px solid white;box-shadow:0 1px 4px rgba(0,0,0,.4)"></div>`,
        className: '', iconSize: [12,12], iconAnchor: [6,6]
    });
    L.marker([{{ $region->latitude }}, {{ $region->longitude }}], { icon })
     .bindPopup('<b>{{ addslashes($acc->display_name) }}</b><br>@{{ $acc->username }}')
     .addTo(regionMap);
})();
@endif
@endforeach

// Center marker for the region
L.marker([{{ $region->latitude }}, {{ $region->longitude }}]).addTo(regionMap)
 .bindPopup('<b>{{ addslashes($region->name) }}</b>').openPopup();
</script>
@endpush
