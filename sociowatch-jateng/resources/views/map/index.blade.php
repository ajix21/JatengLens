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
const GEOJSON_URL = '{{ asset("geojson/jawa-tengah.geojson") }}';
const MAP_DATA_URL = '{{ route("map.data") }}';
const INIT_REGION_ID = '{{ request("region_id") }}';

function mapApp() {
    return {
        mode: 'marker',
        filterOpen: true,
        loading: false,
        accountCount: 0,
        selectedRegion: null,
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
        regionStats: [],

        get activeFilterCount() {
            let c = 0;
            if (this.filters.categories.length) c++;
            if (this.filters.platforms.length) c++;
            if (this.filters.region_id) c++;
            if (this.filters.min_followers) c++;
            if (this.filters.max_followers) c++;
            return c;
        },

        init() {
            this.map = L.map('mainMap', {
                center: [-7.150975, 110.140259],
                zoom: 8,
                minZoom: 7,
                maxZoom: 15,
            });
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(this.map);

            this.markerLayer = L.markerClusterGroup({
                maxClusterRadius: 50,
                showCoverageOnHover: false,
                iconCreateFunction: function(cluster) {
                    const count = cluster.getChildCount();
                    return L.divIcon({
                        html: `<div style="background:#4F46E5;color:white;border-radius:50%;width:36px;height:36px;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:12px;box-shadow:0 2px 6px rgba(0,0,0,.3)">${count}</div>`,
                        className: '', iconSize: [36, 36]
                    });
                }
            });

            // Load GeoJSON for choropleth
            fetch(GEOJSON_URL)
                .then(r => r.json())
                .then(data => { this.geojsonData = data; })
                .catch(e => console.warn('GeoJSON load failed:', e));

            this.loadData();
        },

        async loadData() {
            this.loading = true;
            const params = new URLSearchParams();
            this.filters.categories.forEach(c => params.append('categories[]', c));
            this.filters.platforms.forEach(p => params.append('platforms[]', p));
            if (this.filters.region_id) params.set('region_id', this.filters.region_id);
            if (this.filters.min_followers) params.set('min_followers', this.filters.min_followers);
            if (this.filters.max_followers) params.set('max_followers', this.filters.max_followers);

            try {
                const res = await fetch(`${MAP_DATA_URL}?${params}`);
                const data = await res.json();
                this.regionStats = data.region_stats;
                this.renderCurrentMode(data.accounts);
                this.accountCount = data.accounts.length;
            } catch(e) {
                console.error('Map data load failed:', e);
            } finally {
                this.loading = false;
            }
        },

        renderCurrentMode(accounts) {
            if (this.mode === 'marker') {
                this.renderMarkers(accounts);
            } else {
                this.renderChoropleth();
            }
        },

        renderMarkers(accounts) {
            // Clear choropleth
            if (this.choroplethLayer) { this.map.removeLayer(this.choroplethLayer); this.choroplethLayer = null; }
            this.markerLayer.clearLayers();

            accounts.forEach(acc => {
                if (!acc.region) return;
                const color = acc.category?.color ?? '#6366f1';
                const platIcons = {instagram:'📷',twitter:'🐦',facebook:'👤',tiktok:'🎵',youtube:'▶️'};
                const platIcon = platIcons[acc.platform] || '🌐';

                const icon = L.divIcon({
                    html: `<div style="background:${color};width:14px;height:14px;border-radius:50%;border:2.5px solid white;box-shadow:0 1px 4px rgba(0,0,0,.4)"></div>`,
                    className: '', iconSize: [14, 14], iconAnchor: [7, 7]
                });

                const adminNames = (acc.admins || []).join(', ') || '-';
                const popup = `
                    <div style="min-width:200px;font-family:sans-serif">
                        <div style="font-weight:600;font-size:14px;margin-bottom:4px">${platIcon} @${acc.username}</div>
                        <div style="font-size:12px;color:#555;margin-bottom:6px">${acc.display_name}</div>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:6px">
                            <span style="background:${color};color:white;padding:2px 8px;border-radius:9999px;font-size:11px">${acc.category?.name ?? '-'}</span>
                            <span style="background:#f3f4f6;padding:2px 8px;border-radius:9999px;font-size:11px">${acc.platform}</span>
                        </div>
                        <div style="font-size:12px;color:#444">
                            <div><b>Followers:</b> ${this.formatNum(acc.followers_count)}</div>
                            <div><b>Wilayah:</b> ${acc.region?.name ?? '-'}</div>
                            <div><b>Admin:</b> ${adminNames}</div>
                        </div>
                        <a href="/accounts/${acc.id}" style="display:block;margin-top:8px;text-align:center;background:#4F46E5;color:white;padding:4px;border-radius:6px;font-size:11px;text-decoration:none">Lihat Detail</a>
                    </div>`;

                const m = L.marker([acc.region.latitude, acc.region.longitude], { icon });
                m.bindPopup(popup, { maxWidth: 260 });
                this.markerLayer.addLayer(m);
            });

            if (!this.map.hasLayer(this.markerLayer)) {
                this.map.addLayer(this.markerLayer);
            }
        },

        renderChoropleth() {
            if (this.markerLayer) { this.map.removeLayer(this.markerLayer); }
            if (this.choroplethLayer) { this.map.removeLayer(this.choroplethLayer); this.choroplethLayer = null; }
            if (!this.geojsonData) { console.warn('GeoJSON not loaded yet'); return; }

            const stats = {};
            this.regionStats.forEach(r => { stats[r.geojson_key] = r; });

            const metric = this.choroplethMetric;
            const values = this.regionStats.map(r => metric === 'accounts' ? r.account_count : r.total_followers).filter(v => v > 0);
            const maxVal = values.length ? Math.max(...values) : 1;

            const getColor = (val) => {
                if (!val) return '#f8f9fa';
                const ratio = val / maxVal;
                if (ratio < 0.2)  return '#fff3cd';
                if (ratio < 0.4)  return '#ffc107';
                if (ratio < 0.6)  return '#fd7e14';
                if (ratio < 0.8)  return '#dc3545';
                return '#6f0000';
            };

            const self = this;
            this.choroplethLayer = L.geoJSON(this.geojsonData, {
                style: function(feature) {
                    const key = feature.properties.KABKOT || feature.properties.GEO_KEY || feature.properties.name?.toUpperCase() || '';
                    const regionStat = stats[key];
                    const val = regionStat ? (metric === 'accounts' ? regionStat.account_count : regionStat.total_followers) : 0;
                    return {
                        fillColor: getColor(val),
                        weight: 1.5,
                        color: '#999',
                        fillOpacity: 0.75,
                    };
                },
                onEachFeature: function(feature, layer) {
                    const key = feature.properties.KABKOT || feature.properties.GEO_KEY || feature.properties.name?.toUpperCase() || '';
                    const regionStat = stats[key];

                    layer.on({
                        mouseover: function(e) {
                            e.target.setStyle({ weight: 2.5, color: '#4F46E5', fillOpacity: 0.9 });
                            if (!L.Browser.ie && !L.Browser.opera && !L.Browser.edge) e.target.bringToFront();

                            const name = regionStat?.name ?? feature.properties.name ?? key;
                            const accounts = regionStat?.account_count ?? 0;
                            const followers = self.formatNum(regionStat?.total_followers ?? 0);
                            layer.bindTooltip(
                                `<b>${name}</b><br>Akun: ${accounts} | Followers: ${followers}`,
                                { sticky: true, className: 'choropleth-tooltip' }
                            ).openTooltip();
                        },
                        mouseout: function(e) {
                            self.choroplethLayer.resetStyle(e.target);
                        },
                        click: function() {
                            if (regionStat) {
                                // Find region id from stats
                                self.selectedRegion = { ...regionStat };
                            }
                        }
                    });
                }
            }).addTo(this.map);
        },

        applyFilters() {
            this.loadData();
        },

        resetFilters() {
            this.filters = { categories: [], platforms: [], region_id: '', min_followers: '', max_followers: '' };
            this.loadData();
        },

        switchMode(mode) {
            this.mode = mode;
            this.selectedRegion = null;
            if (mode === 'choropleth') {
                this.renderChoropleth();
            } else {
                // Re-fetch to restore marker mode
                this.loadData();
            }
        },

        formatNum(n) {
            if (!n) return '0';
            if (n >= 1000000) return (n/1000000).toFixed(1) + 'M';
            if (n >= 1000) return (n/1000).toFixed(1) + 'K';
            return n.toString();
        }
    }
}
</script>
<style>
.choropleth-tooltip { font-size: 13px; }
.leaflet-popup-content { font-size: 13px; }
</style>
@endpush
