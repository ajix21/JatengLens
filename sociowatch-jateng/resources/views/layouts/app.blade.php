<!DOCTYPE html>
<html lang="id" x-data="appShell()" x-init="init()">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SocioWatch Jateng')</title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        navy:  { 950:'#0a0f1e', 900:'#0d1424', 800:'#111827', 700:'#1a2540', 600:'#1e2d4d', 500:'#243660' },
                        brand: { DEFAULT:'#4F46E5', dark:'#3730A3', light:'#818CF8', glow:'rgba(79,70,229,0.25)' }
                    },
                    boxShadow: {
                        'brand-glow': '0 0 20px rgba(79,70,229,0.35)',
                    }
                }
            }
        }
    </script>

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">

    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    @stack('styles')

    <style>
        /* ── base ── */
        [x-cloak] { display: none !important; }
        :root {
            --sidebar-w: 256px;
            --sidebar-collapsed: 64px;
            --header-h: 64px;
            --navy-900: #0d1424;
            --navy-800: #111827;
            --navy-700: #1a2540;
        }
        body { background: #f1f5f9; }

        /* ── sidebar links ── */
        .nav-link {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 12px; border-radius: 10px;
            color: #94a3b8; font-size: 0.8125rem; font-weight: 500;
            transition: background 150ms, color 150ms;
            white-space: nowrap; overflow: hidden;
            text-decoration: none;
        }
        .nav-link:hover { background: rgba(255,255,255,.07); color: #e2e8f0; }
        .nav-link.active { background: #4F46E5; color: #fff; box-shadow: 0 4px 14px rgba(79,70,229,.45); }
        .nav-link .nav-icon { width: 18px; text-align: center; flex-shrink: 0; font-size: 0.875rem; }

        /* ── nav section label ── */
        .nav-section {
            font-size: 0.65rem; font-weight: 700; letter-spacing: .09em;
            text-transform: uppercase; color: #334155;
            padding: 16px 14px 6px; white-space: nowrap; overflow: hidden;
        }

        /* ── UTAMA badge ── */
        .badge-utama {
            font-size: 0.55rem; font-weight: 800; letter-spacing: .07em;
            background: linear-gradient(135deg,#f59e0b,#ef4444);
            color: #fff; padding: 2px 6px; border-radius: 4px;
            text-transform: uppercase; flex-shrink: 0;
        }

        /* ── scrollbar ── */
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 9999px; }

        /* ── breadcrumb separator ── */
        .breadcrumb-sep { color: #cbd5e1; margin: 0 5px; }

        /* ── flash toast ── */
        .flash-toast {
            position: fixed; bottom: 24px; right: 24px; z-index: 9999;
            min-width: 300px; max-width: 420px;
        }
    </style>
</head>

<body class="font-sans antialiased">
<div class="flex h-screen overflow-hidden">

    {{-- ═══════════════════════════════════════════════════════
         SIDEBAR — dark navy professional
    ═══════════════════════════════════════════════════════ --}}
    <aside id="sidebar"
           class="flex flex-col flex-shrink-0 transition-all duration-300 ease-in-out overflow-hidden"
           :class="sidebar ? 'w-64' : 'w-16'"
           style="background: linear-gradient(180deg, #0d1424 0%, #111827 100%); border-right: 1px solid rgba(255,255,255,.06);"
           x-cloak>

        {{-- ── Logo / Brand ── --}}
        <div class="flex items-center gap-3 px-4 min-h-[64px] flex-shrink-0"
             style="border-bottom: 1px solid rgba(255,255,255,.06);">

            {{-- Icon --}}
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background: linear-gradient(135deg,#4F46E5,#7C3AED); box-shadow: 0 0 16px rgba(79,70,229,.5)">
                <i class="fas fa-satellite-dish text-white" style="font-size:.85rem"></i>
            </div>

            {{-- Brand text --}}
            <div x-show="sidebar" x-transition:enter="transition-opacity duration-200"
                 x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity duration-100"
                 x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                 class="overflow-hidden">
                <p class="text-white font-bold leading-tight" style="font-size:.9rem;letter-spacing:-.01em">SocioWatch</p>
                <p style="font-size:.65rem;color:#818CF8;font-weight:600;letter-spacing:.04em;text-transform:uppercase">
                    Jawa Tengah
                </p>
            </div>
        </div>

        {{-- ── Navigation ── --}}
        <nav class="flex-1 overflow-y-auto py-3 space-y-0.5 px-2">

            {{-- Dashboard --}}
            <a href="{{ route('dashboard') }}"
               class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="fas fa-chart-pie nav-icon"></i>
                <span x-show="sidebar">Dashboard</span>
            </a>

            {{-- Peta Persebaran — UTAMA --}}
            <a href="{{ route('map.index') }}"
               class="nav-link {{ request()->routeIs('map.*') ? 'active' : '' }}"
               style="{{ request()->routeIs('map.*') ? '' : 'border: 1px solid rgba(245,158,11,.2);' }}">
                <i class="fas fa-map-marked-alt nav-icon" style="{{ request()->routeIs('map.*') ? '' : 'color:#f59e0b' }}"></i>
                <span x-show="sidebar" class="flex-1">Peta Persebaran</span>
                <span x-show="sidebar" class="badge-utama">UTAMA</span>
            </a>

            {{-- Section: Manajemen --}}
            <div class="nav-section" x-show="sidebar">Manajemen Data</div>

            <a href="{{ route('accounts.index') }}"
               class="nav-link {{ request()->routeIs('accounts.*') ? 'active' : '' }}">
                <i class="fas fa-users nav-icon"></i>
                <span x-show="sidebar">Manajemen Akun</span>
            </a>

            <a href="{{ route('regions.index') }}"
               class="nav-link {{ request()->routeIs('regions.*') ? 'active' : '' }}">
                <i class="fas fa-map-pin nav-icon"></i>
                <span x-show="sidebar">Rekapitulasi Wilayah</span>
            </a>

            <a href="{{ route('categories.index') }}"
               class="nav-link {{ request()->routeIs('categories.*') ? 'active' : '' }}">
                <i class="fas fa-tags nav-icon"></i>
                <span x-show="sidebar">Kategori</span>
            </a>

            <a href="{{ route('admins.index') }}"
               class="nav-link {{ request()->routeIs('admins.*') ? 'active' : '' }}">
                <i class="fas fa-id-card nav-icon"></i>
                <span x-show="sidebar">Admin Akun</span>
            </a>

            {{-- Section: Monitoring --}}
            <div class="nav-section" x-show="sidebar">Monitoring</div>

            <a href="{{ route('analytics') }}"
               class="nav-link {{ request()->routeIs('analytics') ? 'active' : '' }}">
                <i class="fas fa-chart-line nav-icon"></i>
                <span x-show="sidebar">Analytics</span>
            </a>

            <a href="{{ route('alerts.index') }}"
               class="nav-link {{ request()->routeIs('alerts.*') ? 'active' : '' }}">
                <i class="fas fa-bell nav-icon"></i>
                <span x-show="sidebar" class="flex-1">Notifikasi</span>
                @php $unread = \App\Models\Alert::where('is_read', false)->count(); @endphp
                @if($unread > 0)
                <span x-show="sidebar" class="text-white text-[0.6rem] font-bold px-1.5 py-0.5 rounded-full"
                      style="background:#ef4444">{{ $unread > 99 ? '99+' : $unread }}</span>
                @endif
            </a>

            <a href="{{ route('accounts.import') }}"
               class="nav-link {{ request()->routeIs('accounts.import*') ? 'active' : '' }}">
                <i class="fas fa-file-import nav-icon"></i>
                <span x-show="sidebar">Import Akun</span>
            </a>

            {{-- Section: Konten --}}
            <div class="nav-section" x-show="sidebar">Konten</div>

            <a href="{{ route('posts.index') }}"
               class="nav-link {{ request()->routeIs('posts.index') || (request()->routeIs('posts.*') && !request()->routeIs('posts.search') && !request()->routeIs('posts.flagged*') && !request()->routeIs('posts.import*')) ? 'active' : '' }}">
                <i class="fas fa-newspaper nav-icon"></i>
                <span x-show="sidebar">Semua Postingan</span>
            </a>

            <a href="{{ route('posts.search') }}"
               class="nav-link {{ request()->routeIs('posts.search') ? 'active' : '' }}">
                <i class="fas fa-search nav-icon"></i>
                <span x-show="sidebar">Cari Postingan</span>
            </a>

            <a href="{{ route('posts.flagged') }}"
               class="nav-link {{ request()->routeIs('posts.flagged*') ? 'active' : '' }}">
                <i class="fas fa-flag nav-icon"></i>
                <span x-show="sidebar">Postingan Terpantau</span>
            </a>

            <a href="{{ route('posts.import') }}"
               class="nav-link {{ request()->routeIs('posts.import*') ? 'active' : '' }}">
                <i class="fas fa-file-upload nav-icon"></i>
                <span x-show="sidebar">Import Postingan</span>
            </a>

            @if(auth()->user()->role !== 'viewer')
            <a href="{{ route('keywords.manage') }}"
               class="nav-link {{ request()->routeIs('keywords.*') ? 'active' : '' }}">
                <i class="fas fa-hashtag nav-icon"></i>
                <span x-show="sidebar">Kelola Keyword</span>
            </a>
            @endif

            {{-- Section: Sistem --}}
            <div class="nav-section" x-show="sidebar">Info Sistem</div>

            <div class="nav-link cursor-default opacity-60" x-show="sidebar" style="font-size:.75rem">
                <i class="fas fa-circle nav-icon" style="color:#22c55e;font-size:.45rem"></i>
                <span>35 Kab/Kota · Jawa Tengah</span>
            </div>

            {{-- Superadmin only: user management --}}
            @can('manage-users')
            <div class="nav-section" x-show="sidebar">Administrasi</div>

            <a href="{{ route('users.index') }}"
               class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                <i class="fas fa-user-cog nav-icon"></i>
                <span x-show="sidebar">Manajemen User</span>
            </a>

            <a href="{{ route('users.activity-log') }}"
               class="nav-link {{ request()->routeIs('users.activity-log') ? 'active' : '' }}">
                <i class="fas fa-clipboard-list nav-icon"></i>
                <span x-show="sidebar">Log Aktivitas</span>
            </a>

            <a href="{{ route('api-tokens.index') }}"
               class="nav-link {{ request()->routeIs('api-tokens.*') ? 'active' : '' }}">
                <i class="fas fa-key nav-icon"></i>
                <span x-show="sidebar">API Tokens</span>
            </a>
            @endcan

        </nav>

        {{-- ── Collapse button ── --}}
        <div class="flex-shrink-0 px-2 pb-3" style="border-top: 1px solid rgba(255,255,255,.06); padding-top: 10px;">
            <button @click="sidebar = !sidebar; saveSidebar()"
                    class="nav-link w-full justify-center"
                    style="padding: 8px;">
                <i class="fas nav-icon" :class="sidebar ? 'fa-chevron-left' : 'fa-chevron-right'"
                   style="width:auto;margin:0"></i>
                <span x-show="sidebar" x-transition.opacity style="font-size:.75rem">Tutup sidebar</span>
            </button>
        </div>
    </aside>

    {{-- ═══════════════════════════════════════════════════════
         MAIN WRAPPER
    ═══════════════════════════════════════════════════════ --}}
    <div class="flex-1 flex flex-col overflow-hidden min-w-0">

        {{-- ── Header ── --}}
        <header class="flex items-center justify-between px-6 flex-shrink-0"
                style="height:var(--header-h); background:#fff; border-bottom:1px solid #e2e8f0; box-shadow:0 1px 3px rgba(0,0,0,.06);">

            {{-- Left: page title + breadcrumb --}}
            <div class="flex items-center gap-3 min-w-0">
                {{-- Mobile toggle --}}
                <button @click="sidebar = !sidebar" class="text-slate-400 hover:text-slate-600 lg:hidden">
                    <i class="fas fa-bars"></i>
                </button>

                <div class="min-w-0">
                    {{-- Breadcrumb --}}
                    <nav class="flex items-center text-xs text-slate-400 mb-0.5" aria-label="breadcrumb">
                        <a href="{{ route('dashboard') }}" class="hover:text-indigo-600 transition">
                            <i class="fas fa-home"></i>
                        </a>
                        @hasSection('breadcrumb')
                        <span class="breadcrumb-sep">/</span>
                        @yield('breadcrumb')
                        @endif
                    </nav>
                    {{-- Page title --}}
                    <h1 class="font-semibold text-slate-800 truncate leading-tight"
                        style="font-size:1rem">
                        @yield('page-title', 'Dashboard')
                    </h1>
                </div>
            </div>

            {{-- Right: user info + logout --}}
            <div class="flex items-center gap-3 flex-shrink-0">
                {{-- System status --}}
                <div class="hidden md:flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium"
                     style="background:#f0fdf4; color:#166534; border:1px solid #bbf7d0;">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                    Sistem Aktif
                </div>

                {{-- Bell notification --}}
                <div x-data="bellNotif()" x-init="init()" class="relative">
                    <button @click="open = !open; if(open) fetchAlerts()"
                            class="relative w-9 h-9 flex items-center justify-center rounded-full hover:bg-slate-100 transition text-slate-500">
                        <i class="fas fa-bell text-base"></i>
                        <span x-show="unreadCount > 0" x-cloak
                              class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] flex items-center justify-center rounded-full text-white font-bold"
                              style="background:#ef4444; font-size:.6rem; padding:0 3px;"
                              x-text="unreadCount > 99 ? '99+' : unreadCount"></span>
                    </button>

                    {{-- Dropdown --}}
                    <div x-show="open" x-cloak @click.outside="open = false"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                         class="absolute right-0 mt-2 w-80 rounded-xl shadow-xl border overflow-hidden z-50"
                         style="background:#fff; border-color:#e2e8f0; top:100%">

                        {{-- Header --}}
                        <div class="flex items-center justify-between px-4 py-3"
                             style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                            <span class="text-sm font-semibold text-slate-700">Notifikasi</span>
                            <div class="flex items-center gap-2">
                                <span x-show="unreadCount > 0" x-cloak
                                      class="text-xs font-bold px-2 py-0.5 rounded-full text-white"
                                      style="background:#ef4444;" x-text="unreadCount + ' belum dibaca'"></span>
                                <a href="{{ route('alerts.index') }}"
                                   class="text-xs text-indigo-600 font-medium hover:underline">Semua</a>
                            </div>
                        </div>

                        {{-- Alert list --}}
                        <div class="divide-y divide-slate-100 max-h-72 overflow-y-auto">
                            <template x-if="loading">
                                <div class="flex items-center justify-center py-8">
                                    <i class="fas fa-spinner fa-spin text-indigo-500 mr-2"></i>
                                    <span class="text-xs text-slate-400">Memuat…</span>
                                </div>
                            </template>
                            <template x-if="!loading && alerts.length === 0">
                                <div class="flex flex-col items-center py-8 text-slate-400">
                                    <i class="fas fa-bell-slash text-2xl mb-2"></i>
                                    <span class="text-xs">Tidak ada notifikasi baru</span>
                                </div>
                            </template>
                            <template x-for="alert in alerts" :key="alert.id">
                                <div class="flex items-start gap-3 px-4 py-3 hover:bg-slate-50 transition"
                                     :class="!alert.is_read ? 'bg-indigo-50/60' : ''">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5"
                                         :style="alert.alert_type === 'spike_up' ? 'background:#dcfce7' : (alert.alert_type === 'spike_down' ? 'background:#fee2e2' : 'background:#fef3c7')">
                                        <i class="fas text-xs"
                                           :class="alert.alert_type === 'spike_up' ? 'fa-arrow-trend-up text-green-600' : (alert.alert_type === 'spike_down' ? 'fa-arrow-trend-down text-red-600' : 'fa-trophy text-amber-600')"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs text-slate-700 leading-snug" x-text="alert.message"></p>
                                        <p class="text-[0.65rem] text-slate-400 mt-0.5" x-text="alert.triggered_at_human"></p>
                                    </div>
                                    <div x-show="!alert.is_read" class="w-2 h-2 rounded-full bg-indigo-500 mt-1 flex-shrink-0"></div>
                                </div>
                            </template>
                        </div>

                        {{-- Footer --}}
                        <div class="px-4 py-2.5 text-center" style="border-top:1px solid #e2e8f0; background:#f8fafc;">
                            <a href="{{ route('alerts.index') }}"
                               class="text-xs text-indigo-600 font-medium hover:underline">
                                Lihat semua notifikasi →
                            </a>
                        </div>
                    </div>
                </div>

                {{-- User name + role badge --}}
                <div class="hidden md:flex items-center gap-2">
                    <div class="text-right">
                        <div class="text-xs font-semibold text-slate-700 leading-tight">{{ auth()->user()->name }}</div>
                        @php
                            $roleBadge = match(auth()->user()->role) {
                                'superadmin' => ['bg' => '#fef3c7', 'color' => '#92400e', 'label' => 'SUPERADMIN'],
                                'admin'      => ['bg' => '#dbeafe', 'color' => '#1e40af', 'label' => 'ADMIN'],
                                default      => ['bg' => '#f1f5f9', 'color' => '#475569', 'label' => 'VIEWER'],
                            };
                        @endphp
                        <span class="text-[0.6rem] font-bold px-1.5 py-0.5 rounded"
                              style="background:{{ $roleBadge['bg'] }};color:{{ $roleBadge['color'] }}">
                            {{ $roleBadge['label'] }}
                        </span>
                    </div>

                    {{-- Avatar --}}
                    <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
                         style="background:linear-gradient(135deg,#4F46E5,#7C3AED)">
                        <i class="fas fa-user text-white" style="font-size:.65rem"></i>
                    </div>
                </div>

                {{-- Logout --}}
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-red-600 border border-red-200 hover:bg-red-50 transition"
                            title="Logout">
                        <i class="fas fa-sign-out-alt"></i>
                        <span class="hidden md:inline">Logout</span>
                    </button>
                </form>
            </div>
        </header>

        {{-- ── Flash Messages ── --}}
        @if(session('success') || session('error') || session('warning'))
        <div class="flash-toast space-y-2" x-data x-init="setTimeout(() => $el.remove(), 5000)">
            @if(session('success'))
            <div class="flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg text-sm font-medium"
                 style="background:#fff; border-left:4px solid #22c55e; border:1px solid #bbf7d0;">
                <i class="fas fa-check-circle text-green-500 flex-shrink-0"></i>
                <span class="text-green-800">{{ session('success') }}</span>
            </div>
            @endif
            @if(session('error'))
            <div class="flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg text-sm font-medium"
                 style="background:#fff; border-left:4px solid #ef4444; border:1px solid #fecaca;">
                <i class="fas fa-exclamation-circle text-red-500 flex-shrink-0"></i>
                <span class="text-red-800">{{ session('error') }}</span>
            </div>
            @endif
            @if(session('warning'))
            <div class="flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg text-sm font-medium"
                 style="background:#fff; border-left:4px solid #f59e0b; border:1px solid #fde68a;">
                <i class="fas fa-exclamation-triangle text-amber-500 flex-shrink-0"></i>
                <span class="text-amber-800">{{ session('warning') }}</span>
            </div>
            @endif
        </div>
        @endif

        {{-- ── Page Content ── --}}
        <main class="flex-1 overflow-auto @yield('content-class', 'p-6')">
            @yield('content')
        </main>

    </div>
</div>

@stack('scripts')

<script>
function appShell() {
    return {
        sidebar: localStorage.getItem('sw_sidebar') !== 'false',
        init() { /* Alpine init hook */ },
        saveSidebar() {
            localStorage.setItem('sw_sidebar', this.sidebar);
        }
    }
}

function bellNotif() {
    return {
        open: false,
        loading: false,
        unreadCount: 0,
        alerts: [],
        pollTimer: null,

        init() {
            this.fetchCount();
            this.pollTimer = setInterval(() => this.fetchCount(), 60000);
        },

        fetchCount() {
            fetch('{{ route('api.alerts.unread') }}', {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(data => { this.unreadCount = data.count; })
            .catch(() => {});
        },

        fetchAlerts() {
            this.loading = true;
            fetch('{{ route('api.alerts.unread') }}', {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(data => {
                this.unreadCount = data.count;
                this.alerts = data.alerts;
            })
            .catch(() => {})
            .finally(() => { this.loading = false; });
        },
    }
}
</script>
</body>
</html>
