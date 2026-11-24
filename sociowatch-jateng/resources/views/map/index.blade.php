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
                    :class="showLabels ? 'bg-slate-100 border-slate-300 text-slate-800 font-medium' : 'text-gray-600 hover:bg-gray-50'">
                <i class="fas fa-tag text-xs"></i>
                <span class="hidden sm:inline">Nama Wilayah</span>
            </button>

            {{-- Pilihan Gaya Peta Minimalis (Bebas API Key & Rate Limit) --}}
            <div class="flex items-center bg-slate-100 p-0.5 rounded-lg border border-slate-200 text-xs">
                <button type="button" @click="setBasemap('gray')"
                        class="px-2.5 py-1 rounded-md transition font-medium"
                        :class="baseStyle === 'gray' ? 'bg-white text-indigo-600 shadow-xs' : 'text-slate-600 hover:text-slate-800'">
                    Minimalis
                </button>
                <button type="button" @click="setBasemap('topo')"
                        class="px-2.5 py-1 rounded-md transition font-medium"
                        :class="baseStyle === 'topo' ? 'bg-white text-indigo-600 shadow-xs' : 'text-slate-600 hover:text-slate-800'">
                    Topografi
                </button>
                <button type="button" @click="setBasemap('hot')"
                        class="px-2.5 py-1 rounded-md transition font-medium"
                        :class="baseStyle === 'hot' ? 'bg-white text-indigo-600 shadow-xs' : 'text-slate-600 hover:text-slate-800'">
                    Pastel
                </button>
            </div>

            {{-- Reset Fokus Jawa Tengah --}}
            <button @click="focusJateng()"
                    title="Kembalikan kamera ke tengah Provinsi Jawa Tengah"
                    class="flex items-center gap-1.5 text-sm text-gray-600 hover:text-indigo-600 border border-gray-200 rounded-lg px-3 py-1.5 transition">
                <i class="fas fa-crosshairs text-xs"></i>
                <span class="hidden lg:inline">Fokus Jateng</span>
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
        <div id="mainMap" class="flex-1 bg-slate-100"></div>

        {{-- Legend (Minimalist Glass Card) --}}
        <div class="absolute bottom-4 right-4 bg-white/95 backdrop-blur-sm rounded-xl shadow-md border border-slate-200/80 p-3 z-[1000] max-w-[200px]">
            <div class="flex items-center justify-between mb-2">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Legenda</p>
                <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 font-medium">Jawa Tengah</span>
            </div>

            {{-- Wilayah --}}
            <div class="space-y-1.5 mb-3 pb-2.5 border-b border-slate-100">
                <div class="flex items-center gap-2 text-xs text-slate-600">
                    <span class="w-3 h-3 rounded-full flex-shrink-0 border-2"
                          style="background:#bbf7d0;border-color:#16a34a"></span>
                    Kabupaten (29)
                </div>
                <div class="flex items-center gap-2 text-xs text-slate-600">
                    <span class="w-3 h-3 rounded-full flex-shrink-0 border-2"
                          style="background:#fde68a;border-color:#d97706"></span>
                    Kota (6)
                </div>
            </div>

            {{-- Kategori akun --}}
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Kategori Akun</p>
            <div class="space-y-1">
                @foreach($categories as $cat)
                <div class="flex items-center gap-2 text-xs text-slate-600">
                    <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:{{ $cat->color }}"></span>
                    <span class="truncate">{{ $cat->name }}</span>
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
                <button @click="selectedRegion = null" class="text-gray-400 hover:text-gray-600" aria-label="Tutup panel wilayah">
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

// Titik tengah Provinsi Jawa Tengah, Indonesia
const JATENG_CENTER = [-7.150975, 110.140259];

// Daftar Tile Server Gratis 100% Tanpa API Key & Tanpa Watermark
const BASEMAPS = {
    gray: {
        url: 'https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Light_Gray_Base/MapServer/tile/{z}/{y}/{x}',
        options: { attribution: 'Tiles © Esri — Esri, DeLorme, NAVTEQ', maxZoom: 16 }
    },
    topo: {
        url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}',
        options: { attribution: 'Tiles © Esri — Esri, HERE, Garmin, USGS', maxZoom: 18 }
    },
    hot: {
        url: 'https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png',
        options: { attribution: '© OpenStreetMap contributors, Tiles style by HOT', subdomains: 'abc', maxZoom: 19 }
    }
};

