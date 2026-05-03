@extends('layouts.app')
@section('title', $keyword->word . ' — Keyword Detail')
@section('page-title', 'Keyword: ' . $keyword->word)
@section('breadcrumb')
<a href="{{ route('keywords.manage') }}" class="hover:text-indigo-600">Keyword</a>
<span class="breadcrumb-sep">/</span><span class="text-slate-600">{{ $keyword->word }}</span>
@endsection

@section('content')
<div class="space-y-5">

{{-- Header card --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex items-center gap-5">
    <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-white text-xl font-bold flex-shrink-0"
         style="background:{{ $keyword->color }}">
        {{ strtoupper(substr($keyword->word, 0, 2)) }}
    </div>
    <div class="flex-1 min-w-0">
        <div class="flex flex-wrap items-center gap-3 mb-1">
            <h2 class="text-xl font-bold text-gray-800">{{ $keyword->word }}</h2>
            @php $catColors = ['sensitif'=>'bg-red-100 text-red-700','negatif'=>'bg-orange-100 text-orange-700','netral'=>'bg-gray-100 text-gray-600','positif'=>'bg-green-100 text-green-700']; @endphp
            <span class="text-sm px-3 py-0.5 rounded-full font-medium {{ $catColors[$keyword->category] }}">
                {{ ucfirst($keyword->category) }}
            </span>
            <span class="text-xs px-2 py-0.5 rounded-full {{ $keyword->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                {{ $keyword->is_active ? 'Aktif' : 'Nonaktif' }}
            </span>
        </div>
        <p class="text-sm text-gray-500">Total postingan: <strong>{{ number_format($keyword->posts_count) }}</strong></p>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

    {{-- Trend chart --}}
    <div class="xl:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2">
            <i class="fas fa-chart-line text-indigo-500"></i> Tren Kemunculan (30 Hari)
        </h3>
        @if($trend->count() >= 2)
        <canvas id="trendChart" style="height:200px"></canvas>
        @else
        <div class="flex flex-col items-center justify-center h-32 text-gray-400">
            <i class="fas fa-chart-line text-3xl mb-2 opacity-30"></i>
            <p class="text-sm">Belum cukup data.</p>
        </div>
        @endif
    </div>

    {{-- Top accounts --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2">
            <i class="fas fa-trophy text-amber-500"></i> Akun Paling Sering
        </h3>
        <div class="space-y-2">
            @forelse($topAccounts as $i => $acc)
            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-400 w-5 text-center">{{ $i+1 }}</span>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-700 truncate">{{ $acc->display_name }}</p>
                    <p class="text-[0.6rem] text-gray-400">{{ ucfirst($acc->platform) }} · {{ $acc->post_count }} post</p>
                </div>
                <span class="text-xs font-bold text-gray-600">{{ number_format($acc->total_occurrences) }}×</span>
            </div>
            @empty
            <p class="text-sm text-gray-400 text-center py-4">Tidak ada data.</p>
            @endforelse
        </div>
    </div>
</div>

{{-- Posts list --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
    <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2">
        <i class="fas fa-list text-gray-400"></i> Postingan Mengandung Keyword Ini
    </h3>
    @forelse($posts as $post)
    @php
    $content = e($post->content);
    $content = preg_replace('/(' . preg_quote(e($keyword->word), '/') . ')/i',
        '<mark style="background:' . $keyword->color . '22;color:' . $keyword->color . ';border-radius:3px;padding:0 2px;font-weight:600">$1</mark>',
        $content);
    @endphp
    <div class="py-3 border-b border-gray-50 last:border-0">
        <div class="flex items-start justify-between gap-3">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1 text-xs text-gray-500">
                    <a href="{{ route('accounts.show', $post->socialAccount) }}"
                       class="font-medium text-gray-700 hover:text-indigo-600">
                        {{ $post->socialAccount->display_name }}
                    </a>
                    <span>·</span>
                    <span>{{ $post->posted_at->format('d M Y') }}</span>
                    @if($post->is_flagged)
                    <span class="text-[0.6rem] font-bold px-1.5 py-0.5 rounded-full bg-red-100 text-red-700">
                        <i class="fas fa-flag mr-0.5"></i>FLAG
                    </span>
                    @endif
                </div>
                <p class="text-sm text-gray-700 leading-relaxed">{!! Str::limit($content, 300) !!}</p>
            </div>
            <a href="{{ route('posts.show', $post) }}"
               class="text-xs text-indigo-600 border border-indigo-200 hover:bg-indigo-50 px-2.5 py-1 rounded-lg transition flex-shrink-0">
                Detail
            </a>
        </div>
    </div>
    @empty
    <p class="text-sm text-gray-400 text-center py-8">Tidak ada postingan.</p>
    @endforelse

    @if($posts->hasPages())
    <div class="mt-4">{{ $posts->links() }}</div>
    @endif
</div>

</div>
@endsection

@push('scripts')
<script>
@if($trend->count() >= 2)
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: [{{ $trend->map(fn($r) => '"' . \Carbon\Carbon::parse($r->date)->format('d M') . '"')->implode(',') }}],
        datasets: [{
            label: 'Kemunculan',
            data: [{{ $trend->pluck('total')->implode(',') }}],
            borderColor: '{{ $keyword->color }}',
            backgroundColor: '{{ $keyword->color }}18',
            borderWidth: 2, tension: 0.4, fill: true,
            pointRadius: 3, pointBackgroundColor: '{{ $keyword->color }}',
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 11 }, maxTicksLimit: 8 } },
            y: { grid: { color: 'rgba(0,0,0,.04)' }, ticks: { font: { size: 11 }, stepSize: 1 } }
        }
    }
});
@endif
</script>
@endpush
