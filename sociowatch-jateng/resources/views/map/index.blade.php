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
                <button @click="applyFilters()" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 py-1.5 rounded-lg transition">
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
                @php $pIcons=['instagram'=>['fa-instagram','text-pink-500'],'twitter'=>['fa-twitter','text-sky-500'],'facebook'=>['fa-facebook','text-blue-600'],'tiktok'=>['fa-tiktok','text-gray-800'],'youtube'=>['fa-youtube','text-red-600']]; @endphp
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
                <select x-model="filters.region_id" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                    <option value="">Semua Wilayah</option>
                    @foreach($regions as $reg)
                    <option value="{{ $reg->id }}">{{ $reg->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Filter: Followers Range --}}
            <div class="mb-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Followers</p>
                <div class="space-y-2">
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
            </div>

            {{-- Choropleth extra filter --}}
            <div x-show="mode === 'choropleth'" class="mb-4 border-t border-gray-100 pt-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Intensitas Choropleth</p>
                <div class="space-y-1">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" value="accounts" x-model="choroplethMetric" class="text-indigo-600">
                        <span class="text-sm text-gray-600">Jumlah Akun</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" value="followers" x-model="choroplethMetric" class="text-indigo-600">
                        <span class="text-sm text-gray-600">Total Followers</span>
                    </label>
                </div>
            </div>

            {{-- Reset --}}
            <button @click="resetFilters()" class="w-full text-xs text-gray-400 hover:text-gray-600 py-2 border border-dashed border-gray-200 rounded-lg mt-2">
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
                <span x-show="activeFilterCount > 0" class="bg-indigo-600 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center" x-text="activeFilterCount"></span>
            </button>

            {{-- Mode toggle --}}
            <div class="flex border border-gray-200 rounded-lg overflow-hidden text-sm">
                <button @click="switchMode('marker')"
                        :class="mode === 'marker' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-50'"
                        class="px-3 py-1.5 transition flex items-center gap-1.5">
                    <i class="fas fa-map-marker-alt text-xs"></i>
                    <span class="hidden sm:inline">Marker Map</span>
                </button>
                <button @click="switchMode('choropleth')"
                        :class="mode === 'choropleth' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-50'"
                        class="px-3 py-1.5 transition flex items-center gap-1.5">
                    <i class="fas fa-layer-group text-xs"></i>
                    <span class="hidden sm:inline">Choropleth</span>
                </button>
            </div>

            {{-- Account count badge --}}
            <div class="text-xs text-gray-500 ml-auto">
                <span x-show="loading" class="text-indigo-500"><i class="fas fa-spinner fa-spin mr-1"></i>Loading...</span>
                <span x-show="!loading">
                    <span class="font-semibold text-gray-700" x-text="accountCount"></span> akun ditampilkan
                </span>
            </div>
        </div>

        {{-- Map container --}}
        <div id="mainMap" class="flex-1"></div>

        {{-- Legend (bottom-right overlay) --}}
        <div class="absolute bottom-4 right-4 bg-white rounded-xl shadow-lg border border-gray-100 p-3 z-[1000]">
            <p class="text-xs font-semibold text-gray-600 mb-2">Legend</p>
            {{-- Marker legend --}}
            <div x-show="mode === 'marker'" class="space-y-1">
                @foreach($categories as $cat)
                <div class="flex items-center gap-2 text-xs text-gray-600">
                    <span class="w-3 h-3 rounded-full flex-shrink-0" style="background:{{ $cat->color }}"></span>
                    {{ $cat->name }}
                </div>
                @endforeach
            </div>
            {{-- Choropleth legend --}}
            <div x-show="mode === 'choropleth'" class="space-y-1">
                <div class="flex gap-0.5 mb-1">
                    <div class="w-4 h-4" style="background:#fff3cd"></div>
                    <div class="w-4 h-4" style="background:#ffc107"></div>
                    <div class="w-4 h-4" style="background:#fd7e14"></div>
                    <div class="w-4 h-4" style="background:#dc3545"></div>
                    <div class="w-4 h-4" style="background:#6f0000"></div>
                </div>
                <div class="flex justify-between text-xs text-gray-500 w-20">
                    <span>Rendah</span><span>Tinggi</span>
                </div>
            </div>
        </div>

        {{-- Choropleth side panel (region detail) --}}
        <div x-show="selectedRegion" x-transition
             class="absolute top-12 right-4 w-72 bg-white rounded-xl shadow-xl border border-gray-100 p-4 z-[1000] max-h-[60vh] overflow-y-auto"
             x-cloak>
            <div class="flex items-start justify-between mb-3">
                <div>
                    <h4 class="font-semibold text-gray-800 text-sm" x-text="selectedRegion?.name"></h4>
                    <p class="text-xs text-gray-400">Klik wilayah untuk detail</p>
                </div>
                <button @click="selectedRegion = null" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
            <div class="grid grid-cols-2 gap-2 mb-3">
                <div class="bg-gray-50 rounded-lg p-2 text-center">
                    <p class="text-lg font-bold text-indigo-600" x-text="selectedRegion?.account_count"></p>
                    <p class="text-xs text-gray-500">Total Akun</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-2 text-center">
                    <p class="text-sm font-bold text-emerald-600" x-text="formatNum(selectedRegion?.total_followers)"></p>
                    <p class="text-xs text-gray-500">Total Followers</p>
                </div>
            </div>
            <div class="space-y-1.5">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Per Kategori</p>
                <template x-for="cat in (selectedRegion?.category_breakdown ?? [])" :key="cat.name">
                    <div class="flex items-center justify-between text-sm">
                        <div class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full" :style="`background:${cat.color}`"></span>
                            <span class="text-gray-600" x-text="cat.name"></span>
                        </div>
                        <span class="font-medium text-gray-800" x-text="cat.count"></span>
                    </div>
                </template>
                <template x-if="!selectedRegion?.category_breakdown?.length">
                    <p class="text-xs text-gray-400">Tidak ada akun di wilayah ini.</p>
                </template>
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
// ── API endpoints (Tahap 6) ──────────────────────────────────
const GEOJSON_URL       = '{{ asset("geojson/jawa-tengah.geojson") }}';
const URL_MARKERS       = '{{ route("map.markers") }}';
const URL_CHOROPLETH    = '{{ route("map.choropleth") }}';
const URL_REGION        = '{{ url("map/region") }}'; // + /{id}
const INIT_REGION_ID    = '{{ request("region_id") }}';
// ─────────────────────────────────────────────────────────────