function mapApp() {
    const esc = s => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    return {
        filterOpen: window.innerWidth >= 768,
        loading: false,
        showLabels: true,
        baseStyle: 'gray',
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
        tileLayer: null,
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
                center: JATENG_CENTER,
                zoom: 8,
                minZoom: 6,
                maxZoom: 16,
                zoomControl: true,
            });

            this.setBasemap('gray');

            // Marker cluster layer minimalis untuk akun
            this.markerLayer = L.markerClusterGroup({
                maxClusterRadius: 45,
                showCoverageOnHover: false,
                iconCreateFunction(cluster) {
                    const n = cluster.getChildCount();
                    return L.divIcon({
                        html: `<div class="sw-cluster-pill"><span>${n}</span></div>`,
                        className: '', iconSize: [34, 34],
                    });
                },
            });

            // Load titik wilayah & akun secara paralel
            await Promise.all([
                this.loadRegions(),
                this.loadMarkers(),
            ]);
        },

        // ── Ganti Gaya Peta Dasar (100% Tanpa API Key) ──
        setBasemap(styleKey) {
            const cfg = BASEMAPS[styleKey] || BASEMAPS.gray;
            this.baseStyle = styleKey;
            if (this.tileLayer && this.map.hasLayer(this.tileLayer)) {
                this.map.removeLayer(this.tileLayer);
            }
            this.tileLayer = L.tileLayer(cfg.url, cfg.options).addTo(this.map);
            this.tileLayer.bringToBack();
        },

        // ── Reset Fokus ke Tengah Provinsi Jawa Tengah ──
        focusJateng() {
            this.selectedRegion = null;
            this.map.setView(JATENG_CENTER, 8);
        },

        // ── Load region data → render titik pusat & label per Kab/Kota ──
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

            const self = this;
            this.regionLayer = L.layerGroup();
            this.labelLayer  = L.layerGroup();

            regions.forEach(r => {
                if (!r.latitude || !r.longitude) return;

                const isKota    = r.type === 'kota';
                const fillColor = isKota ? '#fde68a' : '#bbf7d0';
                const edgeColor = isKota ? '#d97706' : '#16a34a';

                const dot = L.circleMarker([r.latitude, r.longitude], {
                    radius:      isKota ? 8 : 7,
                    fillColor,
                    color:       edgeColor,
                    weight:      2,
                    fillOpacity: 0.92,
                    opacity:     1,
                });

                dot.on('click', function () {
                    self.selectedRegion = {
                        id:              r.id,
                        name:            r.name,
                        type:            r.type,
                        account_count:   r.account_count,
                        total_followers: r.total_followers,
                    };
                });

                dot.bindTooltip(
                    `<div class="font-semibold text-slate-800">${esc(r.name)}</div>` +
                    `<div class="text-[11px] text-slate-500">Akun: <b class="text-indigo-600">${r.account_count}</b> · Followers: <b>${self.formatNum(r.total_followers)}</b></div>`,
                    { sticky: true, className: 'region-tooltip' }
                );

                this.regionLayer.addLayer(dot);

                const shortName = r.name.replace(/^(Kabupaten|Kota)\s+/i, '');
                const label = L.marker([r.latitude, r.longitude], {
                    icon: L.divIcon({
                        html: `<div class="region-label${isKota ? ' kota-label' : ''}">${esc(shortName)}</div>`,
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

            markers.forEach((acc, idx) => {
                if (!acc.region) return;
                const color = acc.category?.color ?? '#4f46e5';
                const icon  = L.divIcon({
                    html: `<div style="background:${color};width:12px;height:12px;border-radius:50%;border:2px solid #fff;box-shadow:0 1px 4px rgba(15,23,42,.35)"></div>`,
                    className: '', iconSize: [12, 12], iconAnchor: [6, 6],
                });
                const admNames = esc((acc.admins ?? []).join(', ') || '-');
                const popup = `
                    <div style="min-width:210px;font-family:system-ui,sans-serif">
                        <div style="font-weight:700;font-size:13px;margin-bottom:3px;color:#0f172a">
                            ${platEmoji[acc.platform] ?? '🌐'} @${esc(acc.username)}
                        </div>
                        <div style="font-size:11px;color:#64748b;margin-bottom:7px">${esc(acc.display_name)}</div>
                        <div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:7px">
                            <span style="background:${color};color:#fff;padding:2px 7px;border-radius:999px;font-size:10px;font-weight:600">${esc(acc.category?.name ?? '-')}</span>
                            <span style="background:#f1f5f9;color:#475569;padding:2px 7px;border-radius:999px;font-size:10px;text-transform:capitalize">${esc(acc.platform)}</span>
                        </div>
                        <div style="font-size:11px;color:#334155;line-height:1.7">
                            <div><b>Followers:</b> ${this.formatNum(acc.followers_count)}</div>
                            <div><b>Wilayah:</b> ${esc(acc.region.name)}</div>
                            <div><b>Admin:</b> ${admNames}</div>
                        </div>
                        <a href="/accounts/${acc.id}"
                           style="display:block;margin-top:8px;text-align:center;background:#4F46E5;color:#fff;padding:5px;border-radius:7px;font-size:11px;font-weight:600;text-decoration:none">
                           Lihat Detail →
                        </a>
                    </div>`;
                // Offset spiral mikro jika beberapa akun berada di wilayah yang sama
                const angle = idx * 1.1;
                const offset = 0.0025 * ((idx % 5) + 1);
                const lat = acc.region.latitude + Math.sin(angle) * offset;
                const lng = acc.region.longitude + Math.cos(angle) * offset;
                const m = L.marker([lat, lng], { icon });
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
/* ── Kanvas Peta Minimalis ── */
#mainMap {
    background: radial-gradient(circle at 50% 45%, #f8fafc 0%, #e2e8f0 100%);
}

/* ── Cluster Pill Minimalis ── */
.sw-cluster-pill {
    background: rgba(79, 70, 229, 0.16);
    border-radius: 9999px;
    width: 34px;
    height: 34px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.sw-cluster-pill > span {
    background: #4F46E5;
    color: #fff;
    border-radius: 9999px;
    width: 26px;
    height: 26px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 11px;
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.35);
}

/* ── Nama wilayah minimalis di atas titik pusat ── */
.region-label {
    font-size: 9.5px;
    font-weight: 600;
    letter-spacing: 0.01em;
    color: #334155;
    white-space: nowrap;
    pointer-events: none;
    transform: translateX(-50%);
    display: block;
    text-align: center;
    text-shadow: 0 0 3px #fff, 0 0 3px #fff, 0 1px 2px #fff;
}
.kota-label {
    color: #92400e;
    font-weight: 700;
}

/* ── Tooltip wilayah minimalis ── */
.region-tooltip {
    font-size: 12px;
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(4px);
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08);
    padding: 6px 10px;
}
.region-tooltip::before { display: none; }

.leaflet-popup-content { font-size: 13px; }
</style>
@endpush
