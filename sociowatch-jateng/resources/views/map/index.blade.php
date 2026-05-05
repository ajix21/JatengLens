@extends('layouts.app')
@section('title', 'Peta Persebaran — SocioWatch Jateng')
@section('page-title', 'Peta Persebaran Akun')
@section('content-class', 'p-0')

@section('content')
<div class="flex h-full" x-data="mapApp()" x-init="init()" style="height: calc(100vh - 64px)">

    {{-- ===== FILTER PANEL (left sidebar) ===== --}}
    <div class="bg-white border-r border-gray-200 flex flex-col overflow-hidden transition-all duration-300"
         :class="filterOpen ? 'w-72' : 'w-0'">
        <div class="flex-1 overflow-y-auto p-4 min-w-[288px]">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-gray-700 text-sm flex items-center gap-2">
                    <i class="fas fa-filter text-indigo-500"></i> Filter
                </h3>
                <button @click="applyFilters()"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 py-1.5 rounded-lg transition">
                    Terapkan
                </button>
            </div>

            {{-- Filter: Kategori --}}
            <div class="mb-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Kategori</p>
                @foreach($categories as $cat)
                <label class="flex items-center gap-2 py-1 cursor-pointer hover:text-gray-700">
                    <input type="checkbox" value="{{ $cat->id }}" x-model="filters.categories"
                           class="rounded border-gray-300 text-indigo-600">
                    <span class="w-3 h-3 rounded-full flex-shrink-0" style="background:{{ $cat->color }}"></span>
                    <span class="text-sm text-gray-600">{{ $cat->name }}</span>
                </label>
                @endforeach
            </div>

            {{-- Filter: Platform --}}
            <div class="mb-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Platform</p>
                @php
                $pIcons = [
                    'instagram' => ['fa-instagram','text-pink-500'],
                    'twitter'   => ['fa-twitter','text-sky-500'],
                    'facebook'  => ['fa-facebook','text-blue-600'],
                    'tiktok'    => ['fa-tiktok','text-gray-800'],
                    'youtube'   => ['fa-youtube','text-red-600'],
                ];
                @endphp
                @foreach($platforms as $plat)
                <label class="flex items-center gap-2 py-1 cursor-pointer hover:text-gray-700">
                    <input type="checkbox" value="{{ $plat }}" x-model="filters.platforms"
                           class="rounded border-gray-300 text-indigo-600">
                    <i class="fab {{ $pIcons[$plat][0] }} {{ $pIcons[$plat][1] }} w-4 text-center text-sm"></i>
                    <span class="text-sm text-gray-600 capitalize">{{ $plat }}</span>
                </label>
                @endforeach
            </div>

            {{-- Filter: Kota/Kabupaten --}}
            <div class="mb-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Kota / Kabupaten</p>
                <select x-model="filters.region_id"
                        class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                    <option value="">Semua Wilayah</option>
                    @foreach($regions as $reg)
                    <option value="{{ $reg->id }}">{{ $reg->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Filter: Followers Range --}}
            <div class="mb-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Followers</p>
                <div class="flex gap-2">
                    <div class="flex-1">
                        <label class="text-xs text-gray-400">Min</label>
                        <input type="number" x-model="filters.min_followers" placeholder="0" min="0"
                               class="w-full text-sm border border-gray-200 rounded px-2 py-1.5 focus:ring-1 focus:ring-indigo-300 focus:outline-none">
                    </div>
                    <div class="flex-1">
                        <label class="text-xs text-gray-400">Max</label>
                        <input type="number" x-model="filters.max_followers" placeholder="∞" min="0"
                               class="w-full text-sm border border-gray-200 rounded px-2 py-1.5 focus:ring-1 focus:ring-indigo-300 focus:outline-none">
                    </div>
                </div>
            </div>

            {{-- Reset --}}
            <button @click="resetFilters()"
                    class="w-full text-xs text-gray-400 hover:text-gray-600 py-2 border border-dashed border-gray-200 rounded-lg mt-2">
                <i class="fas fa-times mr-1"></i> Reset Filter
            </button>
        </div>
    </div>

    {{-- ===== MAP AREA ===== --}}
    <div class="flex-1 flex flex-col min-w-0 relative">

        {{-- Toolbar --}}
        <div class="bg-white border-b border-gray-200 px-4 py-2 flex items-center gap-3 flex-shrink-0 z-10">

            {{-- Toggle filter panel --}}
            <button @click="filterOpen = !filterOpen"
                    class="flex items-center gap-2 text-sm text-gray-600 hover:text-indigo-600 border border-gray-200 rounded-lg px-3 py-1.5 transition"
                    :class="filterOpen ? 'bg-indigo-50 border-indigo-200 text-indigo-600' : ''">
                <i class="fas fa-filter text-xs"></i>
                <span class="hidden sm:inline">Filter</span>
                <span x-show="activeFilterCount > 0"
                      class="bg-indigo-600 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center"
                      x-text="activeFilterCount"></span>
            </button>

            {{-- Toggle labels --}}
            <button @click="toggleLabels()"
                    class="flex items-center gap-2 text-sm border border-gray-200 rounded-lg px-3 py-1.5 transition"
                    :class="showLabels ? 'bg-amber-50 border-amber-300 text-amber-700' : 'text-gray-600 hover:bg-gray-50'">
                <i class="fas fa-font text-xs"></i>
                <span class="hidden sm:inline">Nama Wilayah</span>
            </button>

            {{-- Account count + Export --}}
            <div class="flex items-center gap-3 ml-auto">
                <div class="text-xs text-gray-500">
                    <span x-show="loading" class="text-indigo-500">
                        <i class="fas fa-spinner fa-spin mr-1"></i>Loading...
                    </span>
                    <span x-show="!loading">
                        <span class="font-semibold text-gray-700" x-text="accountCount"></span> akun ditampilkan
                    </span>
                </div>
                <button @click="exportMapData()"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-emerald-700 border border-emerald-200 hover:bg-emerald-50 transition">
                    <i class="fas fa-file-excel"></i> Export
                </button>
            </div>
        </div>

        {{-- Map container --}}
        <div id="mainMap" class="flex-1"></div>

        {{-- Legend --}}
        <div class="absolute bottom-4 right-4 bg-white rounded-xl shadow-lg border border-gray-100 p-3 z-[1000]">
            <p class="text-xs font-semibold text-gray-600 mb-2">Legenda</p>

            {{-- Wilayah --}}
            <div class="space-y-1 mb-3 pb-2 border-b border-gray-100">
                <div class="flex items-center gap-2 text-xs text-gray-600">
                    <span class="w-3 h-3 rounded-full flex-shrink-0 border-2"
                          style="background:#bbf7d0;border-color:#16a34a"></span>
                    Kabupaten
                </div>
                <div class="flex items-center gap-2 text-xs text-gray-600">
                    <span class="w-3 h-3 rounded-full flex-shrink-0 border-2"
                          style="background:#fde68a;border-color:#d97706"></span>
                    Kota
                </div>
            </div>

            {{-- Kategori akun --}}
            <p class="text-xs font-semibold text-gray-500 mb-1">Kategori Akun</p>
            <div class="space-y-1">
                @foreach($categories as $cat)
                <div class="flex items-center gap-2 text-xs text-gray-600">
                    <span class="w-3 h-3 rounded-full flex-shrink-0" style="background:{{ $cat->color }}"></span>
                    {{ $cat->name }}
                </div>
                @endforeach
            </div>
        </div>

        {{-- Region popup panel --}}
        <div x-show="selectedRegion" x-transition x-cloak
             class="absolute top-12 right-4 w-64 bg-white rounded-xl shadow-xl border border-gray-100 p-4 z-[1000]">
            <div class="flex items-start justify-between mb-2">
                <div>
                    <h4 class="font-semibold text-gray-800 text-sm" x-text="selectedRegion?.name"></h4>
                    <span class="text-xs px-1.5 py-0.5 rounded font-medium"
                          :style="selectedRegion?.type === 'kota'
                              ? 'background:#fef3c7;color:#92400e'
                              : 'background:#dcfce7;color:#166534'"
                          x-text="selectedRegion?.type === 'kota' ? 'Kota' : 'Kabupaten'"></span>
                </div>
                <button @click="selectedRegion = null" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
            <div class="grid grid-cols-2 gap-2 mt-3">
                <div class="bg-gray-50 rounded-lg p-2 text-center">
                    <p class="text-lg font-bold text-indigo-600" x-text="selectedRegion?.account_count ?? 0"></p>
                    <p class="text-xs text-gray-500">Akun</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-2 text-center">
                    <p class="text-sm font-bold text-emerald-600"
                       x-text="formatNum(selectedRegion?.total_followers ?? 0)"></p>
                    <p class="text-xs text-gray-500">Followers</p>
                </div>
            </div>
            <a :href="`/regions/${selectedRegion?.id}`"
               class="mt-3 block w-full text-center text-xs bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg py-2 transition">
                Lihat Semua Akun <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const URL_MARKERS    = '{{ route("map.markers") }}';
