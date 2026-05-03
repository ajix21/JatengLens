@extends('layouts.app')
@section('title', '@{{ $account->display_name }} — SocioWatch Jateng')
@section('page-title', $account->display_name)

@section('content')
@php
$pIcon = ['instagram'=>['fa-instagram','bg-gradient-to-br from-pink-500 to-yellow-400'],'twitter'=>['fa-twitter','bg-sky-500'],'facebook'=>['fa-facebook','bg-blue-600'],'tiktok'=>['fa-tiktok','bg-gray-900'],'youtube'=>['fa-youtube','bg-red-600']][$account->platform] ?? ['fa-globe','bg-gray-400'];
@endphp
<div class="space-y-5">

    {{-- HEADER CARD --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex flex-wrap items-start gap-5">
            <div class="w-16 h-16 rounded-2xl {{ $pIcon[1] }} flex items-center justify-center flex-shrink-0">
                <i class="fab {{ $pIcon[0] }} text-white text-2xl"></i>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-3 mb-1">
                    <h2 class="text-xl font-bold text-gray-800">{{ $account->display_name }}</h2>
                    <span class="text-sm px-3 py-0.5 rounded-full text-white" style="background:{{ $account->category->color ?? '#888' }}">{{ $account->category->name ?? '-' }}</span>
                    @if($account->is_active)
                    <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-medium">Aktif</span>
                    @else
                    <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 font-medium">Nonaktif</span>
                    @endif
                </div>
                <p class="text-gray-500 text-sm mb-2">@{{ $account->username }} · {{ $account->region->name ?? '-' }}</p>
                @if($account->bio)
                <p class="text-gray-600 text-sm">{{ $account->bio }}</p>
                @endif
                @if($account->profile_url)
                <a href="{{ $account->profile_url }}" target="_blank" class="text-indigo-600 hover:underline text-xs mt-1 inline-block">
                    <i class="fas fa-external-link-alt mr-1"></i>Buka Profil
                </a>
                @endif
            </div>
            <div class="flex gap-2 flex-shrink-0">
                <a href="{{ route('map.index', ['region_id' => $account->region_id]) }}"
                   class="border border-blue-200 text-blue-600 hover:bg-blue-50 text-sm px-3 py-1.5 rounded-lg transition flex items-center gap-1.5">
                    <i class="fas fa-map-marker-alt text-xs"></i> Peta
                </a>
                <a href="{{ route('accounts.edit', $account) }}"
                   class="bg-amber-50 hover:bg-amber-100 text-amber-700 text-sm px-3 py-1.5 rounded-lg transition flex items-center gap-1.5">
                    <i class="fas fa-pen text-xs"></i> Edit
                </a>
            </div>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-3 gap-4 mt-5 pt-5 border-t border-gray-100">
            <div class="text-center">
                <p class="text-xl font-bold text-gray-800">{{ number_format($account->followers_count) }}</p>
                <p class="text-xs text-gray-400">Followers</p>
            </div>
            <div class="text-center">
                <p class="text-xl font-bold text-gray-800">{{ number_format($account->following_count) }}</p>
                <p class="text-xs text-gray-400">Following</p>
            </div>
            <div class="text-center">
                <p class="text-xl font-bold text-gray-800">{{ number_format($account->post_count) }}</p>
                <p class="text-xs text-gray-400">Postingan</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

        {{-- ADMINS LIST --}}
        <div class="xl:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-gray-700 flex items-center gap-2">
                    <i class="fas fa-id-card text-indigo-500"></i> Admin/Operator ({{ $account->admins->count() }})
                </h3>
                <a href="{{ route('accounts.edit', $account) }}" class="text-xs text-indigo-600 hover:underline">Edit Admin</a>
            </div>
            @forelse($account->admins as $admin)
            <div class="flex items-start gap-3 p-3 rounded-lg border border-gray-100 mb-3 bg-gray-50">
                <div class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0 text-indigo-600 font-bold text-sm">
                    {{ strtoupper(substr($admin->full_name, 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0 grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                    <div><span class="font-medium text-gray-800">{{ $admin->full_name }}</span>
                        @if($admin->alias)<span class="text-gray-400 text-xs"> ({{ $admin->alias }})</span>@endif</div>
                    @if($admin->nik) <div class="text-gray-500 text-xs">NIK: {{ $admin->nik }}</div> @endif
                    @if($admin->phone) <div class="text-gray-500 text-xs"><i class="fas fa-phone text-xs mr-1"></i>{{ $admin->phone }}</div> @endif
                    @if($admin->email) <div class="text-gray-500 text-xs"><i class="fas fa-envelope text-xs mr-1"></i>{{ $admin->email }}</div> @endif
                    @if($admin->occupation || $admin->affiliation)
                    <div class="col-span-2 text-gray-500 text-xs">{{ $admin->occupation }}{{ $admin->occupation && $admin->affiliation ? ' — ' : '' }}{{ $admin->affiliation }}</div>
                    @endif
                </div>
            </div>
            @empty
            <p class="text-sm text-gray-400 text-center py-4">Belum ada data admin/operator.</p>
            @endforelse
        </div>

        {{-- MINI MAP --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="font-semibold text-gray-700 mb-3 flex items-center gap-2">
                <i class="fas fa-map text-blue-500"></i> Lokasi
            </h3>
            <div id="accountMap" class="h-48 rounded-lg overflow-hidden border border-gray-100 mb-3"></div>
            <p class="text-sm font-medium text-gray-700">{{ $account->region->name ?? '-' }}</p>
            <p class="text-xs text-gray-400">{{ $account->region->type === 'kota' ? 'Kota' : 'Kabupaten' }} · Jawa Tengah</p>
            @if($account->notes)
            <div class="mt-3 pt-3 border-t border-gray-100">
                <p class="text-xs font-semibold text-gray-500 mb-1">Catatan:</p>
                <p class="text-sm text-gray-600">{{ $account->notes }}</p>
            </div>
            @endif
        </div>
    </div>

    {{-- ACTIVITY LOG --}}
    @if($account->activityLogs->count())
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2">
            <i class="fas fa-history text-purple-500"></i> Riwayat Perubahan
        </h3>
        <div class="space-y-2">
            @foreach($account->activityLogs as $log)
            <div class="flex items-start gap-3 text-sm">
                <div class="w-2 h-2 rounded-full bg-purple-400 mt-1.5 flex-shrink-0"></div>
                <div class="flex-1">
                    <span class="font-medium text-gray-700 capitalize">{{ str_replace('_', ' ', $log->activity_type) }}</span>
                    @if($log->old_value || $log->new_value)
                    <span class="text-gray-400"> · </span>
                    <span class="text-gray-500 line-through text-xs">{{ $log->old_value }}</span>
                    @if($log->old_value && $log->new_value) <span class="text-gray-400 mx-1">→</span> @endif
                    <span class="text-gray-700 text-xs font-medium">{{ $log->new_value }}</span>
                    @endif
                </div>
                <span class="text-xs text-gray-400 flex-shrink-0">{{ $log->logged_at->diffForHumans() }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

</div>
@endsection

@push('scripts')
<script>
@if($account->region)
const map = L.map('accountMap', { zoomControl: false, attributionControl: false })
    .setView([{{ $account->region->latitude }}, {{ $account->region->longitude }}], 10);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
L.control.zoom({ position: 'topright' }).addTo(map);
const color = '{{ $account->category->color ?? "#6366f1" }}';
const icon = L.divIcon({
    html: `<div style="background:${color};width:16px;height:16px;border-radius:50%;border:3px solid white;box-shadow:0 2px 6px rgba(0,0,0,.4)"></div>`,
    className: '', iconSize: [16,16], iconAnchor: [8,8]
});
L.marker([{{ $account->region->latitude }}, {{ $account->region->longitude }}], { icon })
    .bindPopup('{{ $account->display_name }}<br>{{ $account->region->name }}')
    .addTo(map).openPopup();
@endif
</script>
@endpush
