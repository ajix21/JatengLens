@extends('layouts.app')
@section('title', 'Semua Postingan — SocioWatch Jateng')
@section('page-title', 'Manajemen Postingan')
@section('breadcrumb')<span class="text-slate-600">Postingan</span>@endsection

@section('content')
<div class="space-y-4">

{{-- import errors --}}
@if(session('import_errors'))
<div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
    <p class="text-sm font-semibold text-amber-800 mb-1"><i class="fas fa-exclamation-triangle mr-1"></i>Beberapa baris dilewati:</p>
    <ul class="text-xs text-amber-700 list-disc list-inside space-y-0.5 max-h-28 overflow-y-auto">
        @foreach(session('import_errors') as $e)<li>{{ $e }}</li>@endforeach
    </ul>
</div>
@endif

{{-- Toolbar --}}
<div class="flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-2 flex-wrap">
        <span class="text-sm text-slate-500">{{ $posts->total() }} postingan</span>
        @if(request()->hasAny(['q','account_id','platform','category_id','region_id','media_type','date_from','date_to','flagged','sentiment','tag_id','sort']))
        <a href="{{ route('posts.index') }}" class="text-xs text-indigo-600 hover:underline flex items-center gap-1">
            <i class="fas fa-times-circle"></i> Reset filter
        </a>
        @endif
    </div>
    <div class="flex gap-2 flex-wrap">
        <a href="{{ route('posts.search') }}" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm border border-gray-200 text-gray-600 hover:bg-gray-50 transition">
            <i class="fas fa-search text-xs"></i> Cari
        </a>
        @can('can-edit')
        <a href="{{ route('posts.import') }}" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm border border-gray-200 text-gray-600 hover:bg-gray-50 transition">
            <i class="fas fa-file-import text-xs"></i> Import
        </a>
        <a href="{{ route('posts.create') }}" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm bg-indigo-600 hover:bg-indigo-700 text-white transition">
            <i class="fas fa-plus text-xs"></i> Tambah Post
        </a>
        @endcan
    </div>
</div>