const URL_CHOROPLETH = '{{ route("map.choropleth") }}';
const INIT_REGION_ID = '{{ request("region_id") }}';

function mapApp() {
    return {
        filterOpen: true,
        loading: false,
        showLabels: true,
        accountCount: 0,
        selectedRegion: null,
        filters: {
            categories: [],
            platforms: [],
            region_id: INIT_REGION_ID || '',
            min_followers: '',
            max_followers: '',
        },
        map: null,
        markerLayer: null,
        regionLayer: null,
        labelLayer: null,

        get activeFilterCount() {
            return [
                this.filters.categories.length > 0,
                this.filters.platforms.length > 0,
                !!this.filters.region_id,
                !!this.filters.min_followers,
                !!this.filters.max_followers,
            ].filter(Boolean).length;
        },

        // ── init ────────────────────────────────────────────────
        async init() {
            this.map = L.map('mainMap', {
                center: [-7.150975, 110.140259],
                zoom: 8, minZoom: 7, maxZoom: 15,
                zoomControl: true,
            });

            // Tile layer — light style tanpa label bawaan
            L.tileLayer('https://{s}.basemaps.cartocdn.com/light_nolabels/{z}/{x}/{y}{r}.png', {
                attribution: '© OpenStreetMap © CARTO',
                subdomains: 'abcd', maxZoom: 19,
            }).addTo(this.map);

            // Marker cluster layer untuk akun
            this.markerLayer = L.markerClusterGroup({
                maxClusterRadius: 50,
                showCoverageOnHover: false,
                iconCreateFunction(cluster) {
                    const n = cluster.getChildCount();
                    return L.divIcon({
                        html: `<div style="background:#4F46E5;color:#fff;border-radius:50%;width:34px;height:34px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;box-shadow:0 3px 8px rgba(79,70,229,.5)">${n}</div>`,
                        className: '', iconSize: [34, 34],
                    });
                },
            });

            // Load region + akun secara paralel
            await Promise.all([
                this.loadRegions(),
                this.loadMarkers(),
            ]);
        },

        // ── Load region data → render dot + label per kota/kab ──
        async loadRegions() {
            let regions = [];
            try {
                const res  = await fetch(URL_CHOROPLETH);
                const data = await res.json();
                regions = data.regions ?? [];
            } catch (e) {
                console.warn('Region data fetch failed:', e);
                return;
            }

            this.regionLayer = L.layerGroup();
            this.labelLayer  = L.layerGroup();
            const self = this;

            regions.forEach(r => {
                if (!r.latitude || !r.longitude) return;

                const isKota    = r.type === 'kota';
                const fillColor = isKota ? '#fde68a' : '#bbf7d0';
                const edgeColor = isKota ? '#d97706' : '#16a34a';

                // Dot marker — circleMarker tanpa GeoJSON
                const dot = L.circleMarker([r.latitude, r.longitude], {
                    radius:      isKota ? 9 : 8,
                    fillColor,
                    color:       edgeColor,
                    weight:      2,
                    fillOpacity: 0.9,
                    opacity:     1,
                });

                dot.on('click', function () {
                    self.selectedRegion = {
                        id:             r.id,
                        name:           r.name,
                        type:           r.type,
                        account_count:  r.account_count,
                        total_followers: r.total_followers,
                    };
                });

                dot.bindTooltip(
                    `<b>${r.name}</b><br><span style="color:#6b7280">Akun: <b>${r.account_count}</b> · Followers: <b>${self.formatNum(r.total_followers)}</b></span>`,
                    { sticky: true, className: 'region-tooltip' }
                );

                this.regionLayer.addLayer(dot);

                // Label teks di atas dot
                const shortName = r.name.replace(/^(Kabupaten|Kota)\s+/i, '');
                const label = L.marker([r.latitude, r.longitude], {
                    icon: L.divIcon({
                        html: `<div class="region-label${isKota ? ' kota-label' : ''}">${shortName}</div>`,
                        className: '',
                        iconAnchor: [0, 18],
                    }),
                    interactive: false,
                    zIndexOffset: -100,
                });
                this.labelLayer.addLayer(label);
            });

            this.regionLayer.addTo(this.map);
            if (this.showLabels) {
                this.labelLayer.addTo(this.map);
            }
        },

        // ── Toggle nama wilayah ──────────────────────────────────
        toggleLabels() {
            this.showLabels = !this.showLabels;
            if (this.labelLayer) {
                this.showLabels
                    ? this.labelLayer.addTo(this.map)
                    : this.map.removeLayer(this.labelLayer);
            }
        },

        // ── GET /map/markers ─────────────────────────────────────
        buildParams() {
            const p = new URLSearchParams();
            this.filters.categories.forEach(c  => p.append('categories[]', c));
            this.filters.platforms.forEach(pl  => p.append('platforms[]', pl));
            if (this.filters.region_id)     p.set('region_id',    this.filters.region_id);
            if (this.filters.min_followers) p.set('min_followers', this.filters.min_followers);
            if (this.filters.max_followers) p.set('max_followers', this.filters.max_followers);
            return p;
        },

        async loadMarkers() {
            this.loading = true;
            try {
                const res  = await fetch(`${URL_MARKERS}?${this.buildParams()}`);
                const data = await res.json();
                this.accountCount = data.count;
                this.renderMarkers(data.markers);
            } catch (e) {
                console.error('Markers fetch failed:', e);
            } finally {
                this.loading = false;
            }
        },

        // ── Render account markers ───────────────────────────────
        renderMarkers(markers) {
            this.markerLayer.clearLayers();

            const platEmoji = {
                instagram: '📷', twitter: '🐦', facebook: '👤',
                tiktok: '🎵', youtube: '▶️',
            };

            markers.forEach(acc => {
                if (!acc.region) return;
                const color = acc.category?.color ?? '#6366f1';
                const icon  = L.divIcon({
                    html: `<div style="background:${color};width:13px;height:13px;border-radius:50%;border:2.5px solid #fff;box-shadow:0 1px 5px rgba(0,0,0,.45)"></div>`,
                    className: '', iconSize: [13, 13], iconAnchor: [6, 6],
                });
                const admNames = (acc.admins ?? []).join(', ') || '-';
                const popup = `
                    <div style="min-width:210px;font-family:system-ui,sans-serif">
                        <div style="font-weight:700;font-size:13px;margin-bottom:3px">
                            ${platEmoji[acc.platform] ?? '🌐'} @${acc.username}
                        </div>
                        <div style="font-size:11px;color:#6b7280;margin-bottom:7px">${acc.display_name}</div>
                        <div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:7px">
                            <span style="background:${color};color:#fff;padding:2px 7px;border-radius:999px;font-size:10px;font-weight:600">${acc.category?.name ?? '-'}</span>
                            <span style="background:#f1f5f9;color:#475569;padding:2px 7px;border-radius:999px;font-size:10px;text-transform:capitalize">${acc.platform}</span>
                        </div>
                        <div style="font-size:11px;color:#374151;line-height:1.7">
                            <div><b>Followers:</b> ${this.formatNum(acc.followers_count)}</div>
                            <div><b>Wilayah:</b> ${acc.region.name}</div>
                            <div><b>Admin:</b> ${admNames}</div>
                        </div>
                        <a href="/accounts/${acc.id}"
                           style="display:block;margin-top:8px;text-align:center;background:#4F46E5;color:#fff;padding:5px;border-radius:7px;font-size:11px;font-weight:600;text-decoration:none">
                           Lihat Detail →
                        </a>
                    </div>`;
                const m = L.marker([acc.region.latitude, acc.region.longitude], { icon });
                m.bindPopup(popup, { maxWidth: 270 });
                this.markerLayer.addLayer(m);
            });

            if (!this.map.hasLayer(this.markerLayer)) {
                this.map.addLayer(this.markerLayer);
            }
        },

        // ── Actions ──────────────────────────────────────────────
        applyFilters() { this.loadMarkers(); },

        resetFilters() {
            this.filters = { categories: [], platforms: [], region_id: '', min_followers: '', max_followers: '' };
            this.loadMarkers();
        },

        exportMapData() {
            const sp = new URLSearchParams();
            this.filters.categories.forEach(c  => sp.append('categories[]', c));
            this.filters.platforms.forEach(pl  => sp.append('platforms[]', pl));
            if (this.filters.region_id)     sp.set('region_id',    this.filters.region_id);
            if (this.filters.min_followers) sp.set('min_followers', this.filters.min_followers);
            if (this.filters.max_followers) sp.set('max_followers', this.filters.max_followers);
            window.location.href = '{{ route("export.map") }}?' + sp.toString();
        },

        formatNum(n) {
            if (!n) return '0';
            if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'M';
            if (n >= 1_000)     return (n / 1_000).toFixed(1) + 'K';
            return String(n);
        },
    };
}
</script>

<style>
/* ── Nama wilayah di atas dot ── */
.region-label {
    font-size: 10px;
    font-weight: 700;
    color: #14532d;
    white-space: nowrap;
    pointer-events: none;
    transform: translateX(-50%);
    display: block;
    text-align: center;
    text-shadow: 0 0 4px #fff, 0 0 4px #fff, 0 0 4px #fff;
}
.kota-label {
    color: #78350f;
}

/* ── Tooltip wilayah ── */
.region-tooltip {
    font-size: 12px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,.12);
    padding: 6px 10px;
}
.region-tooltip::before { display: none; }

.leaflet-popup-content { font-size: 13px; }
</style>
@endpush
