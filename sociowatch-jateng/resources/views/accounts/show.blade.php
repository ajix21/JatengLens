@extends('layouts.app')
@section('title', $account->display_name . ' — SocioWatch Jateng')
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

{{-- HEADER CARD --}}
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
                <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-medium">Aktif</span>
                @else
                <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 font-medium">Nonaktif</span>
                @endif
            </div>
            <p class="text-gray-500 text-sm mb-2">@{{ $account->username }} · {{ $account->region->name ?? '-' }}</p>
            @if($account->bio)
            <p class="text-gray-600 text-sm">{{ $account->bio }}</p>
            @endif
            @if($account->profile_url)
            <a href="{{ $account->profile_url }}" target="_blank"
               class="text-indigo-600 hover:underline text-xs mt-1 inline-block">
                <i class="fas fa-external-link-alt mr-1"></i>Buka Profil
            </a>
            @endif
        </div>
        <div class="flex gap-2 flex-shrink-0 flex-wrap">
            {{-- Update Stats --}}
            @can('can-edit')
            <button onclick="document.getElementById('modalStats').classList.remove('hidden')"
                    class="bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-sm px-3 py-1.5 rounded-lg transition flex items-center gap-1.5">
                <i class="fas fa-sync-alt text-xs"></i> Update Stats
            </button>
            @endcan
            <a href="{{ route('map.index', ['region_id' => $account->region_id]) }}"
               class="border border-blue-200 text-blue-600 hover:bg-blue-50 text-sm px-3 py-1.5 rounded-lg transition flex items-center gap-1.5">
                <i class="fas fa-map-marker-alt text-xs"></i> Peta
            </a>
            <a href="{{ route('accounts.edit', $account) }}"
               class="bg-amber-50 hover:bg-amber-100 text-amber-700 text-sm px-3 py-1.5 rounded-lg transition flex items-center gap-1.5">
                <i class="fas fa-pen text-xs"></i> Edit
            </a>
        </div>
    </div>

    {{-- Stats row --}}
    <div class="grid grid-cols-3 gap-4 mt-5 pt-5 border-t border-gray-100">
        <div class="text-center">
            <p class="text-xl font-bold text-gray-800">{{ number_format($account->followers_count) }}</p>
            <p class="text-xs text-gray-400">Followers</p>
            @if($change7d !== null)
            <p class="text-xs font-semibold mt-0.5 {{ $change7d >= 0 ? 'text-green-600' : 'text-red-500' }}">
                {{ $change7d >= 0 ? '+' : '' }}{{ $change7d }}% <span class="font-normal text-gray-400">(7 hari)</span>
            </p>
            @endif
        </div>
        <div class="text-center">
            <p class="text-xl font-bold text-gray-800">{{ number_format($account->following_count) }}</p>
            <p class="text-xs text-gray-400">Following</p>
            @if($change30d !== null)
            <p class="text-xs font-semibold mt-0.5 {{ $change30d >= 0 ? 'text-green-600' : 'text-red-500' }}">
                {{ $change30d >= 0 ? '+' : '' }}{{ $change30d }}% <span class="font-normal text-gray-400">(30 hari)</span>
            </p>
            @endif
        </div>
        <div class="text-center">
            <p class="text-xl font-bold text-gray-800">{{ number_format($account->post_count) }}</p>
            <p class="text-xs text-gray-400">Postingan</p>
        </div>
    </div>
</div>

