@extends('layouts.app')
@section('title', 'Cari Postingan — SocioWatch Jateng')
@section('page-title', 'Pencarian Konten')
@section('breadcrumb')<span class="text-slate-600">Cari Postingan</span>@endsection

@section('content')
<div class="space-y-5">

{{-- Search bar --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <form method="GET" action="{{ route('posts.search') }}" id="searchForm">
        <div class="flex gap-3 mb-4">
            <div class="flex-1 relative">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" name="q" id="searchInput" value="{{ request('q') }}"
                       placeholder='Contoh: "demo buruh" OR "unjuk rasa" -damai'
                       autofocus
                       class="w-full border border-gray-200 rounded-xl pl-11 pr-4 py-3 text-sm focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-6 py-3 rounded-xl transition text-sm">
                Cari
            </button>
        </div>

        {{-- Quick chips --}}
        <div class="flex flex-wrap gap-2">
            @php $currentQ = request('q', ''); @endphp
            @foreach([
                ['label' => 'Semua Platform', 'params' => ['platform' => '']],
                ['label' => 'Hanya Flagged',  'params' => ['flagged_only' => '1']],
                ['label' => '7 Hari',         'params' => ['days' => '7']],
                ['label' => '30 Hari',        'params' => ['days' => '30']],
                ['label' => 'Kata Sensitif',  'params' => ['sensitive_only' => '1']],
            ] as $chip)
            <a href="{{ route('posts.search', array_merge(request()->only('q','platform','days','flagged_only','sensitive_only'), $chip['params'])) }}"
               class="text-xs px-3 py-1.5 rounded-full border transition
                      {{ collect($chip['params'])->every(fn($v,$k) => request($k) == $v && $v !== '')
                         ? 'bg-indigo-600 text-white border-indigo-600'
                         : 'bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100' }}">
                {{ $chip['label'] }}
            </a>
            @endforeach

            {{-- Platform chips --}}
            @foreach(['instagram','twitter','facebook','tiktok','youtube'] as $p)
            <a href="{{ route('posts.search', array_merge(request()->only('q','days','flagged_only','sensitive_only'), ['platform' => $p])) }}"
               class="text-xs px-3 py-1.5 rounded-full border transition
                      {{ request('platform') === $p ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100' }}">
                {{ ucfirst($p) }}
            </a>
            @endforeach
        </div>

        {{-- Sort --}}
        @if(request('q'))
        <div class="flex items-center gap-3 mt-3 pt-3 border-t border-gray-100 text-xs text-gray-500">
            <span>Urutkan:</span>
            @foreach(['latest' => 'Terbaru', 'engagement' => 'Engagement Tertinggi'] as $v => $l)
            <a href="{{ route('posts.search', array_merge(request()->all(), ['sort' => $v])) }}"
               class="px-2 py-1 rounded {{ request('sort', 'latest') === $v ? 'bg-indigo-100 text-indigo-700 font-semibold' : 'hover:bg-gray-100' }}">
                {{ $l }}
            </a>
            @endforeach
        </div>
        @endif
    </form>

    @if(request('q'))
    <p class="text-xs text-gray-400 mt-3">
        <i class="fas fa-info-circle mr-1"></i>
        Boolean: gunakan <code class="bg-gray-100 px-1 rounded">OR</code> untuk salah satu,
        awali kata dengan <code class="bg-gray-100 px-1 rounded">-</code> untuk exclude.
    </p>
    @endif
</div>

{{-- Results --}}
@if(request('q'))
<div class="flex items-center justify-between">
    <p class="text-sm text-slate-500">
        @if($posts instanceof \Illuminate\Pagination\LengthAwarePaginator)
            {{ $posts->total() }} hasil untuk <strong>"{{ request('q') }}"</strong>
        @endif
    </p>
</div>

@if($posts->isEmpty())
<div class="bg-white rounded-xl shadow-sm border border-gray-100 flex flex-col items-center py-16 text-gray-400">
    <i class="fas fa-search text-4xl mb-3 opacity-30"></i>
    <p class="text-sm font-medium">Tidak ada postingan yang cocok.</p>
    <p class="text-xs mt-1">Coba kata kunci lain atau hilangkan filter.</p>
</div>
@else

@php
// Group results by account
$grouped = $posts->getCollection()->groupBy('social_account_id');
@endphp

@foreach($grouped as $accountId => $accountPosts)
@php $account = $accountPosts->first()->socialAccount; @endphp
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    {{-- Account header --}}
    <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 bg-gray-50">
        <div class="flex items-center gap-3">
            @php
            $pColor = match($account->platform) {
                'instagram' => '#e1306c', 'twitter' => '#1da1f2', 'facebook' => '#1877f2',
                'tiktok' => '#010101', 'youtube' => '#ff0000', default => '#6b7280'
            };
            @endphp
            <i class="fab fa-{{ $account->platform }} text-lg" style="color:{{ $pColor }}"></i>
            <div>
                <a href="{{ route('accounts.show', $account) }}"
                   class="font-semibold text-sm text-gray-800 hover:text-indigo-600 transition">
                    {{ $account->display_name }}
                </a>
                <p class="text-xs text-gray-400">{{ $account->region->name ?? '-' }}</p>
            </div>
        </div>
        <span class="text-xs text-gray-500 bg-white border border-gray-200 px-2 py-1 rounded-full">
            {{ $accountPosts->count() }} postingan cocok
        </span>
    </div>

    {{-- Posts --}}
    <div class="divide-y divide-gray-50">
        @foreach($accountPosts as $post)
        @php
        // Highlight search terms in content
        $displayContent = e($post->content);
        foreach (preg_split('/\s+/', trim(request('q'))) as $term) {
            $cleanTerm = ltrim(trim($term, '"'), '-');
            if ($cleanTerm && strtoupper($cleanTerm) !== 'OR') {
                $displayContent = preg_replace(
                    '/(' . preg_quote(e($cleanTerm), '/') . ')/i',
                    '<mark class="bg-yellow-200 text-yellow-900 rounded px-0.5">$1</mark>',
                    $displayContent
                );
            }
        }
        @endphp
        <div class="px-5 py-4">
            <div class="flex items-start justify-between gap-3">
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-gray-700 leading-relaxed">{!! $displayContent !!}</p>

                    {{-- Tags + Keywords --}}
                    <div class="flex flex-wrap gap-1.5 mt-2">
                        @foreach($post->tags as $tag)
                        <span class="text-[0.6rem] px-1.5 py-0.5 rounded-full text-white font-medium"
                              style="background:{{ $tag->color }}">{{ $tag->name }}</span>
                        @endforeach
                        @foreach($post->keywords->take(4) as $kw)
                        <span class="text-[0.6rem] px-1.5 py-0.5 rounded border font-medium"
                              style="color:{{ $kw->color }};border-color:{{ $kw->color }}30;background:{{ $kw->color }}15">
                            {{ $kw->word }}
                        </span>
                        @endforeach
                    </div>

                    {{-- Engagement + date --}}
                    <div class="flex items-center gap-4 mt-2 text-xs text-gray-400">
                        <span><i class="fas fa-heart text-red-400 mr-1"></i>{{ number_format($post->likes_count) }}</span>
                        <span><i class="fas fa-comment text-blue-400 mr-1"></i>{{ number_format($post->comments_count) }}</span>
                        <span><i class="fas fa-share text-green-400 mr-1"></i>{{ number_format($post->shares_count) }}</span>
                        <span class="ml-auto">{{ $post->posted_at->format('d M Y, H:i') }}</span>
                    </div>
                </div>
                <div class="flex flex-col gap-1.5 flex-shrink-0">
                    <a href="{{ route('posts.show', $post) }}"
                       class="text-xs text-indigo-600 border border-indigo-200 hover:bg-indigo-50 px-2.5 py-1 rounded-lg transition">
                        Detail
                    </a>
                    @can('can-edit')
                    <form method="POST" action="{{ route('posts.toggle-flag', $post) }}">
                        @csrf
                        <button type="submit"
                                class="w-full text-xs border px-2.5 py-1 rounded-lg transition
                                       {{ $post->is_flagged ? 'border-red-200 text-red-600 bg-red-50 hover:bg-red-100' : 'border-gray-200 text-gray-500 hover:bg-gray-50' }}">
                            <i class="fas fa-flag mr-0.5"></i>{{ $post->is_flagged ? 'Unflag' : 'Flag' }}
                        </button>
                    </form>
                    @endcan
                    <a href="{{ route('accounts.show', $account) }}"
                       class="text-xs text-gray-500 border border-gray-200 hover:bg-gray-50 px-2.5 py-1 rounded-lg transition text-center">
                        Akun
                    </a>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endforeach

{{-- Pagination --}}
@if($posts->hasPages())
<div>{{ $posts->appends(request()->all())->links() }}</div>
@endif
@endif

@else
{{-- Empty state --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 flex flex-col items-center py-20 text-gray-400">
    <i class="fas fa-search text-5xl mb-4 opacity-20"></i>
    <p class="text-base font-medium text-gray-500">Masukkan kata kunci untuk memulai pencarian</p>
    <p class="text-sm mt-1">Contoh: "demo buruh", "korupsi OR curang", "kebijakan -gaji"</p>
</div>
@endif

</div>
@endsection
