@extends('layouts.app')
@section('title', 'Detail Postingan — SocioWatch Jateng')
@section('page-title', 'Detail Postingan')
@section('breadcrumb')
<a href="{{ route('posts.index') }}" class="hover:text-indigo-600">Postingan</a>
<span class="breadcrumb-sep">/</span><span class="text-slate-600">Detail</span>
@endsection

@section('content')
@php
$pIcon = match($post->platform) {
    'instagram' => ['fa-instagram', 'background:linear-gradient(135deg,#f472b6,#fbbf24)'],
    'twitter'   => ['fa-twitter',   'background:#0ea5e9'],
    'facebook'  => ['fa-facebook',  'background:#2563eb'],
    'tiktok'    => ['fa-tiktok',    'background:#18181b'],
    'youtube'   => ['fa-youtube',   'background:#dc2626'],
    default     => ['fa-globe',     'background:#64748b'],
};

// Build highlighted content
$highlighted = e($post->content);
foreach ($post->keywords->sortByDesc(fn($k) => strlen($k->word)) as $kw) {
    $escaped = e($kw->word);
    $color   = $kw->color;
    $highlighted = str_ireplace(
        $escaped,
        '<mark style="background:' . $color . '22;color:' . $color . ';border-radius:3px;padding:0 2px;font-weight:600">' . $escaped . '</mark>',
        $highlighted
    );
}
@endphp

<div class="max-w-3xl space-y-5">

    {{-- Header --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-start gap-4 mb-5">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0" style="{{ $pIcon[1] }}">
                <i class="fab {{ $pIcon[0] }} text-white text-xl"></i>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-1">
                    <a href="{{ route('accounts.show', $post->socialAccount) }}"
                       class="font-bold text-gray-800 hover:text-indigo-600 transition">
                        {{ $post->socialAccount->display_name }}
                    </a>
                    <span class="text-sm text-gray-400">@{{ $post->socialAccount->username }}</span>
                    @if($post->socialAccount->category)
                    <span class="text-xs px-2 py-0.5 rounded-full text-white"
                          style="background:{{ $post->socialAccount->category->color }}">
                        {{ $post->socialAccount->category->name }}
                    </span>
                    @endif
                </div>
                <p class="text-xs text-gray-400">
                    {{ $post->socialAccount->region->name ?? '-' }} ·
                    {{ $post->posted_at->format('d F Y, H:i') }} WIB
                    @if($post->post_url)
                    · <a href="{{ $post->post_url }}" target="_blank" class="text-indigo-500 hover:underline">
                        <i class="fas fa-external-link-alt mr-0.5"></i>Buka Link
                    </a>
                    @endif
                </p>
            </div>
            <div class="flex gap-2 flex-shrink-0">
                @can('can-edit')
                <a href="{{ route('posts.edit', $post) }}"
                   class="flex items-center gap-1.5 text-sm bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 px-3 py-1.5 rounded-lg transition">
                    <i class="fas fa-pen text-xs"></i> Edit
                </a>
                <form method="POST" action="{{ route('posts.toggle-flag', $post) }}">
                    @csrf
                    @if(!$post->is_flagged)
                    <input type="hidden" name="flag_reason" value="Ditandai dari halaman detail">
                    @endif
                    <button type="submit"
                            class="flex items-center gap-1.5 text-sm px-3 py-1.5 rounded-lg border transition
                                   {{ $post->is_flagged ? 'bg-red-50 text-red-700 border-red-200 hover:bg-red-100' : 'bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100' }}">
                        <i class="fas fa-flag text-xs"></i>
                        {{ $post->is_flagged ? 'Hapus Flag' : 'Flag' }}
                    </button>
                </form>
                @endcan
            </div>
        </div>

        {{-- Flag banner --}}
        @if($post->is_flagged)
        <div class="flex items-start gap-2 p-3 bg-red-50 border border-red-200 rounded-lg mb-4">
            <i class="fas fa-flag text-red-500 mt-0.5"></i>
            <div>
                <span class="text-xs font-bold text-red-700 uppercase tracking-wide">Postingan Terpantau</span>
                @if($post->flag_reason)
                <p class="text-xs text-red-600 mt-0.5">{{ $post->flag_reason }}</p>
                @endif
            </div>
        </div>
        @endif

        {{-- Content with keyword highlighting --}}
        <div class="bg-gray-50 rounded-xl p-4 text-sm text-gray-800 leading-relaxed">
            {!! $highlighted !!}
        </div>

        {{-- Engagement stats --}}
        <div class="grid grid-cols-4 gap-3 mt-4 pt-4 border-t border-gray-100">
            @foreach([
                [$post->likes_count,    'Likes',      'fa-heart',   'text-red-500'],
                [$post->comments_count, 'Comments',   'fa-comment', 'text-blue-500'],
                [$post->shares_count,   'Shares',     'fa-share',   'text-green-500'],
                [$post->views_count,    'Views',      'fa-eye',     'text-gray-500'],
            ] as [$val, $label, $icon, $color])
            <div class="text-center">
                <i class="fas {{ $icon }} {{ $color }} mb-1"></i>
                <p class="font-bold text-gray-800 text-sm">{{ number_format($val) }}</p>
                <p class="text-xs text-gray-400">{{ $label }}</p>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Keyword analysis --}}
    @if($post->keywords->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2">
            <i class="fas fa-key text-purple-500"></i> Keyword Terdeteksi
        </h3>
        <div class="flex flex-wrap gap-2">
            @foreach($post->keywords->sortByDesc(fn($k) => $k->pivot->occurrence_count) as $kw)
            <a href="{{ route('keywords.show', $kw) }}"
               class="flex items-center gap-2 px-3 py-1.5 rounded-lg border transition hover:shadow-sm"
               style="border-color:{{ $kw->color }}30;background:{{ $kw->color }}10">
                <span class="w-2 h-2 rounded-full flex-shrink-0" style="background:{{ $kw->color }}"></span>
                <span class="text-sm font-medium" style="color:{{ $kw->color }}">{{ $kw->word }}</span>
                <span class="text-xs text-gray-400">×{{ $kw->pivot->occurrence_count }}</span>
                <span class="text-[0.6rem] px-1.5 py-0.5 rounded-full text-white font-medium"
                      style="background:{{ $kw->color }}">{{ $kw->category }}</span>
            </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Tags --}}
    @if($post->tags->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-700 mb-3 flex items-center gap-2">
            <i class="fas fa-tags text-indigo-500"></i> Tag
        </h3>
        <div class="flex flex-wrap gap-2">
            @foreach($post->tags as $tag)
            <span class="text-sm px-3 py-1 rounded-full text-white font-medium" style="background:{{ $tag->color }}">
                {{ $tag->name }}
            </span>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
