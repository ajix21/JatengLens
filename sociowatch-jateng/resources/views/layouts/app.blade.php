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

            {{-- Section: Sistem --}}
            <div class="nav-section" x-show="sidebar">Info Sistem</div>

            <div class="nav-link cursor-default opacity-60" x-show="sidebar" style="font-size:.75rem">
                <i class="fas fa-circle nav-icon" style="color:#22c55e;font-size:.45rem"></i>
                <span>35 Kab/Kota · Jawa Tengah</span>
            </div>

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

            {{-- Right: status + avatar --}}
            <div class="flex items-center gap-3 flex-shrink-0">
                {{-- System status --}}
                <div class="hidden md:flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium"
                     style="background:#f0fdf4; color:#166534; border:1px solid #bbf7d0;">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                    Sistem Aktif
                </div>

                {{-- Version tag --}}
                <div class="hidden lg:block text-xs text-slate-400 font-mono">v1.0</div>

                {{-- Avatar --}}
                <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
                     style="background:linear-gradient(135deg,#4F46E5,#7C3AED)">
                    <i class="fas fa-user text-white" style="font-size:.65rem"></i>
                </div>
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
</script>
</body>
</html>
