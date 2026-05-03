@extends('layouts.app')
@section('title', 'Dashboard — SocioWatch Jateng')
@section('page-title', 'Dashboard')

@section('content')
<div class="space-y-6">

    {{-- STAT CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">
        @php
        $cards = [
            ['label'=>'Total Akun','value'=>number_format($totalAccounts),'icon'=>'fa-users','color'=>'indigo','sub'=>'akun terdaftar'],
            ['label'=>'Total Followers','value'=>number_format($totalFollowers),'icon'=>'fa-heart','color'=>'pink','sub'=>'total pengikut'],
            ['label'=>'Wilayah Terdampak','value'=>$regionsCovered.' / 35','icon'=>'fa-map-pin','color'=>'emerald','sub'=>'kota/kabupaten'],
            ['label'=>'Kategori Aktif','value'=>$activeCategories,'icon'=>'fa-tags','color'=>'amber','sub'=>'dari total kategori'],
        ];
        $colorMap = ['indigo'=>'bg-indigo-50 text-indigo-600','pink'=>'bg-pink-50 text-pink-600','emerald'=>'bg-emerald-50 text-emerald-600','amber'=>'bg-amber-50 text-amber-600'];
        $borderMap = ['indigo'=>'border-l-indigo-500','pink'=>'border-l-pink-500','emerald'=>'border-l-emerald-500','amber'=>'border-l-amber-500'];
        @endphp

        @foreach($cards as $card)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 border-l-4 {{ $borderMap[$card['color']] }} p-5 flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl {{ $colorMap[$card['color']] }} flex items-center justify-center flex-shrink-0">
                <i class="fas {{ $card['icon'] }} text-xl"></i>
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-800">{{ $card['value'] }}</p>
                <p class="text-xs text-gray-500 font-medium">{{ $card['label'] }}</p>
                <p class="text-xs text-gray-400">{{ $card['sub'] }}</p>
            </div>
        </div>
        @endforeach
    </div>

    {{-- CHARTS ROW --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        {{-- Chart: Akun per Kategori --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <i class="fas fa-chart-bar text-indigo-500"></i> Akun per Kategori
            </h3>
            <canvas id="chartCategory" height="200"></canvas>
        </div>
        {{-- Chart: Top 10 Wilayah --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <i class="fas fa-chart-bar text-emerald-500"></i> Top 10 Kota/Kabupaten
            </h3>
            <canvas id="chartRegion" height="200"></canvas>
        </div>
    </div>

    {{-- MAP + LATEST TABLE --}}
    <div class="grid grid-cols-1 xl:grid-cols-5 gap-5">
        {{-- Mini Map --}}
        <div class="xl:col-span-3 bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-gray-700 flex items-center gap-2">
                    <i class="fas fa-map text-blue-500"></i> Persebaran Akun
                </h3>
                <a href="{{ route('map.index') }}" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">
                    Peta Lengkap <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            <div id="dashMap" class="h-72 rounded-lg overflow-hidden border border-gray-100"></div>
        </div>

        {{-- 5 Latest Accounts --}}
        <div class="xl:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-gray-700 flex items-center gap-2">
                    <i class="fas fa-clock text-purple-500"></i> Akun Terbaru
                </h3>
                <a href="{{ route('accounts.index') }}" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">
                    Lihat semua <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            <div class="space-y-3">
                @forelse($latestAccounts as $acc)
                @php
                $platformIcon = ['instagram'=>'fa-instagram','twitter'=>'fa-twitter','facebook'=>'fa-facebook','tiktok'=>'fa-tiktok','youtube'=>'fa-youtube'][$acc->platform] ?? 'fa-globe';
                $platformColor = ['instagram'=>'text-pink-500','twitter'=>'text-sky-500','facebook'=>'text-blue-600','tiktok'=>'text-gray-800','youtube'=>'text-red-600'][$acc->platform] ?? 'text-gray-400';
                @endphp
                <a href="{{ route('accounts.show', $acc) }}"
                   class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 transition group">
                    <div class="w-9 h-9 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
                        <i class="fab {{ $platformIcon }} {{ $platformColor }}"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-800 truncate group-hover:text-indigo-600">{{ $acc->display_name }}</p>
                        <p class="text-xs text-gray-400 truncate">@{{ $acc->username }} · {{ $acc->region->name ?? '-' }}</p>
                    </div>
                    <span class="text-xs px-2 py-0.5 rounded-full text-white flex-shrink-0"
                          style="background:{{ $acc->category->color ?? '#888' }}">
                        {{ $acc->category->name ?? '-' }}
                    </span>
                </a>
                @empty
                <p class="text-sm text-gray-400 text-center py-4">Belum ada data akun.</p>
                @endforelse
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
// --- CHART: Akun per Kategori ---
const catData = @json($accountsPerCategory);
new Chart(document.getElementById('chartCategory'), {
    type: 'bar',
    data: {
        labels: catData.map(c => c.name),
        datasets: [{
            label: 'Jumlah Akun',
            data: catData.map(c => c.social_accounts_count),
            backgroundColor: catData.map(c => c.color + 'CC'),
            borderColor: catData.map(c => c.color),
            borderWidth: 2,
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});

// --- CHART: Top 10 Wilayah ---
const regionData = @json($topRegions);
new Chart(document.getElementById('chartRegion'), {
    type: 'bar',
    data: {
        labels: regionData.map(r => r.name.replace('Kabupaten ','Kab. ').replace('Kota ','Kota ')),
        datasets: [{
            label: 'Jumlah Akun',
            data: regionData.map(r => r.social_accounts_count),
            backgroundColor: '#6366f1CC',
            borderColor: '#6366f1',
            borderWidth: 2,
            borderRadius: 6,
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true, maintainAspectRatio: true,
        plugins: { legend: { display: false } },
        scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});

// --- DASHBOARD MINI MAP ---
const dashMap = L.map('dashMap', { zoomControl: false, attributionControl: false }).setView([-7.150975, 110.140259], 7);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(dashMap);
L.control.zoom({ position: 'topright' }).addTo(dashMap);

const accounts = @json($mapAccounts);
const markers = L.markerClusterGroup({ maxClusterRadius: 40 });

accounts.forEach(acc => {
    if (!acc.region) return;
    const color = acc.category?.color ?? '#6366f1';
    const icon = L.divIcon({
        html: `<div style="background:${color};width:12px;height:12px;border-radius:50%;border:2px solid white;box-shadow:0 1px 3px rgba(0,0,0,.4)"></div>`,
        className: '', iconSize: [12,12], iconAnchor: [6,6]
    });
    const marker = L.marker([acc.region.latitude, acc.region.longitude], { icon });
    marker.bindPopup(`<b>${acc.display_name}</b><br>@${acc.username}<br>${acc.region.name}`);
    markers.addLayer(marker);
});
dashMap.addLayer(markers);
</script>
@endpush