{{-- Filters --}}
<form method="GET" action="{{ route('posts.index') }}" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
    <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-7 gap-3">
        <div class="xl:col-span-2">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari konten…" aria-label="Cari konten"
                   class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:ring-indigo-500 focus:border-indigo-500">
        </div>
        <div>
            <select name="account_id" aria-label="Filter Akun" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">Semua Akun</option>
                @foreach($accounts as $acc)
                <option value="{{ $acc->id }}" {{ request('account_id') == $acc->id ? 'selected' : '' }}>
                    {{ $acc->display_name }}
                </option>
                @endforeach
            </select>
        </div>
        <div>
            <select name="platform" aria-label="Filter Platform" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">Semua Platform</option>
                @foreach(['instagram','twitter','facebook','tiktok','youtube'] as $p)
                <option value="{{ $p }}" {{ request('platform') === $p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <select name="category_id" aria-label="Filter Kategori" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">Semua Kategori</option>
                @foreach($categories as $cat)
                <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <select name="region_id" aria-label="Filter Wilayah" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">Semua Wilayah</option>
                @foreach($regions as $r)
                <option value="{{ $r->id }}" {{ request('region_id') == $r->id ? 'selected' : '' }}>{{ $r->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <select name="media_type" aria-label="Filter Tipe Media" class="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">Semua Tipe</option>
                @foreach(['text','image','video','reel','story'] as $mt)
                <option value="{{ $mt }}" {{ request('media_type') === $mt ? 'selected' : '' }}>{{ ucfirst($mt) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-0.5">Dari:</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}" aria-label="Dari Tanggal"
                   class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-0.5">Sampai:</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}" aria-label="Sampai Tanggal"
                   class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm">
        </div>
        <div>
            <select name="sort" aria-label="Urutkan" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm">
                <option value="">Sort: Terbaru</option>
                <option value="likes" {{ request('sort') === 'likes' ? 'selected' : '' }}>Likes Terbanyak</option>
                <option value="comments" {{ request('sort') === 'comments' ? 'selected' : '' }}>Comments Terbanyak</option>
            </select>
        </div>
        <div>
            <select name="sentiment" aria-label="Filter Sentimen" class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">Semua Sentimen</option>
                @foreach(['sensitif' => 'Sensitif', 'negatif' => 'Negatif', 'netral' => 'Netral', 'positif' => 'Positif'] as $val => $lbl)
                <option value="{{ $val }}" {{ request('sentiment') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-center gap-2">
            <label class="flex items-center gap-1.5 text-sm text-gray-600 cursor-pointer">
                <input type="checkbox" name="flagged" value="1" {{ request('flagged') ? 'checked' : '' }}
                       class="rounded text-red-500">
                <span class="text-red-600 font-medium">Flagged</span>
            </label>
        </div>
        <div class="flex gap-2">
            <button type="submit"
                    class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-1.5 px-3 rounded-lg transition">
                Filter
            </button>
        </div>
    </div>
</form>

{{-- Table --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                    <th class="px-4 py-3 text-left">Akun</th>
                    <th class="px-4 py-3 text-left">Konten</th>
                    <th class="px-4 py-3 text-center">Tipe</th>
                    <th class="px-4 py-3 text-right">Engagement</th>
                    <th class="px-4 py-3 text-center">Waktu Posting</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($posts as $post)
                @php
                $pIcon = match($post->platform) {
                    'instagram' => ['fa-instagram','#e1306c'],
                    'twitter'   => ['fa-twitter','#1da1f2'],
                    'facebook'  => ['fa-facebook','#1877f2'],
                    'tiktok'    => ['fa-tiktok','#010101'],
                    'youtube'   => ['fa-youtube','#ff0000'],
                    default     => ['fa-globe','#6b7280'],
                };
                @endphp
                <tr class="hover:bg-gray-50 transition {{ $post->is_flagged ? 'bg-red-50/40' : '' }}">
                    <td class="px-4 py-3 min-w-[160px]">
                        <div class="flex items-center gap-2">
                            <i class="fab {{ $pIcon[0] }} text-base flex-shrink-0" style="color:{{ $pIcon[1] }}"></i>
                            <div class="min-w-0">
                                <p class="font-medium text-gray-800 truncate text-xs">{{ $post->socialAccount->display_name }}</p>
                                <p class="text-[0.65rem] text-gray-400">{{ $post->socialAccount->region->name ?? '-' }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 max-w-xs">
                        @php
                            $cleanCaption = trim((string) preg_replace('/\s+/', ' ', (string) $post->content));
                            $isTruncated  = mb_strlen($cleanCaption) > 120;
                            $shortCaption = \Illuminate\Support\Str::limit($cleanCaption, 120, '...');
                        @endphp
                        <a href="{{ route('posts.show', $post) }}"
                           class="group block text-xs text-gray-700 hover:text-indigo-600 leading-relaxed break-words transition"
                           title="Klik untuk melihat caption lengkap">
                            <span>{{ $shortCaption !== '' ? $shortCaption : '(Tanpa caption)' }}</span>
                            @if($isTruncated)
                            <span class="inline-block ml-1 text-[11px] font-medium text-indigo-500 group-hover:underline">Lihat detail &rarr;</span>
                            @endif
                        </a>
                        @if($post->tags->isNotEmpty())
                        <div class="flex flex-wrap gap-1 mt-1">
                            @foreach($post->tags as $tag)
                            <span class="text-xs px-1.5 py-0.5 rounded-full text-white font-medium"
                                  style="background:{{ $tag->color }}">{{ $tag->name }}</span>
                            @endforeach
                        </div>
                        @endif
                        @if($post->keywords->isNotEmpty())
                        <div class="flex flex-wrap gap-1 mt-0.5">
                            @foreach($post->keywords->take(3) as $kw)
                            <span class="text-xs px-1.5 py-0.5 rounded border font-medium"
                                  style="color:{{ $kw->color }};border-color:{{ $kw->color }}20;background:{{ $kw->color }}15">
                                {{ $kw->word }}
                            </span>
                            @endforeach
                            @if($post->keywords->count() > 3)
                            <span class="text-xs text-gray-400">+{{ $post->keywords->count()-3 }}</span>
                            @endif
                        </div>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center">
                        @php
                        $isReel = in_array(strtolower((string) $post->media_type), ['reel', 'video']);
                        $mtIcon = match($post->media_type) { 'image'=>'fa-image', 'video'=>'fa-video', 'reel'=>'fa-film', 'story'=>'fa-circle-notch', default=>'fa-align-left' };
                        @endphp
                        <div class="inline-flex flex-col items-center gap-1">
                            @if($isReel)
                                <div class="relative w-12 h-12 rounded-lg bg-gradient-to-br from-slate-900 to-indigo-950 flex items-center justify-center text-white border border-slate-700 shadow-sm overflow-hidden group">
                                    @if($post->media_url)
                                    <img src="{{ $post->media_url }}" class="absolute inset-0 w-full h-full object-cover opacity-60" onerror="this.style.display='none'">
                                    @endif
                                    <span class="relative z-10 w-6 h-6 rounded-full bg-pink-600/90 flex items-center justify-center shadow">
                                        <i class="fas fa-play text-[0.55rem] ml-0.5"></i>
                                    </span>
                                </div>
                                <span class="text-[0.65rem] text-pink-600 font-bold uppercase tracking-wider">Reel</span>
                            @elseif($post->media_url)
                                <div class="w-12 h-12 rounded-lg bg-gray-100 overflow-hidden border border-gray-200 shadow-sm">
                                    <img src="{{ $post->media_url }}" alt="Preview" class="w-full h-full object-cover" loading="lazy"
                                         onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'w-12 h-12 flex items-center justify-center text-gray-400\'><i class=\'fas fa-image text-sm\'></i></div>';">
                                </div>
                                <span class="text-[0.65rem] text-gray-500 font-medium">Foto</span>
                            @elseif($post->media_type)
                                <span class="inline-flex items-center gap-1 text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full">
                                    <i class="fas {{ $mtIcon }}"></i> {{ ucfirst($post->media_type) }}
                                </span>
                            @else
                                <span class="text-gray-300 text-xs">—</span>
                            @endif
                        </div>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="text-xs text-gray-500 space-y-0.5">
                            <div><i class="fas fa-heart text-red-400 w-3"></i> {{ number_format($post->likes_count) }}</div>
                            <div><i class="fas fa-comment text-blue-400 w-3"></i> {{ number_format($post->comments_count) }}</div>
                            <div><i class="fas fa-share text-green-400 w-3"></i> {{ number_format($post->shares_count) }}</div>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-center text-xs text-gray-500 whitespace-nowrap">
                        @php $postedWib = $post->posted_at->copy()->timezone('Asia/Jakarta'); @endphp
                        {{ $postedWib->format('d M Y') }}<br>
                        <span class="text-[0.65rem] text-gray-400">{{ $postedWib->format('H:i') }} WIB</span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        @php $badge = \App\Models\Post::sentimentBadge($post->sentiment ?? 'netral'); @endphp
                        <div class="flex flex-col items-center gap-1">
                            <span class="inline-flex items-center text-[0.65rem] font-semibold px-2 py-0.5 rounded-full {{ $badge['bg'] }} {{ $badge['text'] }}">
                                {{ $badge['label'] }}
                            </span>
                            @if($post->is_flagged)
                            <span class="inline-flex items-center gap-1 text-[0.65rem] font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-700">
                                <i class="fas fa-flag"></i> FLAGGED
                            </span>
                            @endif
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-center gap-1">
                            <a href="{{ route('posts.show', $post) }}" title="Detail"
                               class="p-1.5 text-indigo-500 hover:bg-indigo-50 rounded-lg transition">
                                <i class="fas fa-eye text-xs"></i>
                            </a>
                            @can('can-edit')
                            <a href="{{ route('posts.edit', $post) }}" title="Edit"
                               class="p-1.5 text-amber-500 hover:bg-amber-50 rounded-lg transition">
                                <i class="fas fa-pen text-xs"></i>
                            </a>
                            <form method="POST" action="{{ route('posts.toggle-flag', $post) }}" class="inline">
                                @csrf
                                <button type="submit" title="{{ $post->is_flagged ? 'Hapus Flag' : 'Flag' }}"
                                        class="p-1.5 {{ $post->is_flagged ? 'text-red-500 hover:bg-red-50' : 'text-gray-400 hover:bg-gray-50' }} rounded-lg transition">
                                    <i class="fas fa-flag text-xs"></i>
                                </button>
                            </form>
                            @endcan
                            @can('can-delete')
                            <form method="POST" action="{{ route('posts.destroy', $post) }}" class="inline"
                                  onsubmit="return confirm('Hapus postingan ini?')">
                                @csrf @method('DELETE')
                                <button type="submit" title="Hapus"
                                        class="p-1.5 text-red-400 hover:bg-red-50 rounded-lg transition">
                                    <i class="fas fa-trash text-xs"></i>
                                </button>
                            </form>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-12 text-center text-gray-400">
                        <i class="fas fa-inbox text-3xl mb-3 block opacity-30"></i>
                        Tidak ada postingan yang ditemukan.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($posts->hasPages())
    <div class="px-4 py-3 border-t border-gray-100">{{ $posts->withQueryString()->links() }}</div>
    @endif
</div>

</div>
@endsection
