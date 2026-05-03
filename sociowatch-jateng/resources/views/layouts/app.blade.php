<!DOCTYPE html>
<html lang="id" x-data="{ sidebarOpen: true, mobileMenu: false }">
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
                        brand: { DEFAULT: '#4F46E5', dark: '#3730A3', light: '#818CF8' }
                    }
                }
            }
        }
    </script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">

    <!-- Alpine.js CDN -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    @stack('styles')
    <style>
        [x-cloak] { display: none !important; }
        .sidebar-link { @apply flex items-center gap-3 px-4 py-2.5 rounded-lg text-slate-300 hover:bg-slate-700 hover:text-white transition-all duration-150 text-sm font-medium; }
        .sidebar-link.active { @apply bg-indigo-600 text-white; }
        .sidebar-group { @apply px-3 mb-1; }
    </style>
</head>
<body class="bg-gray-50 font-sans antialiased">

<div class="flex h-screen overflow-hidden">
    <!-- ===== SIDEBAR ===== -->
    <aside class="bg-slate-900 flex flex-col flex-shrink-0 transition-all duration-300"
           :class="sidebarOpen ? 'w-64' : 'w-16'"
           x-cloak>

        <!-- Logo -->
        <div class="flex items-center gap-3 px-4 py-5 border-b border-slate-700 min-h-[64px]">
            <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center flex-shrink-0">
                <i class="fas fa-satellite-dish text-white text-sm"></i>
            </div>
            <div x-show="sidebarOpen" x-transition.opacity class="overflow-hidden">
                <p class="text-white font-bold text-sm leading-tight">SocioWatch</p>
                <p class="text-indigo-400 text-xs">Jawa Tengah</p>
            </div>
        </div>

        <!-- Nav -->
        <nav class="flex-1 overflow-y-auto py-4 space-y-1">
            <div class="sidebar-group">
                <a href="{{ route('dashboard') }}"
                   class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i class="fas fa-chart-pie w-5 text-center flex-shrink-0"></i>
                    <span x-show="sidebarOpen" x-transition.opacity>Dashboard</span>
                </a>
            </div>
            <div class="sidebar-group">
                <a href="{{ route('map.index') }}"
                   class="sidebar-link {{ request()->routeIs('map.*') ? 'active' : '' }}">
                    <i class="fas fa-map-marked-alt w-5 text-center flex-shrink-0"></i>
                    <span x-show="sidebarOpen" x-transition.opacity>Peta Persebaran</span>
                </a>
            </div>

            <div x-show="sidebarOpen" class="px-4 pt-4 pb-1">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-widest">Manajemen</p>
            </div>
            <div class="sidebar-group">
                <a href="{{ route('accounts.index') }}"
                   class="sidebar-link {{ request()->routeIs('accounts.*') ? 'active' : '' }}">
                    <i class="fas fa-users w-5 text-center flex-shrink-0"></i>
                    <span x-show="sidebarOpen" x-transition.opacity>Akun Sosmed</span>
                </a>
            </div>
            <div class="sidebar-group">
                <a href="{{ route('categories.index') }}"
                   class="sidebar-link {{ request()->routeIs('categories.*') ? 'active' : '' }}">
                    <i class="fas fa-tags w-5 text-center flex-shrink-0"></i>
                    <span x-show="sidebarOpen" x-transition.opacity>Kategori</span>
                </a>
            </div>
            <div class="sidebar-group">
                <a href="{{ route('admins.index') }}"
                   class="sidebar-link {{ request()->routeIs('admins.*') ? 'active' : '' }}">
                    <i class="fas fa-id-card w-5 text-center flex-shrink-0"></i>
                    <span x-show="sidebarOpen" x-transition.opacity>Admin Akun</span>
                </a>
            </div>

            <div x-show="sidebarOpen" class="px-4 pt-4 pb-1">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-widest">Wilayah</p>
            </div>
            <div class="sidebar-group">
                <a href="{{ route('regions.index') }}"
                   class="sidebar-link {{ request()->routeIs('regions.*') ? 'active' : '' }}">
                    <i class="fas fa-map-pin w-5 text-center flex-shrink-0"></i>
                    <span x-show="sidebarOpen" x-transition.opacity>Kota / Kabupaten</span>
                </a>
            </div>
        </nav>

        <!-- Footer -->
        <div class="border-t border-slate-700 p-3">
            <button @click="sidebarOpen = !sidebarOpen"
                    class="w-full flex items-center justify-center gap-2 text-slate-400 hover:text-white text-xs py-1.5 rounded-lg hover:bg-slate-700 transition">
                <i class="fas" :class="sidebarOpen ? 'fa-chevron-left' : 'fa-chevron-right'"></i>
                <span x-show="sidebarOpen" x-transition.opacity>Tutup sidebar</span>
            </button>
        </div>
    </aside>

    <!-- ===== MAIN AREA ===== -->
    <div class="flex-1 flex flex-col overflow-hidden min-w-0">

        <!-- Header -->
        <header class="bg-white border-b border-gray-200 flex items-center justify-between px-6 h-16 flex-shrink-0">
            <div class="flex items-center gap-3">
                <!-- Mobile menu -->
                <button @click="mobileMenu = !mobileMenu" class="lg:hidden text-gray-500 hover:text-gray-700">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h1 class="text-gray-800 font-semibold text-lg leading-tight">@yield('page-title', 'Dashboard')</h1>
                    @hasSection('breadcrumb')
                    <nav class="text-xs text-gray-400">@yield('breadcrumb')</nav>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-3 text-sm text-gray-500">
                <span class="hidden sm:block">
                    <i class="fas fa-circle text-green-400 text-xs mr-1"></i>Jawa Tengah — 35 Kota/Kabupaten
                </span>
                <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center">
                    <i class="fas fa-user text-indigo-600 text-xs"></i>
                </div>
            </div>
        </header>

        <!-- Flash messages -->
        @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-transition
             class="mx-6 mt-4 bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 flex items-center justify-between text-sm">
            <span><i class="fas fa-check-circle mr-2 text-green-500"></i>{{ session('success') }}</span>
            <button @click="show = false" class="text-green-400 hover:text-green-600"><i class="fas fa-times"></i></button>
        </div>
        @endif
        @if(session('error'))
        <div x-data="{ show: true }" x-show="show" x-transition
             class="mx-6 mt-4 bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 flex items-center justify-between text-sm">
            <span><i class="fas fa-exclamation-circle mr-2 text-red-500"></i>{{ session('error') }}</span>
            <button @click="show = false" class="text-red-400 hover:text-red-600"><i class="fas fa-times"></i></button>
        </div>
        @endif

        <!-- Content -->
        <main class="flex-1 overflow-auto @yield('content-class', 'p-6')">
            @yield('content')
        </main>

    </div>
</div>

@stack('scripts')
</body>
</html>