function mapApp() {
    return {
        mode: 'marker',
        filterOpen: true,
        loading: false,
        accountCount: 0,
        selectedRegion: null,       // data wilayah yg diklik (choropleth)
        selectedRegionAccounts: [], // akun di wilayah tsb (dari /map/region/{id})
        regionLoading: false,
        choroplethMetric: 'accounts',
        filters: {
            categories: [],
            platforms: [],
            region_id: INIT_REGION_ID || '',
            min_followers: '',
            max_followers: '',
        },
        map: null,
        markerLayer: null,
        choroplethLayer: null,
        geojsonData: null,
        regionStats: [],            // dari /map/choropleth

        get activeFilterCount() {
            return [
                this.filters.categories.length > 0,
                this.filters.platforms.length > 0,
                !!this.filters.region_id,
                !!this.filters.min_followers,
                !!this.filters.max_followers,
            ].filter(Boolean).length;
        },

        // ── init ────────────────────────────────────────────
        init() {
            this.map = L.map('mainMap', {
                center: [-7.150975, 110.140259],
                zoom: 8, minZoom: 7, maxZoom: 15,
            });
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
            }).addTo(this.map);

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

            // GeoJSON pre-load untuk choropleth
            fetch(GEOJSON_URL)
                .then(r => r.json())
                .then(data => { this.geojsonData = data; })
                .catch(e => console.warn('GeoJSON load failed:', e));

            this.loadMarkers(); // default mode = marker
        },

        // ── buildParams — helper query string ───────────────
        buildParams(extra = {}) {
            const p = new URLSearchParams();
            this.filters.categories.forEach(c => p.append('categories[]', c));
            this.filters.platforms.forEach(pl => p.append('platforms[]', pl));
            if (this.filters.region_id)    p.set('region_id', this.filters.region_id);
            if (this.filters.min_followers) p.set('min_followers', this.filters.min_followers);
            if (this.filters.max_followers) p.set('max_followers', this.filters.max_followers);
            Object.entries(extra).forEach(([k, v]) => p.set(k, v));
            return p;
        },

        // ── GET /map/markers ─────────────────────────────────
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

        // ── GET /map/choropleth ──────────────────────────────
        async loadChoropleth() {
            this.loading = true;
            try {
                const params = this.buildParams({ metric: this.choroplethMetric });
                const res    = await fetch(`${URL_CHOROPLETH}?${params}`);
                const data   = await res.json();
                this.regionStats  = data.regions;
                this.accountCount = data.regions.reduce((s, r) => s + r.account_count, 0);
                this.renderChoropleth();
            } catch (e) {
                console.error('Choropleth fetch failed:', e);
            } finally {
                this.loading = false;
            }
        },

        // ── GET /map/region/{id} ─────────────────────────────
        async loadRegionAccounts(regionId) {
            this.regionLoading = true;
            try {
                const res  = await fetch(`${URL_REGION}/${regionId}`);
                const data = await res.json();
                this.selectedRegionAccounts = data.accounts;
            } catch (e) {
                console.error('Region accounts fetch failed:', e);
            } finally {
                this.regionLoading = false;
            }
        },

        // ── render: Marker Map ───────────────────────────────
        renderMarkers(markers) {
            if (this.choroplethLayer) { this.map.removeLayer(this.choroplethLayer); this.choroplethLayer = null; }
            this.markerLayer.clearLayers();

            const platEmoji = { instagram:'📷', twitter:'🐦', facebook:'👤', tiktok:'🎵', youtube:'▶️' };

            markers.forEach(acc => {
                if (!acc.region) return;
                const color = acc.category?.color ?? '#6366f1';
                const icon  = L.divIcon({
                    html: `<div style="background:${color};width:14px;height:14px;border-radius:50%;border:2.5px solid #fff;box-shadow:0 1px 5px rgba(0,0,0,.45)"></div>`,
                    className: '', iconSize: [14, 14], iconAnchor: [7, 7],
                });
                const admNames = (acc.admins ?? []).join(', ') || '-';
                const popup = `
                    <div style="min-width:210px;font-family:system-ui,sans-serif">
                        <div style="font-weight:700;font-size:13px;margin-bottom:3px">
                            ${platEmoji[acc.platform]??'🌐'} @${acc.username}
                        </div>
                        <div style="font-size:11px;color:#6b7280;margin-bottom:7px">${acc.display_name}</div>
                        <div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:7px">
                            <span style="background:${color};color:#fff;padding:2px 7px;border-radius:999px;font-size:10px;font-weight:600">${acc.category?.name??'-'}</span>
                            <span style="background:#f1f5f9;color:#475569;padding:2px 7px;border-radius:999px;font-size:10px;text-transform:capitalize">${acc.platform}</span>
                        </div>
                        <div style="font-size:11px;color:#374151;line-height:1.6">
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

        // ── render: Choropleth ───────────────────────────────
        renderChoropleth() {
            if (this.markerLayer) this.map.removeLayer(this.markerLayer);
            if (this.choroplethLayer) { this.map.removeLayer(this.choroplethLayer); this.choroplethLayer = null; }
            if (!this.geojsonData)    { console.warn('GeoJSON not loaded yet'); return; }

            // index region stats by geojson_key
            const byKey = {};
            this.regionStats.forEach(r => { byKey[r.geojson_key] = r; });

            const metric = this.choroplethMetric;
            const vals   = this.regionStats.map(r => metric === 'accounts' ? r.account_count : r.total_followers).filter(v => v > 0);
            const maxVal = vals.length ? Math.max(...vals) : 1;

            const getColor = v => {
                if (!v) return '#f1f5f9';
                const t = v / maxVal;
                if (t < 0.2) return '#fef9c3';
                if (t < 0.4) return '#fde047';
                if (t < 0.6) return '#f97316';
                if (t < 0.8) return '#dc2626';
                return '#7f1d1d';
            };

            const self = this;
            this.choroplethLayer = L.geoJSON(this.geojsonData, {
                style(feature) {
                    const key  = feature.properties.KABKOT ?? feature.properties.GEO_KEY ?? (feature.properties.name ?? '').toUpperCase();
                    const stat = byKey[key];
                    const val  = stat ? (metric === 'accounts' ? stat.account_count : stat.total_followers) : 0;
                    return { fillColor: getColor(val), weight: 1.5, color: '#94a3b8', fillOpacity: 0.78 };
                },
                onEachFeature(feature, layer) {
                    const key  = feature.properties.KABKOT ?? feature.properties.GEO_KEY ?? (feature.properties.name ?? '').toUpperCase();
                    const stat = byKey[key];
                    layer.on({
                        mouseover(e) {
                            e.target.setStyle({ weight: 2.5, color: '#4F46E5', fillOpacity: 0.92 });
                            if (!L.Browser.ie && !L.Browser.opera && !L.Browser.edge) e.target.bringToFront();
                            const name  = stat?.name ?? feature.properties.name ?? key;
                            const n     = stat?.account_count ?? 0;
                            const f     = self.formatNum(stat?.total_followers ?? 0);
                            layer.bindTooltip(`<b>${name}</b><br>Akun: <b>${n}</b> · Followers: <b>${f}</b>`,
                                { sticky: true, className: 'choropleth-tooltip' }).openTooltip();
                        },
                        mouseout(e) { self.choroplethLayer.resetStyle(e.target); },
                        // klik wilayah → ambil daftar akun via /map/region/{id}
                        click() {
                            if (stat) {
                                self.selectedRegion = { ...stat };
                                self.selectedRegionAccounts = [];
                                self.loadRegionAccounts(stat.id);
                            }
                        },
                    });
                },
            }).addTo(this.map);
        },

        // ── actions ──────────────────────────────────────────
        applyFilters() {
            this.mode === 'marker' ? this.loadMarkers() : this.loadChoropleth();
        },

        resetFilters() {
            this.filters = { categories: [], platforms: [], region_id: '', min_followers: '', max_followers: '' };
            this.applyFilters();
        },

        switchMode(mode) {
            this.mode = mode;
            this.selectedRegion = null;
            this.selectedRegionAccounts = [];
            mode === 'choropleth' ? this.loadChoropleth() : this.loadMarkers();
        },

        formatNum(n) {
            if (!n) return '0';
            if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'M';
            if (n >= 1_000)     return (n / 1_000).toFixed(1) + 'K';
            return String(n);
        },
    }
}
</script>
<style>
.choropleth-tooltip { font-size: 13px; }
.leaflet-popup-content { font-size: 13px; }
</style>
@endpush
