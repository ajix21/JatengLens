@extends('layouts.app')
@section('title', 'Analytics — SocioWatch Jateng')
@section('page-title', 'Analytics & Tren')
@section('breadcrumb')<span class="text-slate-600">Analytics</span>@endsection

@section('content')
<div class="space-y-6" x-data="{ analyticsTab: 'followers' }">

{{-- Tab switcher --}}
<div class="flex gap-1 bg-white rounded-xl border border-gray-100 p-1 shadow-sm w-fit">
    <button @click="analyticsTab='followers'"
            :class="analyticsTab==='followers' ? 'bg-indigo-600 text-white shadow' : 'text-gray-500 hover:text-gray-700'"
            class="px-4 py-1.5 rounded-lg text-sm font-medium transition">
        <i class="fas fa-chart-line mr-1.5"></i>Followers & Tren
    </button>
    <button @click="analyticsTab='content'"
            :class="analyticsTab==='content' ? 'bg-indigo-600 text-white shadow' : 'text-gray-500 hover:text-gray-700'"
            class="px-4 py-1.5 rounded-lg text-sm font-medium transition">
        <i class="fas fa-newspaper mr-1.5"></i>Analisis Konten
    </button>
</div>

<div x-show="analyticsTab==='followers'" x-cloak class="space-y-6">

