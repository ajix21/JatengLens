@extends('layouts.app')
@section('title', 'Postingan Terpantau — SocioWatch Jateng')
@section('page-title', 'Postingan Terpantau')
@section('breadcrumb')<span class="text-slate-600">Postingan Terpantau</span>@endsection

@section('content')
<div class="space-y-4">

{{-- Toolbar --}}
<div class="flex flex-wrap items-center justify-between gap-3">
    <span class="text-sm text-slate-500">{{ $posts->total() }} postingan terpantau</span>
    <a href="{{ route('posts.flagged.pdf') . '?' . http_build_query(request()->only('category_id','region_id')) }}"
       class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm bg-red-600 hover:bg-red-700 text-white transition">
        <i class="fas fa-file-pdf text-xs"></i> Export PDF
    </a>
</div>

{{-- Filters --}}
<form method="GET" action="{{ route('posts.flagged') }}" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <select name="category_id" class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:ring-indigo-500 focus:border-indigo-500">
            <option value="">Semua Kategori</option>
            @foreach($categories as $cat)
            <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
            @endforeach
        </select>
        <select name="region_id" class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:ring-indigo-500 focus:border-indigo-500">
            <option value="">Semua Wilayah</option>
            @foreach($regions as $r)
            <option value="{{ $r->id }}" {{ request('region_id') == $r->id ? 'selected' : '' }}>{{ $r->name }}</option>
            @endforeach
        </select>
        <input type="date" name="date_from" value="{{ request('date_from') }}"
               class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm">
        <div class="flex gap-2">
            <input type="date" name="date_to" value="{{ request('date_to') }}"
                   class="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-sm">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-4 py-1.5 rounded-lg transition">
                Filter
            </button>
        </div>
    </div>
</form>

{{-- Posts grouped by account --}}
@forelse($posts->getCollection()->groupBy('social_account_id') as $accountId => $accountPosts)
@php $account = $accountPosts->first()->socialAccount; @endphp
<div class="bg-white rounded-xl shadow-sm border border-red-100 overflow-hidden">
    <div class="flex items-center gap-3 px-5 py-3 bg-red-50 border-b border-red-100">
        @php
        $pColor = match($account->platform) {
            'instagram'=>'#e1306c','twitter'=>'#1da1f2','facebook'=>'#1877f2',
            'tiktok'=>'#010101','youtube'=>'#ff0000',default=>'#6b7280'
        };
        @endphp
        <i class="fab fa-{{ $account->platform }} text-lg" style="color:{{ $pColor }}"></i>
        <div class="flex-1 min-w-0">
            <a href="{{ route('accounts.show', $account) }}"
               class="font-semibold text-sm text-gray-800 hover:text-indigo-600 transition">
                {{ $account->display_name }}
            </a>
            <span class="text-xs text-gray-400 ml-2">{{ $account->region->name ?? '-' }}</span>
        </div>
        <span class="text-xs font-bold text-red-700 bg-red-100 px-2.5 py-1 rounded-full">
            <i class="fas fa-flag mr-1"></i>{{ $accountPosts->count() }} flagged
        </span>
    </div>

    <div class="divide-y divide-gray-50">
        @foreach($accountPosts as $post)
        <div class="px-5 py-4">
            <div class="flex items-start gap-3">
                <div class="flex-1 min-w-0">
                    @if($post->flag_reason)
                    <div class="flex items-center gap-1.5 mb-2">
                        <i class="fas fa-flag text-red-500 text-xs"></i>
                        <span class="text-xs text-red-600 font-medium">{{ $post->flag_reason }}</span>
                    </div>
                    @endif
                    <p class="text-sm text-gray-700 leading-relaxed">{{ Str::limit($post->content, 300) }}</p>

                    @if($post->keywords->isNotEmpty())
                    <div class="flex flex-wrap gap-1 mt-2">
                        @foreach($post->keywords as $kw)
                        <span class="text-[0.6rem] px-1.5 py-0.5 rounded border font-medium"
                              style="color:{{ $kw->color }};border-color:{{ $kw->color }}30;background:{{ $kw->color }}15">
                            {{ $kw->word }} ({{ $kw->category }})
                        </span>
                        @endforeach
                    </div>
                    @endif

                    <div class="flex items-center gap-4 mt-2 text-xs text-gray-400">
                        <span><i class="fas fa-heart text-red-400 mr-1"></i>{{ number_format($post->likes_count) }}</span>
                        <span><i class="fas fa-comment text-blue-400 mr-1"></i>{{ number_format($post->comments_count) }}</span>
                        <span class="ml-auto">{{ $post->posted_at->format('d M Y, H:i') }}</span>
                    </div>
                </div>
                <div class="flex flex-col gap-1.5 flex-shrink-0">
                    <a href="{{ route('posts.show', $post) }}"
                       class="text-xs text-indigo-600 border border-indigo-200 hover:bg-indigo-50 px-2.5 py-1 rounded-lg transition text-center">
                        Detail
                    </a>
                    @can('can-edit')
                    <form method="POST" action="{{ route('posts.toggle-flag', $post) }}">
                        @csrf
                        <button type="submit"
                                class="w-full text-xs border border-red-200 text-red-600 bg-red-50 hover:bg-red-100 px-2.5 py-1 rounded-lg transition">
                            <i class="fas fa-flag-checkered mr-0.5"></i>Hapus Flag
                        </button>
                    </form>
                    @endcan
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@empty
<div class="bg-white rounded-xl shadow-sm border border-gray-100 flex flex-col items-center py-16 text-gray-400">
    <i class="fas fa-flag text-4xl mb-3 opacity-20"></i>
    <p class="text-sm font-medium">Tidak ada postingan yang diflag.</p>
</div>
@endforelse

@if($posts->hasPages())
<div>{{ $posts->appends(request()->all())->links() }}</div>
@endif

</div>
@endsection
