@extends('layouts.app')
@section('title', $account->display_name . ' — Profil & Pemantauan Akun — SocioWatch Jateng')
@section('page-title', $account->display_name)
@section('breadcrumb')
<a href="{{ route('accounts.index') }}" class="hover:text-indigo-600 transition">Akun</a>
<span class="breadcrumb-sep">/</span>
<span class="text-slate-600">{{ $account->display_name }}</span>
@endsection

@section('content')
@php
$pIcon = match($account->platform) {
    'instagram' => ['fa-instagram', 'background:linear-gradient(135deg,#f472b6,#fbbf24)'],
    'twitter'   => ['fa-twitter',   'background:#0ea5e9'],
    'facebook'  => ['fa-facebook',  'background:#2563eb'],
    'tiktok'    => ['fa-tiktok',    'background:#18181b'],
    'youtube'   => ['fa-youtube',   'background:#dc2626'],
    default     => ['fa-globe',     'background:#64748b'],
};
$setting = $account->alertSetting ?? $globalSetting;
@endphp

<div class="space-y-5">

{{-- HEADER CARD: RINGKASAN AKUN --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <div class="flex flex-wrap items-start gap-5">
        <div class="w-16 h-16 rounded-2xl flex items-center justify-center flex-shrink-0" style="{{ $pIcon[1] }}">
            <i class="fab {{ $pIcon[0] }} text-white text-2xl"></i>
        </div>
        <div class="flex-1 min-w-0">
            <div class="flex flex-wrap items-center gap-3 mb-1">
                <h2 class="text-xl font-bold text-gray-800">{{ $account->display_name }}</h2>
                <span class="text-sm px-3 py-0.5 rounded-full text-white"
                      style="background:{{ $account->category->color ?? '#888' }}">{{ $account->category->name ?? '-' }}</span>
                @if($account->is_active)
                <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-medium">Aktif Dipantau</span>
                @else
                <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 font-medium">Nonaktif</span>
                @endif
            </div>
            <p class="text-gray-500 text-sm mb-2">{{ '@' . $account->username }} · {{ $account->region->name ?? '-' }}</p>
            @include('accounts._fetch-status', ['account' => $account])
            @if($account->bio)
            <p class="text-gray-600 text-sm mt-1 bg-gray-50 p-2.5 rounded-lg border border-gray-100 italic">"{{ $account->bio }}"</p>
            @endif
            @if($account->profile_url)
            <a href="{{ $account->profile_url }}" target="_blank" rel="noopener noreferrer"
               class="text-indigo-600 hover:underline text-xs mt-2 inline-flex items-center gap-1 font-medium">
                <i class="fas fa-external-link-alt text-[0.65rem]"></i> Buka Profil {{ ucfirst($account->platform) }}
            </a>
            @endif
        </div>
        <div class="flex gap-2 flex-shrink-0 flex-wrap">
            @can('can-edit')
            @if(in_array($account->platform, ['youtube', 'instagram']))
            <form method="POST" action="{{ route('accounts.fetch', $account) }}">
                @csrf
                <button type="submit"
                        class="bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-sm px-3 py-1.5 rounded-lg transition flex items-center gap-1.5 border border-emerald-200">
                    <i class="fas fa-rotate text-xs"></i> Ambil sekarang
                </button>
            </form>
            @endif
            @endcan
            <a href="{{ route('map.index', ['region_id' => $account->region_id]) }}"
               class="border border-blue-200 text-blue-600 hover:bg-blue-50 text-sm px-3 py-1.5 rounded-lg transition flex items-center gap-1.5">
                <i class="fas fa-map-marker-alt text-xs"></i> Peta
            </a>
            @can('can-edit')
            <a href="{{ route('accounts.edit', $account) }}"
               class="bg-amber-50 hover:bg-amber-100 text-amber-700 text-sm px-3 py-1.5 rounded-lg transition flex items-center gap-1.5 border border-amber-200">
                <i class="fas fa-pen text-xs"></i> Edit Profiling
            </a>
            @endcan
        </div>
    </div>

    {{-- Stats row --}}
    <div class="grid grid-cols-3 gap-4 mt-5 pt-5 border-t border-gray-100">
        <div class="text-center">
            <p class="text-xl font-bold text-gray-800">{{ number_format($account->followers_count) }}</p>
            <p class="text-xs text-gray-400 font-medium">Followers</p>
            @if($change7d !== null)
            <p class="text-xs font-semibold mt-0.5 {{ $change7d >= 0 ? 'text-green-600' : 'text-red-500' }}">
                {{ $change7d >= 0 ? '+' : '' }}{{ $change7d }}% <span class="font-normal text-gray-400">(7 hr)</span>
            </p>
            @endif
        </div>
        <div class="text-center">
            <p class="text-xl font-bold text-gray-800">{{ number_format($account->following_count) }}</p>
            <p class="text-xs text-gray-400 font-medium">Following</p>
            @if($change30d !== null)
            <p class="text-xs font-semibold mt-0.5 {{ $change30d >= 0 ? 'text-green-600' : 'text-red-500' }}">
                {{ $change30d >= 0 ? '+' : '' }}{{ $change30d }}% <span class="font-normal text-gray-400">(30 hr)</span>
            </p>
            @endif
        </div>
        <div class="text-center">
            <p class="text-xl font-bold text-gray-800">{{ number_format($account->post_count) }}</p>
            <p class="text-xs text-gray-400 font-medium">Total Postingan</p>
        </div>
    </div>
</div>

{{-- TABS SYSTEM: FOKUS UTAMA PROFILING --}}
<div x-data="{ tab: 'profiling' }">
    <div role="tablist" class="flex gap-1 bg-white rounded-xl border border-gray-100 p-1 shadow-sm w-fit mb-5">
        <button role="tab" :aria-selected="tab === 'profiling'" @click="tab='profiling'"
                :class="tab==='profiling' ? 'bg-indigo-600 text-white shadow' : 'text-gray-500 hover:text-gray-700'"
                class="px-4 py-2 rounded-lg text-sm font-semibold transition flex items-center gap-2">
            <i class="fas fa-id-card-clip"></i>
            <span>Profiling & Latar Belakang</span>
        </button>
        <button role="tab" :aria-selected="tab === 'trend'" @click="tab='trend'; $nextTick(() => { window.dispatchEvent(new Event('resize')); })"
                :class="tab==='trend' ? 'bg-indigo-600 text-white shadow' : 'text-gray-500 hover:text-gray-700'"
                class="px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2">
            <i class="fas fa-chart-line"></i>
            <span>Tren Followers (Tambahan)</span>
        </button>
        <button role="tab" :aria-selected="tab === 'alerts'" @click="tab='alerts'"
                :class="tab==='alerts' ? 'bg-indigo-600 text-white shadow' : 'text-gray-500 hover:text-gray-700'"
                class="px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2">
            <i class="fas fa-bell"></i>
            <span>Alert</span>
            @if($recentAlerts->where('is_read', false)->count())
            <span class="bg-red-500 text-white text-[0.65rem] font-bold px-1.5 py-0.5 rounded-full">
                {{ $recentAlerts->where('is_read', false)->count() }}
            </span>
            @endif
        </button>
        <button role="tab" :aria-selected="tab === 'info'" @click="tab='info'"
                :class="tab==='info' ? 'bg-indigo-600 text-white shadow' : 'text-gray-500 hover:text-gray-700'"
                class="px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2">
            <i class="fas fa-history"></i>
            <span>Riwayat & Log</span>
        </button>
    </div>

    {{-- TAB 1: PROFILING & LATAR BELAKANG (TAMPILAN UTAMA) --}}
    <div x-show="tab==='profiling'" x-cloak class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- KOLOM KIRI: ADMIN & ORANG DI BALIK AKUN (2 Kolom) --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- KARTU 1: ADMIN & ORANG DI BALIK AKUN --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center justify-between pb-4 mb-4 border-b border-gray-100">
                        <div>
                            <h3 class="font-bold text-gray-800 text-base flex items-center gap-2">
                                <i class="fas fa-user-shield text-indigo-600"></i>
                                Orang di Balik Layar / Admin Pengelola
                            </h3>
                            <p class="text-xs text-gray-500 mt-0.5">Identitas personal pengelola, relasi organisasi, dan kontak yang terverifikasi</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="bg-indigo-50 text-indigo-700 text-xs font-bold px-2.5 py-1 rounded-full border border-indigo-100">
                                {{ $account->admins->count() }} Orang Terdata
                            </span>
                            @can('can-edit')
                            <a href="{{ route('accounts.edit', $account) }}" class="text-xs text-indigo-600 hover:underline flex items-center gap-1 font-medium">
                                <i class="fas fa-user-plus text-[0.7rem]"></i> Kelola Admin
                            </a>
                            @endcan
                        </div>
                    </div>

                    @forelse($account->admins as $admin)
                    @php
                        $waReport = $admin->toWhatsAppText();
                    @endphp
                    <div x-data="{ copied: false, text: @js($waReport) }"
                         class="p-4 rounded-xl border border-gray-200 bg-slate-50/70 hover:bg-slate-50 transition mb-3">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold text-lg flex-shrink-0 shadow-sm">
                                {{ strtoupper(substr($admin->full_name, 0, 1)) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center justify-between gap-2 mb-2 pb-2 border-b border-gray-100">
                                    <div>
                                        <h4 class="font-bold text-gray-900 text-sm inline-block">{{ $admin->full_name }}</h4>
                                        @if($admin->alias)
                                        <span class="ml-1 text-xs text-indigo-600 font-semibold bg-indigo-50 px-2 py-0.5 rounded border border-indigo-100">
                                            Alias: {{ $admin->alias }}
                                        </span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2">
                                        @if($admin->nik)
                                        <div class="text-xs bg-gray-100 text-gray-700 px-2 py-0.5 rounded font-mono font-medium">
                                            NIK: {{ $admin->nik }}
                                        </div>
                                        @endif
                                        @if($admin->no_kk)
                                        <div class="text-xs bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded font-mono font-medium">
                                            KK: {{ $admin->no_kk }}
                                        </div>
                                        @endif
                                        <button type="button"
                                                @click="navigator.clipboard.writeText(text); copied = true; setTimeout(() => copied = false, 2000)"
                                                class="text-[0.7rem] px-2.5 py-1 rounded-md font-semibold transition flex items-center gap-1 shadow-xs"
                                                :class="copied ? 'bg-emerald-600 text-white' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200'"
                                                title="Salin format data admin untuk laporan WhatsApp">
                                            <i class="fab fa-whatsapp" :class="copied ? 'text-white' : 'text-emerald-600'"></i>
                                            <span x-text="copied ? 'Tersalin!' : 'Salin Format WA'">Salin Format WA</span>
                                        </button>
                                    </div>
                                </div>

                                {{-- FORMAT DATA PROFILING ADMIN (STANDAR LAPORAN WA) --}}
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1.5 text-xs text-gray-700 mb-2.5 font-mono">
                                    <div class="flex items-baseline gap-1.5">
                                        <span class="text-gray-400 font-sans">•</span>
                                        <span class="font-semibold text-gray-500 font-sans">Nama:</span>
                                        <span class="text-gray-900 font-sans font-medium">{{ $admin->full_name }}</span>
                                    </div>
                                    <div class="flex items-baseline gap-1.5">
                                        <span class="text-gray-400 font-sans">•</span>
                                        <span class="font-semibold text-gray-500 font-sans">NIK:</span>
                                        <span class="text-gray-900">{{ $admin->nik ?: '-' }}</span>
                                    </div>
                                    <div class="flex items-baseline gap-1.5">
                                        <span class="text-gray-400 font-sans">•</span>
                                        <span class="font-semibold text-gray-500 font-sans">No KK:</span>
                                        <span class="text-gray-900">{{ $admin->no_kk ?: '-' }}</span>
                                    </div>
                                    <div class="flex items-baseline gap-1.5">
                                        <span class="text-gray-400 font-sans">•</span>
                                        <span class="font-semibold text-gray-500 font-sans">Status:</span>
                                        <span class="text-gray-900 font-sans">{{ $admin->status ?: '-' }}</span>
                                    </div>
                                    <div class="flex items-baseline gap-1.5">
                                        <span class="text-gray-400 font-sans">•</span>
                                        <span class="font-semibold text-gray-500 font-sans">Agama:</span>
                                        <span class="text-gray-900 font-sans">{{ $admin->agama ?: '-' }}</span>
                                    </div>
                                    <div class="flex items-baseline gap-1.5">
                                        <span class="text-gray-400 font-sans">•</span>
                                        <span class="font-semibold text-gray-500 font-sans">TTL:</span>
                                        <span class="text-gray-900 font-sans">{{ $admin->ttl ?: '-' }}</span>
                                    </div>
                                    <div class="flex items-baseline gap-1.5">
                                        <span class="text-gray-400 font-sans">•</span>
                                        <span class="font-semibold text-gray-500 font-sans">Pekerjaan:</span>
                                        <span class="text-gray-900 font-sans">{{ $admin->occupation ?: '-' }}</span>
                                    </div>
                                    <div class="sm:col-span-2 flex items-start gap-1.5">
                                        <span class="text-gray-400 font-sans">•</span>
                                        <span class="font-semibold text-gray-500 font-sans flex-shrink-0">Alamat:</span>
                                        <span class="text-gray-900 font-sans">{{ $admin->alamat ?: '-' }}</span>
                                    </div>
                                    @if($admin->phone || $admin->email || $admin->affiliation)
                                    <div class="sm:col-span-2 pt-1 border-t border-gray-100 flex flex-wrap items-center gap-3 text-[0.7rem] text-gray-500 font-sans">
                                        @if($admin->phone)
                                        <span class="flex items-center gap-1">
                                            <i class="fab fa-whatsapp text-emerald-600"></i>
                                            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $admin->phone) }}" target="_blank" rel="noopener noreferrer" class="hover:text-emerald-700 font-mono">
                                                {{ $admin->phone }}
                                            </a>
                                        </span>
                                        @endif
                                        @if($admin->email)
                                        <span class="flex items-center gap-1">
                                            <i class="fas fa-envelope text-blue-500"></i>
                                            <a href="mailto:{{ $admin->email }}" class="hover:text-blue-700">{{ $admin->email }}</a>
                                        </span>
                                        @endif
                                        @if($admin->affiliation)
                                        <span class="flex items-center gap-1">
                                            <i class="fas fa-sitemap text-gray-400"></i> {{ $admin->affiliation }}
                                        </span>
                                        @endif
                                    </div>
                                    @endif
                                </div>

                                @if($admin->notes)
                                <div class="mt-2.5 p-2.5 bg-amber-50/80 border border-amber-200/70 rounded-lg text-xs text-amber-900 leading-relaxed">
                                    <span class="font-bold flex items-center gap-1 text-amber-800 mb-0.5">
                                        <i class="fas fa-note-sticky text-[0.65rem]"></i> Catatan:
                                    </span>
                                    <p class="whitespace-pre-line text-amber-950 font-sans">{{ $admin->notes }}</p>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="flex flex-col items-center justify-center py-8 text-center bg-gray-50 rounded-xl border border-dashed border-gray-200">
                        <i class="fas fa-user-ninja text-3xl text-gray-300 mb-2"></i>
                        <p class="text-sm font-semibold text-gray-600">Belum Ada Profil Pengelola Tercatat</p>
                        <p class="text-xs text-gray-400 max-w-sm mt-1">Identitas admin atau operator akun ini belum ditambahkan ke basis data pemantauan.</p>
                        @can('can-edit')
                        <a href="{{ route('accounts.edit', $account) }}" class="mt-3 text-xs bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg transition font-medium">
                            + Tambah Data Admin
                        </a>
                        @endcan
                    </div>
                    @endforelse
                </div>

                {{-- KARTU 2: ANTESEDEN & REKAM JEJAK AKUN --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center justify-between pb-3 mb-3 border-b border-gray-100">
                        <h3 class="font-bold text-gray-800 text-base flex items-center gap-2">
                            <i class="fas fa-fingerprint text-purple-600"></i>
                            Anteseden & Rekam Jejak Akun
                        </h3>
                        <span class="text-xs bg-purple-50 text-purple-700 px-2.5 py-0.5 rounded-full border border-purple-100 font-medium">
                            Informasi Latar Belakang
                        </span>
                    </div>

                    <div class="space-y-3">
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-sm text-gray-700 leading-relaxed">
                            @if($account->notes)
                            <div class="font-semibold text-gray-800 mb-1 flex items-center gap-1.5">
                                <i class="fas fa-note-sticky text-indigo-500"></i> Catatan Latar Belakang & Anteseden:
                            </div>
                            <p class="whitespace-pre-line text-xs sm:text-sm text-slate-700">{{ $account->notes }}</p>
                            @else
                            <p class="text-xs text-gray-400 italic">Belum ada catatan khusus mengenai rekam jejak atau anteseden akun ini.</p>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                            <div class="p-3 bg-gray-50 rounded-lg border border-gray-100">
                                <span class="text-gray-400 block mb-0.5">Kategori Pergerakan:</span>
                                <span class="font-bold text-gray-800">{{ $account->category->name ?? 'Tidak Terkategori' }}</span>
                                <p class="text-[0.7rem] text-gray-500 mt-0.5">Fokus isu: advokasi kebijakan, pergerakan massa, atau sosial-politik.</p>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-lg border border-gray-100">
                                <span class="text-gray-400 block mb-0.5">Tingkat Pengawasan:</span>
                                <span class="font-bold text-emerald-700 flex items-center gap-1">
                                    <i class="fas fa-shield-halved"></i> {{ $account->is_active ? 'Aktif dalam Radar Monitor' : 'Pengawasan Pasif' }}
                                </span>
                                <p class="text-[0.7rem] text-gray-500 mt-0.5">Siklus update auto-fetch otomatis tersinkronisasi.</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            {{-- KOLOM KANAN: LOKASI & GEOSPASIAL (1 Kolom) --}}
            <div class="space-y-6">

                {{-- KARTU LOKASI WILAYAH & PETA --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <h3 class="font-bold text-gray-800 text-sm mb-3 flex items-center gap-2">
                        <i class="fas fa-map-location-dot text-blue-600"></i>
                        Lokasi & Teritorial Wilayah
                    </h3>

                    <div id="accountMap" class="h-48 rounded-lg overflow-hidden border border-gray-200 mb-3 shadow-inner"></div>

                    <div class="space-y-2 text-xs">
                        <div class="flex items-center justify-between pb-2 border-b border-gray-100">
                            <span class="text-gray-500">Kabupaten/Kota:</span>
                            <span class="font-bold text-gray-800">{{ $account->region->name ?? '-' }}</span>
                        </div>
                        <div class="flex items-center justify-between pb-2 border-b border-gray-100">
                            <span class="text-gray-500">Tipe Wilayah:</span>
                            <span class="font-medium text-gray-700 capitalize">{{ $account->region?->type ?? 'Kabupaten' }}</span>
                        </div>
                        <div class="flex items-center justify-between pb-2 border-b border-gray-100">
                            <span class="text-gray-500">Provinsi:</span>
                            <span class="font-medium text-gray-700">Jawa Tengah</span>
                        </div>
                        @if($account->region)
                        <div class="flex items-center justify-between pt-1">
                            <span class="text-gray-400 text-[0.65rem]">Koordinat:</span>
                            <span class="font-mono text-gray-500 text-[0.65rem]">{{ number_format($account->region->latitude, 4) }}, {{ number_format($account->region->longitude, 4) }}</span>
                        </div>
                        @endif
                    </div>

                    <a href="{{ route('map.index', ['region_id' => $account->region_id]) }}"
                       class="mt-4 w-full bg-blue-50 hover:bg-blue-100 text-blue-700 font-semibold text-xs py-2 px-3 rounded-lg transition flex items-center justify-center gap-1.5 border border-blue-200">
                        <i class="fas fa-map text-xs"></i> Buka Peta Sebaran Jawa Tengah
                    </a>
                </div>

                {{-- KARTU RINGKASAN METRIK TAMBAHAN --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <h4 class="font-bold text-gray-700 text-sm mb-3 flex items-center gap-2">
                        <i class="fas fa-chart-simple text-indigo-500"></i> Metrik Ringkas
                    </h4>
                    <div class="space-y-2.5 text-xs">
                        <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg">
                            <span class="text-gray-500">Dinamika 7 Hari:</span>
                            @if($change7d !== null)
                            <span class="font-bold {{ $change7d >= 0 ? 'text-green-600' : 'text-red-500' }}">
                                {{ $change7d >= 0 ? '+' : '' }}{{ $change7d }}%
                            </span>
                            @else
                            <span class="text-gray-400">-</span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg">
                            <span class="text-gray-500">Dinamika 30 Hari:</span>
                            @if($change30d !== null)
                            <span class="font-bold {{ $change30d >= 0 ? 'text-green-600' : 'text-red-500' }}">
                                {{ $change30d >= 0 ? '+' : '' }}{{ $change30d }}%
                            </span>
                            @else
                            <span class="text-gray-400">-</span>
                            @endif
                        </div>
                    </div>
                    <button @click="tab='trend'; $nextTick(() => { window.dispatchEvent(new Event('resize')); })"
                            class="mt-3 text-xs text-indigo-600 hover:underline block text-center w-full font-medium">
                        Lihat Grafik Tren Selengkapnya →
                    </button>
                </div>

            </div>

        </div>

        {{-- SECTION POSTINGAN TERKINI AKUN (3-6 POSTINGAN) --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3 pb-4 mb-5 border-b border-gray-100">
                <div>
                    <h3 class="font-bold text-gray-800 text-base flex items-center gap-2">
                        <i class="fas fa-newspaper text-indigo-600"></i>
                        Postingan Terkini Akun
                    </h3>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Menampilkan 3–6 postingan terakhir yang tersimpan di sistem (tanpa hit API berulang)
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs bg-indigo-50 text-indigo-700 px-3 py-1 rounded-full border border-indigo-100 font-bold">
                        {{ $posts->count() }} Post Terdata
                    </span>
                    <a href="{{ route('posts.index', ['account_id' => $account->id]) }}"
                       class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold flex items-center gap-1 hover:underline">
                        Buka di Menu Postingan <i class="fas fa-arrow-right text-[0.65rem]"></i>
                    </a>
                </div>
            </div>

            @if($posts->isNotEmpty())
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach($posts as $p)
                @php
                $badge = \App\Models\Post::sentimentBadge($p->sentiment ?? 'netral');
                $isReel = in_array(strtolower((string) $p->media_type), ['reel', 'video']);
                @endphp
                <div class="flex flex-col bg-slate-50/60 rounded-xl border border-gray-200/80 overflow-hidden hover:shadow-md transition duration-200">

                    {{-- VISUAL MEDIA / PREVIEW REELS CONTAINER --}}
                    @if($isReel)
                        {{-- PREVIEW KHUSUS REELS / VIDEO --}}
                        <div class="relative w-full h-48 bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 flex flex-col justify-between p-3.5 border-b border-slate-800 group overflow-hidden">
                            @if($p->media_url)
                            <img src="{{ $p->media_url }}" alt="Reel Preview" referrerpolicy="no-referrer"
                                 class="absolute inset-0 w-full h-full object-cover opacity-60 group-hover:scale-105 transition duration-300"
                                 onerror="this.style.display='none'">
                            @endif
                            <div class="relative z-10 flex items-center justify-between">
                                <span class="bg-pink-600 text-white text-[0.65rem] font-bold px-2 py-0.5 rounded shadow flex items-center gap-1 tracking-wider uppercase">
                                    <i class="fas fa-film"></i> Reels Preview
                                </span>
                                <span class="text-xs text-slate-300 bg-black/40 px-2 py-0.5 rounded backdrop-blur-sm">
                                    <i class="fab fa-instagram text-pink-400"></i>
                                </span>
                            </div>

                            <div class="relative z-10 flex items-center justify-center my-auto">
                                <div class="w-12 h-12 rounded-full bg-white/20 border border-white/40 text-white flex items-center justify-center shadow-lg group-hover:scale-110 group-hover:bg-pink-600/80 transition duration-200">
                                    <i class="fas fa-play text-sm ml-0.5"></i>
                                </div>
                            </div>

                            <div class="relative z-10 flex items-center justify-between text-[0.7rem] text-slate-300 pt-1">
                                <span><i class="fas fa-video text-slate-400 mr-1"></i>Video Konten</span>
                                @if($p->post_url)
                                <a href="{{ $p->post_url }}" target="_blank" rel="noopener noreferrer"
                                   class="bg-white/90 hover:bg-white text-gray-900 px-2 py-0.5 rounded font-semibold text-[0.65rem] shadow flex items-center gap-1">
                                    Tonton di IG <i class="fas fa-external-link-alt text-[0.55rem]"></i>
                                </a>
                                @endif
                            </div>
                        </div>
                    @elseif($p->media_url)
                        {{-- PREVIEW GAMBAR ASLI DARI MEDIA_URL --}}
                        <div class="relative w-full h-48 bg-slate-900 overflow-hidden border-b border-gray-200 group">
                            <img src="{{ $p->media_url }}" alt="Preview Postingan" referrerpolicy="no-referrer"
                                 class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                                 loading="lazy"
                                 onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'w-full h-48 bg-slate-100 flex flex-col items-center justify-center text-slate-400 text-xs p-4 text-center\'><i class=\'fas fa-image text-3xl mb-1 opacity-40\'></i>Gambar tidak dapat dimuat</div>';">
                            <span class="absolute top-2 left-2 bg-black/60 text-white text-[0.65rem] font-bold px-2 py-0.5 rounded backdrop-blur-sm">
                                <i class="fas fa-image text-indigo-300 mr-1"></i> Foto
                            </span>
                        </div>
                    @else
                        {{-- FALLBACK GAMBAR JIKA URL GAMBAR BELUM TERSIMPAN --}}
                        <div class="w-full h-28 bg-gradient-to-r from-slate-100 to-indigo-50/50 flex flex-col items-center justify-center text-slate-400 p-3 border-b border-slate-200/60">
                            <i class="fab fa-instagram text-2xl text-pink-400 mb-1 opacity-70"></i>
                            <span class="text-[0.65rem] text-slate-500 font-medium">Postingan Publik Instagram</span>
                        </div>
                    @endif

                    {{-- POST CARD BODY --}}
                    <div class="p-4 flex-1 flex flex-col justify-between space-y-3">
                        <div>
                            {{-- Header Post Card: Sentimen & Tanggal --}}
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <span class="text-[0.65rem] font-semibold px-2 py-0.5 rounded-full {{ $badge['bg'] }} {{ $badge['text'] }}">
                                    {{ $badge['label'] }}
                                </span>
                                <span class="text-[0.65rem] text-gray-400 font-medium">
                                    {{ $p->posted_at->format('d M Y, H:i') }}
                                </span>
                            </div>

                            @if($p->is_flagged)
                            <div class="mb-2 bg-red-100 text-red-800 text-[0.65rem] font-bold px-2 py-0.5 rounded flex items-center gap-1 border border-red-200">
                                <i class="fas fa-flag text-[0.6rem]"></i> DIPANTAU KHUSUS
                            </div>
                            @endif

                            {{-- Caption Narasi --}}
                            <p class="text-xs text-gray-700 leading-relaxed line-clamp-3">
                                {{ $p->content }}
                            </p>

                            {{-- Keywords Badges --}}
                            @if($p->keywords->isNotEmpty())
                            <div class="flex flex-wrap gap-1 mt-2.5">
                                @foreach($p->keywords->take(3) as $kw)
                                <span class="text-[0.65rem] px-1.5 py-0.5 rounded border font-medium"
                                      style="color:{{ $kw->color }};border-color:{{ $kw->color }}30;background:{{ $kw->color }}10">
                                    #{{ $kw->word }}
                                </span>
                                @endforeach
                                @if($p->keywords->count() > 3)
                                <span class="text-[0.65rem] text-gray-400">+{{ $p->keywords->count() - 3 }}</span>
                                @endif
                            </div>
                            @endif
                        </div>

                        {{-- Footer Engagement & Action Links --}}
                        <div class="pt-3 border-t border-gray-200/60 flex items-center justify-between text-xs text-gray-500">
                            <div class="flex items-center gap-3 text-[0.7rem]">
                                <span title="Likes"><i class="fas fa-heart text-red-400 mr-1"></i>{{ number_format($p->likes_count) }}</span>
                                <span title="Komentar"><i class="fas fa-comment text-blue-400 mr-1"></i>{{ number_format($p->comments_count) }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <a href="{{ route('posts.show', $p) }}" class="text-indigo-600 hover:text-indigo-800 font-semibold text-[0.7rem] hover:underline">
                                    Detail
                                </a>
                                @if($p->post_url)
                                <span class="text-gray-300">·</span>
                                <a href="{{ $p->post_url }}" target="_blank" rel="noopener noreferrer" class="text-gray-500 hover:text-gray-800 text-[0.7rem]">
                                    <i class="fas fa-external-link-alt text-[0.6rem]"></i>
                                </a>
                                @endif
                            </div>
                        </div>
                    </div>

                </div>
                @endforeach
            </div>
            @else
            <div class="flex flex-col items-center justify-center py-10 text-center bg-gray-50 rounded-xl border border-dashed border-gray-200">
                <i class="fas fa-inbox text-3xl text-gray-300 mb-2"></i>
                <p class="text-sm font-semibold text-gray-600">Belum Ada Postingan Terambil</p>
                <p class="text-xs text-gray-400 max-w-sm mt-1">Belum ada riwayat postingan yang tersimpan di basis data untuk akun ini.</p>
            </div>
            @endif
        </div>

    </div>

    {{-- TAB 2: TREN FOLLOWERS (TAMBAHAN) --}}
    <div x-show="tab==='trend'" x-cloak class="mt-4">
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
            {{-- Chart --}}
            <div class="xl:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="font-semibold text-gray-700 flex items-center gap-2">
                            <i class="fas fa-chart-area text-indigo-500"></i> Tren Followers 30 Hari (Metrik Tambahan)
                        </h3>
                        <p class="text-xs text-gray-400 mt-0.5">Dinamika fluktuasi pertumbuhan pengikut</p>
                    </div>
                    <span class="text-xs text-gray-400">{{ $snapshots30->count() }} titik data</span>
                </div>
                @if($snapshots30->count() >= 2)
                <canvas id="trendChart" style="height:240px"></canvas>
                @else
                <div class="flex flex-col items-center justify-center h-48 text-gray-400">
                    <i class="fas fa-chart-line text-3xl mb-2 opacity-30"></i>
                    <p class="text-sm">Belum cukup data snapshot untuk menampilkan grafik.</p>
                    <p class="text-xs mt-1">Minimal 2 snapshot diperlukan.</p>
                </div>
                @endif
            </div>

            {{-- Stat Perubahan --}}
            <div class="space-y-4">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <h4 class="font-semibold text-gray-700 text-sm mb-3 flex items-center gap-2">
                        <i class="fas fa-arrow-trend-up text-green-500"></i> Perubahan Followers
                    </h4>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between pb-2 border-b border-gray-100">
                            <span class="text-xs text-gray-500">vs 7 hari lalu</span>
                            @if($change7d !== null)
                            <span class="text-sm font-bold {{ $change7d >= 0 ? 'text-green-600' : 'text-red-500' }}">
                                {{ $change7d >= 0 ? '+' : '' }}{{ $change7d }}%
                            </span>
                            @else
                            <span class="text-xs text-gray-400">Data tidak cukup</span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-gray-500">vs 30 hari lalu</span>
                            @if($change30d !== null)
                            <span class="text-sm font-bold {{ $change30d >= 0 ? 'text-green-600' : 'text-red-500' }}">
                                {{ $change30d >= 0 ? '+' : '' }}{{ $change30d }}%
                            </span>
                            @else
                            <span class="text-xs text-gray-400">Data tidak cukup</span>
                            @endif
                        </div>
                    </div>
                </div>

                @can('can-edit')
                <div class="bg-indigo-50/60 rounded-xl border border-indigo-100 p-4 text-center">
                    <p class="text-xs text-indigo-700 mb-2">Ingin memperbarui angka statistik pengikut secara manual?</p>
                    <button onclick="document.getElementById('modalStats').classList.remove('hidden')"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 py-1.5 rounded-lg transition font-medium inline-flex items-center gap-1.5">
                        <i class="fas fa-pen-to-square text-xs"></i> Update Angka Stats
                    </button>
                </div>
                @endcan
            </div>
        </div>
    </div>

    {{-- TAB 3: ALERT --}}
    <div x-show="tab==='alerts'" x-cloak class="mt-4">
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
            {{-- Recent alerts --}}
            <div class="xl:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-700 flex items-center gap-2">
                        <i class="fas fa-bell text-amber-500"></i> Alert Terbaru
                    </h3>
                    <a href="{{ route('alerts.index', ['account_id' => $account->id]) }}"
                       class="text-xs text-indigo-600 hover:underline">Semua alert →</a>
                </div>
                @forelse($recentAlerts as $alert)
                @php
                $alertStyle = match($alert->alert_type) {
                    'spike_up'   => ['bg-green-50',  'border-green-200', 'text-green-700',  'fa-arrow-trend-up',   'bg-green-100'],
                    'spike_down' => ['bg-red-50',    'border-red-200',   'text-red-700',    'fa-arrow-trend-down', 'bg-red-100'],
                    default      => ['bg-amber-50',  'border-amber-200', 'text-amber-700',  'fa-trophy',           'bg-amber-100'],
                };
                @endphp
                <div class="flex items-start gap-3 p-3 rounded-lg border mb-2 {{ $alertStyle[0] }} {{ $alertStyle[1] }}">
                    <div class="w-8 h-8 rounded-full {{ $alertStyle[4] }} flex items-center justify-center flex-shrink-0">
                        <i class="fas {{ $alertStyle[3] }} text-xs {{ $alertStyle[2] }}"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm {{ $alertStyle[2] }}">{{ $alert->message }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $alert->triggered_at->diffForHumans() }}</p>
                    </div>
                    @if(!$alert->is_read)
                    <span class="w-2 h-2 rounded-full bg-indigo-500 mt-1 flex-shrink-0"></span>
                    @endif
                </div>
                @empty
                <div class="flex flex-col items-center py-8 text-gray-400">
                    <i class="fas fa-bell-slash text-3xl mb-2 opacity-30"></i>
                    <p class="text-sm">Belum ada alert untuk akun ini.</p>
                </div>
                @endforelse
            </div>

            {{-- Alert settings --}}
            @can('can-edit')
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2">
                    <i class="fas fa-sliders-h text-indigo-500"></i> Pengaturan Alert
                </h3>
                <form method="POST" action="{{ route('alerts.settings.account', $account) }}">
                    @csrf
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Override setting global</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="override" value="1" class="sr-only peer"
                                       {{ $account->alertSetting ? 'checked' : '' }}
                                       onchange="document.getElementById('overrideFields').classList.toggle('hidden', !this.checked)">
                                <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:bg-indigo-600 transition-colors
                                            after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white
                                            after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full"></div>
                            </label>
                        </div>
                        <div id="overrideFields" class="{{ $account->alertSetting ? '' : 'hidden' }} space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Spike Naik (%)</label>
                                <input type="number" name="spike_up_threshold" step="0.1" min="0"
                                       value="{{ $account->alertSetting->spike_up_threshold ?? $globalSetting->spike_up_threshold }}"
                                       class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Spike Turun (%)</label>
                                <input type="number" name="spike_down_threshold" step="0.1" min="0"
                                       value="{{ $account->alertSetting->spike_down_threshold ?? $globalSetting->spike_down_threshold }}"
                                       class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label for="acc_alert_active" class="block text-xs font-medium text-gray-500 mb-1">Status Alert</label>
                                <label class="flex items-center gap-2 text-sm text-gray-600">
                                    <input type="checkbox" id="acc_alert_active" name="is_active" value="1"
                                           {{ ($account->alertSetting->is_active ?? true) ? 'checked' : '' }}
                                           class="rounded text-indigo-600">
                                    Alert aktif untuk akun ini
                                </label>
                            </div>
                        </div>
                        <button type="submit"
                                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2 rounded-lg transition">
                            Simpan Pengaturan
                        </button>
                    </div>
                </form>
            </div>
            @endcan
        </div>
    </div>

    {{-- TAB 4: RIWAYAT & LOG --}}
    <div x-show="tab==='info'" x-cloak class="mt-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <i class="fas fa-history text-purple-500"></i> Riwayat Perubahan Data Akun
            </h3>
            @if($account->activityLogs->count())
            <div class="space-y-3">
                @foreach($account->activityLogs as $log)
                <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg border border-gray-100 text-sm">
                    <div class="w-2.5 h-2.5 rounded-full bg-purple-500 mt-1.5 flex-shrink-0"></div>
                    <div class="flex-1 min-w-0">
                        <span class="font-bold text-gray-800 capitalize text-xs">{{ str_replace('_', ' ', $log->activity_type) }}</span>
                        @if($log->old_value || $log->new_value)
                        <div class="text-xs text-gray-600 mt-0.5">
                            <span class="text-gray-400 line-through">{{ $log->old_value }}</span>
                            @if($log->old_value && $log->new_value) <span class="text-gray-400 mx-1">→</span> @endif
                            <span class="text-indigo-700 font-medium">{{ $log->new_value }}</span>
                        </div>
                        @endif
                    </div>
                    <span class="text-[0.65rem] text-gray-400 flex-shrink-0">{{ $log->logged_at->diffForHumans() }}</span>
                </div>
                @endforeach
            </div>
            @else
            <p class="text-sm text-gray-400 text-center py-4">Belum ada riwayat perubahan.</p>
            @endif
        </div>
    </div>

</div>

{{-- MODAL: Update Stats --}}
@can('can-edit')
<div id="modalStats" role="dialog" aria-modal="true" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
     style="background:rgba(0,0,0,.5)">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800">
                <i class="fas fa-sync-alt text-indigo-500 mr-2"></i>Update Statistik
            </h3>
            <button onclick="document.getElementById('modalStats').classList.add('hidden')" aria-label="Tutup modal"
                    class="text-gray-400 hover:text-gray-600 text-lg leading-none">×</button>
        </div>
        <form method="POST" action="{{ route('accounts.update-stats', $account) }}">
            @csrf
            <div class="px-6 py-5 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Followers</label>
                    <input type="number" name="followers_count" min="0" required
                           value="{{ $account->followers_count }}"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Following</label>
                    <input type="number" name="following_count" min="0"
                           value="{{ $account->following_count }}"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah Postingan</label>
                    <input type="number" name="post_count" min="0"
                           value="{{ $account->post_count }}"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>
            <div class="flex gap-3 px-6 pb-5">
                <button type="button"
                        onclick="document.getElementById('modalStats').classList.add('hidden')"
                        class="flex-1 border border-gray-200 text-gray-600 hover:bg-gray-50 text-sm font-medium py-2 rounded-lg transition">
                    Batal
                </button>
                <button type="submit"
                        class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2 rounded-lg transition">
                    Simpan
                </button>
            </div>
        </form>
    </div>
</div>
@endcan

</div>

@endsection

@push('scripts')
<script>
@if($account->region)
const map = L.map('accountMap', {
    zoomControl: false,
    attributionControl: false,
    scrollWheelZoom: false,
}).setView([{{ $account->region->latitude }}, {{ $account->region->longitude }}], 10);
L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}', {
    attribution: 'Tiles © Esri',
    maxZoom: 18,
}).addTo(map);
L.control.zoom({ position: 'topright' }).addTo(map);
const color = '{{ $account->category->color ?? "#6366f1" }}';
const icon = L.divIcon({
    html: `<div style="background:${color};width:16px;height:16px;border-radius:50%;border:3px solid white;box-shadow:0 2px 6px rgba(0,0,0,.4)"></div>`,
    className: '', iconSize: [16,16], iconAnchor: [8,8]
});
L.marker([{{ $account->region->latitude }}, {{ $account->region->longitude }}], { icon })
    .bindPopup(@js(e($account->display_name) . '<br>' . e($account->region->name)))
    .addTo(map).openPopup();
@endif

@if($snapshots30->count() >= 2)
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: [{{ $snapshots30->map(fn($s) => '"' . \Carbon\Carbon::parse($s->recorded_at)->format('d M') . '"')->implode(',') }}],
        datasets: [{
            label: 'Followers',
            data: [{{ $snapshots30->pluck('followers_count')->implode(',') }}],
            borderColor: '#4F46E5',
            backgroundColor: 'rgba(79,70,229,0.08)',
            borderWidth: 2,
            pointRadius: 3,
            pointBackgroundColor: '#4F46E5',
            tension: 0.4,
            fill: true,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => ' ' + ctx.parsed.y.toLocaleString('id-ID') + ' followers'
                }
            }
        },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 11 }, maxTicksLimit: 8 } },
            y: {
                grid: { color: 'rgba(0,0,0,.05)' },
                ticks: {
                    font: { size: 11 },
                    callback: v => v >= 1000 ? (v/1000).toFixed(1)+'K' : v
                }
            }
        }
    }
});
@endif
</script>
@endpush
