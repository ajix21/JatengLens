@extends('layouts.app')
@section('title', 'Manajemen Akun — SocioWatch Jateng')
@section('page-title', 'Manajemen Akun Sosmed')
@section('breadcrumb')<span class="text-slate-600">Akun Sosmed</span>@endsection

@section('content')
<div class="space-y-5">

    @php
        $unsupportedIgCount = \App\Models\SocialAccount::where('platform', 'instagram')
            ->where('is_active', true)
            ->where('last_fetch_status', \App\Enums\FetchStatus::Unsupported)
            ->count();
    @endphp
    @if($unsupportedIgCount > 0)
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex flex-wrap items-center justify-between gap-3 text-amber-900 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center flex-shrink-0 text-amber-600">
                <i class="fab fa-chrome text-lg"></i>
            </div>
            <div>
                <p class="text-sm font-semibold">Terdapat {{ $unsupportedIgCount }} Akun Instagram Personal</p>
                <p class="text-xs text-amber-700">Akun personal dibatasi oleh Meta Graph API. Gunakan <strong>Ekstensi Browser SocioWatch Companion</strong> untuk sinkronisasi otomatis 1-klik melalui sesi browser Anda.</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs px-3 py-1.5 bg-amber-200 text-amber-800 rounded-lg font-medium">Lokasi: /extension</span>
        </div>
    </div>
    @endif

    {{-- TOOLBAR --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
        <form method="GET" action="{{ route('accounts.index') }}" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[200px]">
                <label for="f_search" class="block text-xs font-medium text-gray-500 mb-1">Cari</label>
                <div class="relative">
                    <input type="text" id="f_search" name="search" value="{{ request('search') }}" placeholder="Username / nama..."
                           class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                </div>
            </div>
            <div>
                <label for="f_platform" class="block text-xs font-medium text-gray-500 mb-1">Platform</label>
                <select id="f_platform" name="platform" class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                    <option value="">Semua</option>
                    @foreach($platforms as $p)
                    <option value="{{ $p }}" {{ request('platform') == $p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="f_category" class="block text-xs font-medium text-gray-500 mb-1">Kategori</label>
                <select id="f_category" name="category_id" class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                    <option value="">Semua</option>
                    @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="f_region" class="block text-xs font-medium text-gray-500 mb-1">Wilayah</label>
                <select id="f_region" name="region_id" class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                    <option value="">Semua</option>
                    @foreach($regions as $reg)
                    <option value="{{ $reg->id }}" {{ request('region_id') == $reg->id ? 'selected' : '' }}>{{ $reg->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="f_status" class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                <select id="f_status" name="status" class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                    <option value="">Semua</option>
                    <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Aktif</option>
                    <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Nonaktif</option>
                </select>
            </div>
            <div>
                <label for="f_fetch_mode" class="block text-xs font-medium text-gray-500 mb-1">Metode Fetch</label>
                <select id="f_fetch_mode" name="fetch_mode" class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                    <option value="">Semua</option>
                    <option value="api" {{ request('fetch_mode') === 'api' ? 'selected' : '' }}>Meta API (Auto)</option>
                    <option value="extension" {{ request('fetch_mode') === 'extension' ? 'selected' : '' }}>Ekstensi Browser</option>
                    <option value="not_found" {{ request('fetch_mode') === 'not_found' ? 'selected' : '' }}>Tidak Ditemukan</option>
                    <option value="manual" {{ request('fetch_mode') === 'manual' ? 'selected' : '' }}>Manual / Non-API</option>
                </select>
            </div>
            <div>
                <label for="f_admin_status" class="block text-xs font-medium text-gray-500 mb-1">Data Admin</label>
                <select id="f_admin_status" name="admin_status" class="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                    <option value="">Semua</option>
                    <option value="missing" {{ request('admin_status') === 'missing' ? 'selected' : '' }}>Belum Ada Admin ({{ $missingAdminCount ?? 0 }})</option>
                    <option value="has_admin" {{ request('admin_status') === 'has_admin' ? 'selected' : '' }}>Sudah Ada Admin</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-4 py-2 rounded-lg transition">
                    <i class="fas fa-search mr-1"></i>Filter
                </button>
                <a href="{{ route('accounts.index') }}" class="border border-gray-200 hover:bg-gray-50 text-gray-600 text-sm px-4 py-2 rounded-lg transition">
                    Reset
                </a>
            </div>
            {{-- Export buttons --}}
            <div class="ml-auto flex items-center gap-2">
                @php $exportParams = http_build_query(array_filter(request()->only(['search', 'platform', 'category_id', 'region_id', 'status', 'fetch_mode', 'admin_status']))) @endphp
                <a href="{{ route('export.accounts') }}?{{ $exportParams }}"
                   class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium text-emerald-700 border border-emerald-200 hover:bg-emerald-50 transition">
                    <i class="fas fa-file-excel"></i> Excel
                </a>
                <a href="{{ route('export.accounts.pdf') }}?{{ $exportParams }}"
                   class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium text-red-600 border border-red-200 hover:bg-red-50 transition">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
                @can('can-edit')
                <a href="{{ route('accounts.create') }}" class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm px-4 py-2 rounded-lg transition flex items-center gap-2">
                    <i class="fas fa-plus"></i> Tambah Akun
                </a>
                @endcan
            </div>
        </form>
    </div>

    {{-- TABLE --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wide">Platform / Akun</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wide">Kategori</th>
                        <th class="text-left px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wide">Wilayah</th>
                        <th class="text-right px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wide">Followers</th>
                        <th class="text-center px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wide">Status</th>
                        <th class="text-center px-4 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wide">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($accounts as $acc)
                    @php
                    $pIcon = ['instagram'=>['fa-instagram','text-pink-500'],'twitter'=>['fa-twitter','text-sky-500'],'facebook'=>['fa-facebook','text-blue-600'],'tiktok'=>['fa-tiktok','text-gray-700'],'youtube'=>['fa-youtube','text-red-600']][$acc->platform] ?? ['fa-globe','text-gray-400'];
                    @endphp
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
                                    <i class="fab {{ $pIcon[0] }} {{ $pIcon[1] }} text-lg"></i>
                                </div>
                                <div class="min-w-0">
                                    <a href="{{ route('accounts.show', $acc) }}" class="font-medium text-gray-800 hover:text-indigo-600 truncate block">{{ $acc->display_name }}</a>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-xs text-gray-400">{{ '@' . $acc->username }}</span>
                                        @if(($acc->admins_count ?? 0) > 0)
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 font-medium">
                                            <i class="fas fa-user-shield mr-0.5"></i>{{ $acc->admins_count }} Admin
                                        </span>
                                        @else
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 font-medium">
                                            Belum Ada Admin
                                        </span>
                                        @endif
                                    </div>
                                    @include('accounts._fetch-status', ['account' => $acc])
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium text-white"
                                  style="background:{{ $acc->category->color ?? '#888' }}">
                                {{ $acc->category->name ?? '-' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $acc->region->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-right font-medium text-gray-700">
                            {{ number_format($acc->followers_count) }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($acc->is_active)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Aktif
                            </span>
                            @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
                                <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> Nonaktif
                            </span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-1">
                                <a href="{{ route('map.index', ['region_id' => $acc->region_id]) }}"
                                   title="Lihat di Peta" aria-label="Lihat di Peta"
                                   class="min-w-[34px] min-h-[34px] inline-flex items-center justify-center p-1.5 text-blue-500 hover:bg-blue-50 rounded-lg transition">
                                    <i class="fas fa-map-marker-alt text-xs"></i>
                                </a>
                                <a href="{{ route('accounts.show', $acc) }}" title="Detail" aria-label="Detail"
                                   class="min-w-[34px] min-h-[34px] inline-flex items-center justify-center p-1.5 text-indigo-500 hover:bg-indigo-50 rounded-lg transition">
                                    <i class="fas fa-eye text-xs"></i>
                                </a>
                                @can('can-edit')
                                <button type="button" title="Update Stats" aria-label="Update Stats"
                                        data-account-id="{{ $acc->id }}" data-account-name="{{ $acc->display_name }}" data-followers="{{ $acc->followers_count }}" data-following="{{ $acc->following_count }}" data-posts="{{ $acc->post_count }}" onclick="openStatsModalFromEl(this)"
                                        class="min-w-[34px] min-h-[34px] inline-flex items-center justify-center p-1.5 text-green-500 hover:bg-green-50 rounded-lg transition">
                                    <i class="fas fa-sync-alt text-xs"></i>
                                </button>
                                <a href="{{ route('accounts.edit', $acc) }}" title="Edit" aria-label="Edit"
                                   class="min-w-[34px] min-h-[34px] inline-flex items-center justify-center p-1.5 text-amber-500 hover:bg-amber-50 rounded-lg transition">
                                    <i class="fas fa-pen text-xs"></i>
                                </a>
                                @endcan
                                @can('can-delete')
                                <form method="POST" action="{{ route('accounts.destroy', $acc) }}" class="inline"
                                      onsubmit="return confirm('Hapus akun ini?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" title="Hapus" aria-label="Hapus"
                                            class="min-w-[34px] min-h-[34px] inline-flex items-center justify-center p-1.5 text-red-400 hover:bg-red-50 rounded-lg transition">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-gray-400">
                            <i class="fas fa-inbox text-3xl mb-3 block"></i>
                            Tidak ada data akun yang ditemukan.
                            @if(request()->hasAny(['search', 'platform', 'category_id', 'region_id', 'status']))
                            <div class="mt-3">
                                <a href="{{ route('accounts.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-indigo-600 bg-indigo-50 hover:bg-indigo-100 transition">
                                    <i class="fas fa-redo"></i> Reset Filter
                                </a>
                            </div>
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($accounts->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $accounts->links() }}
        </div>
        @endif
    </div>

</div>

{{-- Update Stats Modal --}}
@can('can-edit')
<div id="statsModal" role="dialog" aria-modal="true" aria-labelledby="modalAccountName" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
     style="background:rgba(0,0,0,.5)"
     @keydown.escape.window="document.getElementById('statsModal').classList.add('hidden')"
     @click.self="document.getElementById('statsModal').classList.add('hidden')">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm" @click.stop>
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h3 id="modalAccountName" class="font-semibold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-sync-alt text-green-500"></i> Update Stats
                </h3>
                <p id="modalStatsName" class="text-xs text-gray-400 mt-0.5"></p>
            </div>
            <button onclick="document.getElementById('statsModal').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 text-xl leading-none">×</button>
        </div>
        <form id="formQuickStats" method="POST" action="">
            @csrf
            <div class="px-6 py-4 space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Followers</label>
                    <input type="number" name="followers_count" id="statsFollowers" min="0" required
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Following</label>
                    <input type="number" name="following_count" id="statsFollowing" min="0"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Postingan</label>
                    <input type="number" name="post_count" id="statsPost" min="0"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>
            <div class="flex gap-3 px-6 pb-5">
                <button type="button" onclick="document.getElementById('statsModal').classList.add('hidden')"
                        class="flex-1 border border-gray-200 text-gray-600 hover:bg-gray-50 text-sm font-medium py-2 rounded-lg transition">
                    Batal
                </button>
                <button type="submit"
                        class="flex-1 bg-green-600 hover:bg-green-700 text-white text-sm font-medium py-2 rounded-lg transition">
                    Simpan
                </button>
            </div>
        </form>
    </div>
</div>
@endcan

@endsection

@push('scripts')
<script>
function openStatsModalFromEl(el) {
    const id = el.dataset.accountId;
    const name = el.dataset.accountName;
    const followers = el.dataset.followers;
    const following = el.dataset.following;
    const post = el.dataset.posts;
    document.getElementById('formQuickStats').action = '/accounts/' + id + '/update-stats';
    document.getElementById('modalStatsName').textContent = name;
    document.getElementById('statsFollowers').value = followers;
    document.getElementById('statsFollowing').value = following;
    document.getElementById('statsPost').value = post;
    document.getElementById('statsModal').classList.remove('hidden');
}
</script>
@endpush