{{-- TABS --}}
<div x-data="{ tab: 'trend' }">
    <div class="flex gap-1 bg-white rounded-xl border border-gray-100 p-1 shadow-sm w-fit">
        <button @click="tab='trend'"
                :class="tab==='trend' ? 'bg-indigo-600 text-white shadow' : 'text-gray-500 hover:text-gray-700'"
                class="px-4 py-1.5 rounded-lg text-sm font-medium transition">
            <i class="fas fa-chart-line mr-1.5"></i>Tren Followers
        </button>
        <button @click="tab='alerts'"
                :class="tab==='alerts' ? 'bg-indigo-600 text-white shadow' : 'text-gray-500 hover:text-gray-700'"
                class="px-4 py-1.5 rounded-lg text-sm font-medium transition">
            <i class="fas fa-bell mr-1.5"></i>Alert
            @if($recentAlerts->where('is_read', false)->count())
            <span class="ml-1 bg-red-500 text-white text-[0.6rem] font-bold px-1.5 py-0.5 rounded-full">
                {{ $recentAlerts->where('is_read', false)->count() }}
            </span>
            @endif
        </button>
        <button @click="tab='info'"
                :class="tab==='info' ? 'bg-indigo-600 text-white shadow' : 'text-gray-500 hover:text-gray-700'"
                class="px-4 py-1.5 rounded-lg text-sm font-medium transition">
            <i class="fas fa-info-circle mr-1.5"></i>Info & Log
        </button>
    </div>

    {{-- TAB: Tren --}}
    <div x-show="tab==='trend'" x-cloak class="mt-4">
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
            {{-- Chart --}}
            <div class="xl:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-700 flex items-center gap-2">
                        <i class="fas fa-chart-area text-indigo-500"></i> Tren Followers 30 Hari
                    </h3>
                    <span class="text-xs text-gray-400">{{ $snapshots30->count() }} titik data</span>
                </div>
                @if($snapshots30->count() >= 2)
                <canvas id="trendChart" style="height:220px"></canvas>
                @else
                <div class="flex flex-col items-center justify-center h-40 text-gray-400">
                    <i class="fas fa-chart-line text-3xl mb-2 opacity-30"></i>
                    <p class="text-sm">Belum cukup data untuk menampilkan grafik.</p>
                    <p class="text-xs mt-1">Minimal 2 snapshot diperlukan.</p>
                </div>
                @endif
            </div>

            {{-- Change stats + mini map --}}
            <div class="space-y-4">
                {{-- Change cards --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                    <h4 class="font-semibold text-gray-700 text-sm mb-3 flex items-center gap-2">
                        <i class="fas fa-arrow-trend-up text-green-500"></i> Perubahan Followers
                    </h4>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
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

                {{-- Mini map --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                    <h4 class="font-semibold text-gray-700 text-sm mb-3 flex items-center gap-2">
                        <i class="fas fa-map text-blue-500"></i> Lokasi
                    </h4>
                    <div id="accountMap" class="h-40 rounded-lg overflow-hidden border border-gray-100 mb-2"></div>
                    <p class="text-sm font-medium text-gray-700">{{ $account->region->name ?? '-' }}</p>
                    <p class="text-xs text-gray-400">{{ $account->region?->type === 'kota' ? 'Kota' : 'Kabupaten' }} · Jawa Tengah</p>
                    @if($account->notes)
                    <p class="text-xs text-gray-500 mt-2 pt-2 border-t border-gray-100">{{ $account->notes }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- TAB: Alert --}}
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
                                <label class="block text-xs font-medium text-gray-500 mb-1">Is Active</label>
                                <label class="flex items-center gap-2 text-sm text-gray-600">
                                    <input type="checkbox" name="is_active" value="1"
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
                <p class="text-xs text-gray-400 mt-3 text-center">
                    <a href="{{ route('alerts.settings') }}" class="hover:underline">Kelola setting global →</a>
                </p>
            </div>
            @endcan
        </div>
    </div>

    {{-- TAB: Info & Log --}}
    <div x-show="tab==='info'" x-cloak class="mt-4">
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

            {{-- Admins --}}
            <div class="xl:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-700 flex items-center gap-2">
                        <i class="fas fa-id-card text-indigo-500"></i> Admin/Operator ({{ $account->admins->count() }})
                    </h3>
                    <a href="{{ route('accounts.edit', $account) }}" class="text-xs text-indigo-600 hover:underline">Edit Admin</a>
                </div>
                @forelse($account->admins as $admin)
                <div class="flex items-start gap-3 p-3 rounded-lg border border-gray-100 mb-3 bg-gray-50">
                    <div class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0 text-indigo-600 font-bold text-sm">
                        {{ strtoupper(substr($admin->full_name, 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0 grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                        <div><span class="font-medium text-gray-800">{{ $admin->full_name }}</span>
                            @if($admin->alias)<span class="text-gray-400 text-xs"> ({{ $admin->alias }})</span>@endif</div>
                        @if($admin->nik) <div class="text-gray-500 text-xs">NIK: {{ $admin->nik }}</div> @endif
                        @if($admin->phone) <div class="text-gray-500 text-xs"><i class="fas fa-phone text-xs mr-1"></i>{{ $admin->phone }}</div> @endif
                        @if($admin->email) <div class="text-gray-500 text-xs"><i class="fas fa-envelope text-xs mr-1"></i>{{ $admin->email }}</div> @endif
                        @if($admin->occupation || $admin->affiliation)
                        <div class="col-span-2 text-gray-500 text-xs">
                            {{ $admin->occupation }}{{ $admin->occupation && $admin->affiliation ? ' — ' : '' }}{{ $admin->affiliation }}
                        </div>
                        @endif
                    </div>
                </div>
                @empty
                <p class="text-sm text-gray-400 text-center py-4">Belum ada data admin/operator.</p>
                @endforelse
            </div>

            {{-- Activity Log --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="font-semibold text-gray-700 mb-4 flex items-center gap-2">
                    <i class="fas fa-history text-purple-500"></i> Riwayat Perubahan
                </h3>
                @if($account->activityLogs->count())
                <div class="space-y-2">
                    @foreach($account->activityLogs as $log)
                    <div class="flex items-start gap-2 text-sm">
                        <div class="w-2 h-2 rounded-full bg-purple-400 mt-1.5 flex-shrink-0"></div>
                        <div class="flex-1 min-w-0">
                            <span class="font-medium text-gray-700 capitalize text-xs">{{ str_replace('_', ' ', $log->activity_type) }}</span>
                            @if($log->old_value || $log->new_value)
                            <br>
                            <span class="text-gray-400 line-through text-[0.65rem]">{{ $log->old_value }}</span>
                            @if($log->old_value && $log->new_value) <span class="text-gray-400 mx-0.5 text-[0.65rem]">→</span> @endif
                            <span class="text-gray-700 text-[0.65rem] font-medium">{{ $log->new_value }}</span>
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
</div>

</div>

{{-- MODAL: Update Stats --}}
@can('can-edit')
<div id="modalStats" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
     style="background:rgba(0,0,0,.5)">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800">
                <i class="fas fa-sync-alt text-indigo-500 mr-2"></i>Update Statistik
            </h3>
            <button onclick="document.getElementById('modalStats').classList.add('hidden')"
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
                <p class="text-xs text-gray-400">
                    <i class="fas fa-info-circle mr-1"></i>
                    Snapshot otomatis dibuat. Alert akan dicek sesuai threshold.
                </p>
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

@endsection

@push('scripts')
<script>
@if($account->region)
const map = L.map('accountMap', { zoomControl: false, attributionControl: false })
    .setView([{{ $account->region->latitude }}, {{ $account->region->longitude }}], 10);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
L.control.zoom({ position: 'topright' }).addTo(map);
const color = '{{ $account->category->color ?? "#6366f1" }}';
const icon = L.divIcon({
    html: `<div style="background:${color};width:16px;height:16px;border-radius:50%;border:3px solid white;box-shadow:0 2px 6px rgba(0,0,0,.4)"></div>`,
    className: '', iconSize: [16,16], iconAnchor: [8,8]
});
L.marker([{{ $account->region->latitude }}, {{ $account->region->longitude }}], { icon })
    .bindPopup('{{ $account->display_name }}<br>{{ $account->region->name }}')
    .addTo(map).openPopup();
@endif

@if($snapshots30->count() >= 2)
const labels = {!! $snapshots30->map(fn($s) => '"' . \Carbon\Carbon::parse($s->recorded_at)->format('d M') . '"')->implode(',') !!};
const data   = {!! $snapshots30->pluck('followers_count')->implode(',') !!};

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
