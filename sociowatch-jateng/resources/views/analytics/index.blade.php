@extends('layouts.app')
@section('title', 'Analytics — SocioWatch Jateng')
@section('page-title', 'Analytics & Tren')
@section('breadcrumb')<span class="text-slate-600">Analytics</span>@endsection

@section('content')
<div class="space-y-6">

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

</div>
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
</script>
@endpush
