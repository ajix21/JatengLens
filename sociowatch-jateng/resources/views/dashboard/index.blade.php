@extends('layouts.app')
@section('title', 'Dashboard — SocioWatch Jateng')
@section('page-title', 'Dashboard')

@section('content')
<div class="space-y-6">

    @if(!empty($igTokenAlert))
    @php $isDanger = ($igTokenAlert['level'] ?? 'warning') === 'danger'; @endphp
    <div class="{{ $isDanger ? 'bg-red-50 border-red-200 text-red-900' : 'bg-amber-50 border-amber-200 text-amber-900' }} border rounded-xl p-4 flex flex-wrap items-center justify-between gap-3 shadow-sm">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-lg {{ $isDanger ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-600' }} flex items-center justify-center flex-shrink-0">
                <i class="fas {{ $isDanger ? 'fa-exclamation-circle' : 'fa-clock' }} text-lg"></i>
            </div>
            <div>
                <p class="text-sm font-semibold">{{ $igTokenAlert['title'] }}</p>
                <p class="text-xs {{ $isDanger ? 'text-red-700' : 'text-amber-700' }} mt-0.5">{{ $igTokenAlert['message'] }}</p>
            </div>
        </div>
        <a href="{{ route('auto-fetch.index') }}"
           class="text-xs font-semibold px-3 py-1.5 rounded-lg {{ $isDanger ? 'bg-red-600 hover:bg-red-700 text-white' : 'bg-amber-600 hover:bg-amber-700 text-white' }} transition">
            Cek Pengaturan Auto-Fetch
        </a>
    </div>
    @endif

    {{-- STAT CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">
        @php
        $cards = [
            ['label'=>'Total Akun','value'=>number_format($totalAccounts),'icon'=>'fa-users','color'=>'indigo','sub'=>'akun terdaftar','href'=>route('accounts.index')],
            ['label'=>'Total Followers','value'=>number_format($totalFollowers),'icon'=>'fa-heart','color'=>'pink','sub'=>'total pengikut','href'=>route('accounts.index')],
            ['label'=>'Cakupan Wilayah','value'=>$regionsCovered.' / 35','icon'=>'fa-map-pin','color'=>'emerald','sub'=>'kota/kabupaten','href'=>route('regions.index')],
            ['label'=>'Kategori Aktif','value'=>$activeCategories,'icon'=>'fa-tags','color'=>'amber','sub'=>'dari total kategori','href'=>route('categories.index')],
        ];
        $colorMap = ['indigo'=>'bg-indigo-50 text-indigo-600','pink'=>'bg-pink-50 text-pink-600','emerald'=>'bg-emerald-50 text-emerald-600','amber'=>'bg-amber-50 text-amber-600'];
        $borderMap = ['indigo'=>'border-l-indigo-500','pink'=>'border-l-pink-500','emerald'=>'border-l-emerald-500','amber'=>'border-l-amber-500'];
        @endphp

        @foreach($cards as $card)
        <a href="{{ $card['href'] }}" class="bg-white rounded-xl shadow-sm border border-gray-100 border-l-4 {{ $borderMap[$card['color']] }} p-5 flex items-center gap-4 hover:shadow-md hover:border-indigo-200 transition">
            <div class="w-12 h-12 rounded-xl {{ $colorMap[$card['color']] }} flex items-center justify-center flex-shrink-0">
                <i class="fas {{ $card['icon'] }} text-xl"></i>
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-800">{{ $card['value'] }}</p>
                <p class="text-xs text-gray-500 font-medium">{{ $card['label'] }}</p>
                <p class="text-xs text-gray-400">{{ $card['sub'] }}</p>
            </div>
        </a>
        @endforeach
    </div>

    {{-- EXECUTIVE BRIEFING PANEL --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex flex-wrap items-center justify-between gap-3 pb-4 mb-4 border-b border-gray-100">
            <div class="flex flex-wrap items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-brain text-base"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                        Ringkasan Strategis &amp; Klaster Isu
                    </h3>
                    @if($latestBriefing)
                    <div class="flex flex-wrap items-center gap-2 mt-0.5 text-xs text-gray-500">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full font-medium {{ $latestBriefing->source === 'gemini' ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-700' }}">
                            <i class="fas {{ $latestBriefing->source === 'gemini' ? 'fa-wand-magic-sparkles' : 'fa-microchip' }} text-[0.65rem]"></i>
                            {{ $latestBriefing->source === 'gemini' ? 'AI Gemini' : 'Analitik Otomatis' }}
                        </span>
                        <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 font-medium">
                            {{ $latestBriefing->window_label }}
                        </span>
                        @if($latestBriefing->created_at)
                        <span class="text-gray-400">
                            <i class="far fa-clock mr-0.5"></i>{{ $latestBriefing->created_at->diffForHumans() }}
                        </span>
                        @endif
                    </div>
                    @endif
                </div>
            </div>

            @can('can-edit')
            <form method="POST" action="{{ route('briefings.generate') }}" x-data="{ busy: false }" @submit="busy = true">
                @csrf
                <button type="submit"
                        :disabled="busy"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-xs font-semibold bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm transition">
                    <i class="fas" :class="busy ? 'fa-spinner fa-spin' : 'fa-rotate'"></i> Perbarui Ringkasan
                </button>
            </form>
            @endcan
        </div>

        @if($latestBriefing)
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            {{-- Left column: Executive Summary, Sentiment Snapshot, Recommendations --}}
            <div class="lg:col-span-5 space-y-4">
                <div class="text-sm text-gray-700 leading-relaxed bg-slate-50/70 rounded-xl p-4 border border-slate-100">
                    {!! nl2br(e($latestBriefing->executive_summary)) !!}
                </div>

                @php
                    $sentimentCounts = $latestBriefing->stats_snapshot['sentiment_counts'] ?? [];
                    $sentimentOrder = [
                        'sensitif' => 'Sensitif',
                        'negatif'  => 'Negatif',
                        'netral'   => 'Netral',
                        'positif'  => 'Positif',
                    ];
                @endphp
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Distribusi Sentimen</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($sentimentOrder as $sKey => $sLabel)
                        @php $sBadge = \App\Models\Post::sentimentBadge($sKey); @endphp
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full {{ $sBadge['bg'] }} {{ $sBadge['text'] }}">
                            <span>{{ $sLabel }}:</span>
                            <span class="font-bold">{{ number_format((int) ($sentimentCounts[$sKey] ?? 0)) }}</span>
                        </span>
                        @endforeach
                    </div>
                </div>

                @if(!empty($latestBriefing->recommendations))
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Rekomendasi Tindak Lanjut</p>
                    <ul class="space-y-1.5">
                        @foreach($latestBriefing->recommendations as $rec)
                        <li class="flex items-start gap-2 text-xs text-gray-700">
                            <i class="fas fa-check-circle text-indigo-500 mt-0.5 flex-shrink-0"></i>
                            <span>{{ $rec }}</span>
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endif
            </div>

            {{-- Right column: Issue Clusters --}}
            <div class="lg:col-span-7 space-y-3">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Klaster Isu Teridentifikasi</p>
                @forelse($latestBriefing->issue_clusters ?? [] as $cluster)
                @php
                    $urgency = $cluster['urgency'] ?? 'rendah';
                    $urgencyClass = match ($urgency) {
                        'tinggi' => 'bg-red-100 text-red-700',
                        'sedang' => 'bg-amber-100 text-amber-700',
                        default  => 'bg-slate-100 text-slate-700',
                    };
                    $cSentiment = \App\Models\Post::sentimentBadge($cluster['sentiment'] ?? 'netral');
                @endphp
                <div class="rounded-xl border border-gray-100 bg-gray-50/50 p-4 space-y-2">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <a href="{{ route('posts.search', ['q' => $cluster['title']]) }}" class="text-sm font-bold text-slate-800 hover:text-indigo-600 transition">{{ $cluster['title'] ?? '-' }}</a>
                        <div class="flex items-center gap-1.5 flex-shrink-0">
                            <span class="text-[0.65rem] font-bold uppercase px-2 py-0.5 rounded-full {{ $urgencyClass }}">
                                Urgensi: {{ ucfirst($urgency) }}
                            </span>
                            <span class="text-[0.65rem] font-semibold px-2 py-0.5 rounded-full {{ $cSentiment['bg'] }} {{ $cSentiment['text'] }}">
                                {{ $cSentiment['label'] }}
                            </span>
                        </div>
                    </div>

                    @if(!empty($cluster['summary']))
                    <p class="text-xs text-gray-600 leading-relaxed">{{ $cluster['summary'] }}</p>
                    @endif

                    <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($cluster['regions'] ?? [] as $regionName)
                            <span class="inline-flex items-center gap-1 text-[0.65rem] px-2 py-0.5 rounded-md bg-white border border-gray-200 text-gray-600">
                                <i class="fas fa-map-pin text-indigo-400"></i> {{ $regionName }}
                            </span>
                            @endforeach
                        </div>
                        <div class="flex items-center gap-3 text-xs text-gray-500 font-medium">
                            <span><i class="fas fa-newspaper text-gray-400 mr-1"></i>{{ number_format((int) ($cluster['post_count'] ?? 0)) }} post</span>
                            <span><i class="fas fa-bolt text-amber-400 mr-1"></i>{{ number_format((int) ($cluster['total_engagement'] ?? 0)) }} interaksi</span>
                        </div>
                    </div>
                </div>
                @empty
                <p class="text-sm text-gray-400 py-6 text-center bg-gray-50 rounded-xl border border-dashed border-gray-200">
                    Belum ada klaster isu dominan yang teridentifikasi pada periode ini.
                </p>
                @endforelse
            </div>
        </div>
        @else
        <div class="text-center py-8 text-gray-400">
            <i class="fas fa-brain text-3xl mb-2 opacity-30"></i>
            <p class="text-sm">
                @can('can-edit')
                Klik tombol Perbarui Ringkasan...
                @else
                Ringkasan akan diperbarui otomatis sesuai jadwal.
                @endcan
            </p>
        </div>
        @endif
    </div>

    {{-- CHARTS ROW --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        {{-- Chart: Akun per Kategori --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <i class="fas fa-chart-bar text-indigo-500"></i> Akun per Kategori
            </h3>
            <canvas id="chartCategory" role="img" aria-label="Grafik distribusi akun per kategori" height="200"></canvas>
        </div>
        {{-- Chart: Top 10 Wilayah --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <i class="fas fa-chart-bar text-emerald-500"></i> Top 10 Kota/Kabupaten
            </h3>
            <canvas id="chartRegion" role="img" aria-label="Grafik perbandingan platform" height="200"></canvas>
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
                        <p class="text-xs text-gray-400 truncate">{{ '@' . $acc->username }} · {{ $acc->region->name ?? '-' }}</p>
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

// --- DASHBOARD MINI MAP (Minimalis Jawa Tengah) ---
const esc = s => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
const dashMap = L.map('dashMap', {
    zoomControl: false,
    attributionControl: true,
    scrollWheelZoom: false,
}).setView([-7.150975, 110.140259], 7);
L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Light_Gray_Base/MapServer/tile/{z}/{y}/{x}', {
    attribution: 'Tiles © Esri',
    maxZoom: 16,
}).addTo(dashMap);
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
    marker.bindPopup(`<b>${esc(acc.display_name)}</b><br>@${esc(acc.username)}<br>${esc(acc.region.name)}`);
    markers.addLayer(marker);
});
dashMap.addLayer(markers);
</script>
@endpush