{{-- Summary cards --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    @foreach([
        ['Akun Aktif',       $totalAccounts,                         'fa-users',       '#4F46E5', '#eef2ff'],
        ['Total Followers',  number_format($totalFollowers),          'fa-heart',       '#ec4899', '#fdf2f8'],
        ['Alert (7 hari)',   $totalAlerts7d,                         'fa-bell',        '#f59e0b', '#fffbeb'],
        ['Snapshot (30h)',   $snapshotCount,                         'fa-camera',      '#10b981', '#ecfdf5'],
    ] as [$label, $value, $icon, $color, $bg])
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-center gap-4">
        <div class="w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0"
             style="background:{{ $bg }}">
            <i class="fas {{ $icon }} text-base" style="color:{{ $color }}"></i>
        </div>
        <div>
            <p class="text-xs text-gray-400 font-medium">{{ $label }}</p>
            <p class="text-xl font-bold text-gray-800">{{ $value }}</p>
        </div>
    </div>
    @endforeach
</div>

{{-- Top rows --}}
<div class="grid grid-cols-1 xl:grid-cols-2 gap-5">

    {{-- Growth ranking up --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2">
            <i class="fas fa-arrow-trend-up text-green-500"></i> Top 10 Pertumbuhan Tertinggi (30 Hari)
        </h3>
        @forelse($growthTop as $i => $acc)
        <div class="flex items-center gap-3 py-2 {{ $i < $growthTop->count()-1 ? 'border-b border-gray-50' : '' }}">
            <span class="w-6 text-xs font-bold text-gray-400 text-center">{{ $i+1 }}</span>
            <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
                 style="background:{{ $acc->category->color ?? '#6366f1' }}20">
                <i class="fab fa-{{ $acc->platform }} text-xs" style="color:{{ $acc->category->color ?? '#6366f1' }}"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-700 truncate">{{ $acc->display_name }}</p>
                <p class="text-xs text-gray-400">{{ $acc->region->name ?? '-' }}</p>
            </div>
            <div class="text-right">
                <p class="text-sm font-bold text-green-600">+{{ $acc->pct_change }}%</p>
                <p class="text-xs text-gray-400">+{{ number_format($acc->abs_change) }}</p>
            </div>
        </div>
        @empty
        <p class="text-sm text-gray-400 text-center py-6">Tidak ada data pertumbuhan.</p>
        @endforelse
    </div>

    {{-- Growth ranking down --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2">
            <i class="fas fa-arrow-trend-down text-red-500"></i> Top 10 Penurunan Terbesar (30 Hari)
        </h3>
        @forelse($growthBot as $i => $acc)
        <div class="flex items-center gap-3 py-2 {{ $i < $growthBot->count()-1 ? 'border-b border-gray-50' : '' }}">
            <span class="w-6 text-xs font-bold text-gray-400 text-center">{{ $i+1 }}</span>
            <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
                 style="background:{{ $acc->category->color ?? '#6366f1' }}20">
                <i class="fab fa-{{ $acc->platform }} text-xs" style="color:{{ $acc->category->color ?? '#6366f1' }}"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-700 truncate">{{ $acc->display_name }}</p>
                <p class="text-xs text-gray-400">{{ $acc->region->name ?? '-' }}</p>
            </div>
            <div class="text-right">
                <p class="text-sm font-bold text-red-500">{{ $acc->pct_change }}%</p>
                <p class="text-xs text-gray-400">{{ number_format($acc->abs_change) }}</p>
            </div>
        </div>
        @empty
        <p class="text-sm text-gray-400 text-center py-6">Tidak ada data penurunan.</p>
        @endforelse
    </div>
</div>

{{-- Charts row --}}
<div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

    {{-- Aggregate trend line --}}
    <div class="xl:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2">
            <i class="fas fa-chart-line text-indigo-500"></i> Total Followers Semua Akun (30 Hari)
        </h3>
        @if($dailyTrend->count() >= 2)
        <canvas id="trendAggChart" style="height:220px"></canvas>
        @else
        <div class="flex flex-col items-center justify-center h-40 text-gray-400">
            <i class="fas fa-chart-line text-3xl mb-2 opacity-30"></i>
            <p class="text-sm">Belum cukup data snapshot.</p>
        </div>
        @endif
    </div>

    {{-- Category breakdown --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2">
            <i class="fas fa-tags text-purple-500"></i> Followers per Kategori
        </h3>
        @if($categories->sum('total_followers') > 0)
        <canvas id="categoryChart" style="height:220px"></canvas>
        @else
        <p class="text-sm text-gray-400 text-center py-8">Tidak ada data.</p>
        @endif
    </div>
</div>

{{-- Region bar chart --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
    <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2">
        <i class="fas fa-map-pin text-blue-500"></i> Followers per Wilayah (Top 15)
    </h3>
    @if($regionStats->sum('total_followers') > 0)
    <canvas id="regionChart" style="height:280px"></canvas>
    @else
    <p class="text-sm text-gray-400 text-center py-8">Tidak ada data.</p>
    @endif
</div>

{{-- Activity heatmap --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
    <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2">
        <i class="fas fa-calendar-alt text-amber-500"></i> Aktivitas Snapshot (90 Hari Terakhir)
    </h3>
    <div id="heatmapGrid" class="overflow-x-auto pb-2">
        @php
        $heatStart  = $day90->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
        $heatEnd    = now()->endOfWeek(\Carbon\Carbon::SUNDAY);
        $weeks      = [];
        $cursor     = $heatStart->copy();
        while ($cursor->lte($heatEnd)) {
            $week = [];
            for ($d = 0; $d < 7; $d++) {
                $week[] = $cursor->copy();
                $cursor->addDay();
            }
            $weeks[] = $week;
        }
        $maxCount = $heatmap->max('count') ?: 1;
        @endphp
        <div class="flex gap-1">
            @foreach($weeks as $week)
            <div class="flex flex-col gap-1">
                @foreach($week as $day)
                @php
                $dayStr  = $day->format('Y-m-d');
                $entry   = $heatmap->get($dayStr);
                $count   = $entry ? $entry->count : 0;
                $opacity = $count > 0 ? max(0.2, $count / $maxCount) : 0;
                $isPast  = $day->lte(now()) && $day->gte($day90);
                @endphp
                <div class="w-3 h-3 rounded-sm"
                     title="{{ $dayStr }}: {{ $count }} snapshot"
                     style="background: {{ $isPast && $count > 0 ? 'rgba(79,70,229,'.$opacity.')' : ($isPast ? '#f1f5f9' : 'transparent') }}; border: 1px solid {{ $isPast ? '#e2e8f0' : 'transparent' }}">
                </div>
                @endforeach
            </div>
            @endforeach
        </div>
        <div class="flex items-center gap-2 mt-3 text-xs text-gray-400">
            <span>Rendah</span>
            @foreach([0.1, 0.3, 0.5, 0.7, 1.0] as $op)
            <div class="w-3 h-3 rounded-sm" style="background:rgba(79,70,229,{{ $op }})"></div>
            @endforeach
            <span>Tinggi</span>
        </div>
    </div>
</div>

</div>{{-- /followers tab --}}

{{-- ═══════ CONTENT ANALYTICS TAB ═══════ --}}
<div x-show="analyticsTab==='content'" x-cloak class="space-y-6">
@php
$kCloud    = $contentData['keywordCloud'];
$kRank30   = $contentData['keywordRanking30'];
$kRank7    = $contentData['keywordRanking7'];
$sentiment = $contentData['sentiment'];
$topEng    = $contentData['topEngagement'];
$kTrend    = $contentData['keywordTrend'];
$topKwIds  = $contentData['topKeywordIds'];
@endphp

{{-- Word cloud --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
    <h3 class="font-semibold text-gray-700 mb-1 flex items-center gap-2">
        <i class="fas fa-cloud text-indigo-500"></i> Word Cloud Keyword (30 Hari)
    </h3>
    <p class="text-xs text-gray-400 mb-4">Ukuran kata = frekuensi kemunculan · Warna = kategori</p>
    @if($kCloud->isNotEmpty())
    <div id="wordCloudCanvas" style="height:280px;"></div>
    @else
    <div class="flex flex-col items-center justify-center h-40 text-gray-400">
        <i class="fas fa-cloud text-3xl mb-2 opacity-30"></i>
        <p class="text-sm">Belum ada data keyword.</p>
    </div>
    @endif
</div>

{{-- Keyword trend + sentiment --}}
<div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
    {{-- Tren keyword --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2">
            <i class="fas fa-chart-line text-purple-500"></i> Tren Keyword Top 5 (30 Hari)
        </h3>
        @if($kTrend->isNotEmpty())
        <canvas id="kwTrendChart" style="height:220px"></canvas>
        @else
        <div class="flex flex-col items-center justify-center h-32 text-gray-400">
            <p class="text-sm">Belum ada data.</p>
        </div>
        @endif
    </div>

    {{-- Sentiment pie --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-700 mb-1 flex items-center gap-2">
            <i class="fas fa-chart-pie text-green-500"></i> Analisis Sentimen (30 Hari)
        </h3>
        <p class="text-[0.7rem] text-amber-600 bg-amber-50 border border-amber-100 rounded px-2 py-1 mb-3">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            Berdasarkan deteksi keyword, bukan NLP. Hanya sebagai indikasi awal.
        </p>
        @if(!empty($sentiment))
        <canvas id="sentimentChart" style="height:200px"></canvas>
        @else
        <div class="flex flex-col items-center justify-center h-32 text-gray-400">
            <p class="text-sm">Belum ada data sentimen.</p>
        </div>
        @endif
    </div>
</div>

{{-- Keyword ranking table --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="font-semibold text-gray-700 flex items-center gap-2">
            <i class="fas fa-list-ol text-amber-500"></i> Ranking Keyword (30 Hari)
        </h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                    <th class="px-4 py-3 text-center w-10">#</th>
                    <th class="px-4 py-3 text-left">Keyword</th>
                    <th class="px-4 py-3 text-center">Kategori</th>
                    <th class="px-4 py-3 text-right">Total Muncul</th>
                    <th class="px-4 py-3 text-right">Postingan</th>
                    <th class="px-4 py-3 text-right">Akun</th>
                    <th class="px-4 py-3 text-center">Tren 7h</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($kRank30 as $i => $kw)
                @php
                $prev7  = $kRank7->get($kw->id)?->total ?? 0;
                $trendDir = $prev7 > 0
                    ? ($kw->total_occurrences / max(1, ($kw->total_occurrences - $prev7 + $prev7)) > 1.1 ? 'up' : ($kw->total_occurrences < $prev7 ? 'down' : 'stable'))
                    : 'stable';
                $catColors = ['sensitif'=>'bg-red-100 text-red-700','negatif'=>'bg-orange-100 text-orange-700','netral'=>'bg-gray-100 text-gray-600','positif'=>'bg-green-100 text-green-700'];
                @endphp
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-4 py-3 text-center text-xs font-bold text-gray-400">{{ $i+1 }}</td>
                    <td class="px-4 py-3">
                        <a href="{{ route('keywords.show', $kw->id) }}"
                           class="flex items-center gap-2 hover:text-indigo-600 transition">
                            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:{{ $kw->color }}"></span>
                            <span class="font-medium text-gray-800">{{ $kw->word }}</span>
                        </a>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $catColors[$kw->category] ?? 'bg-gray-100' }}">
                            {{ ucfirst($kw->category) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right font-semibold text-gray-700">{{ number_format($kw->total_occurrences) }}</td>
                    <td class="px-4 py-3 text-right text-gray-500">{{ number_format($kw->post_count) }}</td>
                    <td class="px-4 py-3 text-right text-gray-500">{{ number_format($kw->account_count) }}</td>
                    <td class="px-4 py-3 text-center text-xs">
                        @if($trendDir === 'up')
                        <span class="text-green-600 font-medium"><i class="fas fa-arrow-up mr-0.5"></i>Naik</span>
                        @elseif($trendDir === 'down')
                        <span class="text-red-500 font-medium"><i class="fas fa-arrow-down mr-0.5"></i>Turun</span>
                        @else
                        <span class="text-gray-400"><i class="fas fa-minus mr-0.5"></i>Stabil</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400 text-sm">Belum ada data keyword.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Top engagement --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-700 flex items-center gap-2">
            <i class="fas fa-fire text-orange-500"></i> Top 10 Postingan Engagement Tertinggi (30 Hari)
        </h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                    <th class="px-4 py-3 text-center w-10">#</th>
                    <th class="px-4 py-3 text-left">Akun & Konten</th>
                    <th class="px-4 py-3 text-right">♥ Likes</th>
                    <th class="px-4 py-3 text-right">✉ Komentar</th>
                    <th class="px-4 py-3 text-right">↗ Share</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($topEng as $i => $post)
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-4 py-3 text-center text-xs font-bold text-gray-400">{{ $i+1 }}</td>
                    <td class="px-4 py-3 max-w-xs">
                        <p class="text-xs font-medium text-gray-700">{{ $post->socialAccount->display_name }}</p>
                        <p class="text-xs text-gray-500 line-clamp-1">{{ Str::limit($post->content, 80) }}</p>
                    </td>
                    <td class="px-4 py-3 text-right text-xs text-gray-600">{{ number_format($post->likes_count) }}</td>
                    <td class="px-4 py-3 text-right text-xs text-gray-600">{{ number_format($post->comments_count) }}</td>
                    <td class="px-4 py-3 text-right text-xs text-gray-600">{{ number_format($post->shares_count) }}</td>
                    <td class="px-4 py-3 text-right font-semibold text-gray-700 text-xs">{{ number_format($post->total_engagement) }}</td>
                    <td class="px-4 py-3 text-center">
                        <a href="{{ route('posts.show', $post) }}"
                           class="text-xs text-indigo-600 hover:underline">Detail</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400 text-sm">Belum ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

</div>{{-- /content tab --}}

</div>{{-- /main wrapper --}}
@endsection

@push('scripts')
<script>
@if($dailyTrend->count() >= 2)
new Chart(document.getElementById('trendAggChart'), {
    type: 'line',
    data: {
        labels: {!! $dailyTrend->map(fn($r) => '"' . \Carbon\Carbon::parse($r->date)->format('d M') . '"')->implode(',') !!},
        datasets: [{
            label: 'Total Followers',
            data: [{{ $dailyTrend->pluck('total')->implode(',') }}],
            borderColor: '#4F46E5',
            backgroundColor: 'rgba(79,70,229,0.07)',
            borderWidth: 2, tension: 0.4, fill: true,
            pointRadius: 2, pointBackgroundColor: '#4F46E5',
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 11 }, maxTicksLimit: 8 } },
            y: { grid: { color: 'rgba(0,0,0,.04)' }, ticks: { font: { size: 11 }, callback: v => v>=1000000?(v/1000000).toFixed(1)+'M':v>=1000?(v/1000).toFixed(0)+'K':v } }
        }
    }
});
@endif

@if($categories->sum('total_followers') > 0)
new Chart(document.getElementById('categoryChart'), {
    type: 'doughnut',
    data: {
        labels: [{{ $categories->map(fn($c) => '"' . addslashes($c->name) . '"')->implode(',') }}],
        datasets: [{
            data: [{{ $categories->pluck('total_followers')->implode(',') }}],
            backgroundColor: [{{ $categories->map(fn($c) => '"' . ($c->color ?? '#6366f1') . '"')->implode(',') }}],
            borderWidth: 2, borderColor: '#fff'
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 10 } },
            tooltip: { callbacks: { label: ctx => ' ' + ctx.label + ': ' + ctx.parsed.toLocaleString('id-ID') } }
        }
    }
});
@endif

@if($regionStats->sum('total_followers') > 0)
new Chart(document.getElementById('regionChart'), {
    type: 'bar',
    data: {
        labels: [{{ $regionStats->map(fn($r) => '"' . addslashes($r->name) . '"')->implode(',') }}],
        datasets: [{
            label: 'Total Followers',
            data: [{{ $regionStats->pluck('total_followers')->implode(',') }}],
            backgroundColor: 'rgba(79,70,229,0.75)',
            borderRadius: 6, borderSkipped: false,
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false, indexAxis: 'y',
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { color: 'rgba(0,0,0,.04)' }, ticks: { font: { size: 11 }, callback: v => v>=1000000?(v/1000000).toFixed(1)+'M':v>=1000?(v/1000).toFixed(0)+'K':v } },
            y: { grid: { display: false }, ticks: { font: { size: 11 } } }
        }
    }
});
@endif

// ── Content analytics charts ──────────────────────────────────────────────

@if($kCloud->isNotEmpty())
// Word cloud using inline flex simulation (no CDN needed)
(function() {
    const container = document.getElementById('wordCloudCanvas');
    if (!container) return;
    const max = {{ $kCloud->max('total') ?: 1 }};
    const words = {!! $kCloud->map(fn($k) => json_encode(['word'=>$k->word,'total'=>$k->total,'color'=>$k->color]))->implode(',') !!};
    container.style.display = 'flex';
    container.style.flexWrap = 'wrap';
    container.style.alignContent = 'center';
    container.style.gap = '8px';
    container.style.padding = '16px';
    container.style.justifyContent = 'center';
    words.forEach(w => {
        const size = Math.max(11, Math.min(42, 11 + (w.total / max) * 31));
        const span = document.createElement('a');
        span.href = '/keywords/{{ 0 }}'; // placeholder, not clickable from here
        span.textContent = w.word;
        span.style.fontSize = size + 'px';
        span.style.color = w.color;
        span.style.fontWeight = w.total / max > 0.5 ? '700' : '500';
        span.style.lineHeight = '1.3';
        span.style.textDecoration = 'none';
        span.title = w.word + ': ' + w.total + ' kemunculan';
        container.appendChild(span);
    });
})();
@endif

@if($kTrend->isNotEmpty())
(function() {
    const palette = ['#4F46E5','#ef4444','#22c55e','#f59e0b','#8b5cf6'];
    const allDates = [...new Set(
        {!! collect($kTrend->flatten())->map(fn($r) => '"'.$r->date.'"')->implode(',') !!}
    )].sort();
    const kwData   = {!! $kTrend->keys()->map(fn($id) => $id)->values()->toJson() !!};
    const kwNames  = {!! $kRank30->whereIn('id', $topKwIds)->pluck('word','id')->toJson() !!};
    const kwColors = {!! $kRank30->whereIn('id', $topKwIds)->pluck('color','id')->toJson() !!};

    const datasets = kwData.map((id, i) => {
        const rows = {!! $kTrend->map(fn($rows) => $rows->pluck('total','date'))->toJson() !!}[id] || {};
        return {
            label: kwNames[id] || id,
            data: allDates.map(d => rows[d] || 0),
            borderColor: kwColors[id] || palette[i % palette.length],
            backgroundColor: 'transparent',
            borderWidth: 2, tension: 0.4, pointRadius: 2,
        };
    });

    new Chart(document.getElementById('kwTrendChart'), {
        type: 'line',
        data: { labels: allDates.map(d => { const p = d.split('-'); return p[2]+'/'+p[1]; }), datasets },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 8 } } },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 10 }, maxTicksLimit: 8 } },
                y: { grid: { color: 'rgba(0,0,0,.04)' }, ticks: { font: { size: 10 }, stepSize: 1 } }
            }
        }
    });
})();
@endif

@if(!empty($sentiment))
new Chart(document.getElementById('sentimentChart'), {
    type: 'doughnut',
    data: {
        labels: {!! collect($sentiment)->keys()->map(fn($k) => '"' . ucfirst($k) . '"')->implode(',') !!},
        datasets: [{
            data: [{{ collect($sentiment)->values()->implode(',') }}],
            backgroundColor: {!! collect($sentiment)->keys()->map(fn($k) => '"' . ['sensitif'=>'#ef4444','negatif'=>'#f97316','netral'=>'#6b7280','positif'=>'#22c55e'][$k] . '"')->implode(',') !!},
            borderWidth: 2, borderColor: '#fff'
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 10 } },
            tooltip: { callbacks: { label: ctx => ' ' + ctx.label + ': ' + ctx.parsed + ' post' } }
        }
    }
});
@endif
</script>
@endpush
