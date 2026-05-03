@extends('layouts.app')
@section('title', 'Notifikasi Alert — SocioWatch Jateng')
@section('page-title', 'Notifikasi & Alert')
@section('breadcrumb')
    <span class="text-slate-500">Notifikasi</span>
@endsection

@section('content')
<div class="space-y-5">

    {{-- Stats bar --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        @php
            $spikeUp   = \App\Models\Alert::where('alert_type','spike_up')->count();
            $spikeDown = \App\Models\Alert::where('alert_type','spike_down')->count();
            $milestone = \App\Models\Alert::where('alert_type','milestone')->count();
        @endphp
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" style="background:#fef3c7">
                <i class="fas fa-bell text-amber-500"></i>
            </div>
            <div>
                <div class="text-xl font-bold text-slate-800">{{ $unreadCount }}</div>
                <div class="text-xs text-slate-400">Belum Dibaca</div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" style="background:#dcfce7">
                <i class="fas fa-arrow-trend-up text-green-500"></i>
            </div>
            <div>
                <div class="text-xl font-bold text-slate-800">{{ $spikeUp }}</div>
                <div class="text-xs text-slate-400">Spike Naik</div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" style="background:#fee2e2">
                <i class="fas fa-arrow-trend-down text-red-500"></i>
            </div>
            <div>
                <div class="text-xl font-bold text-slate-800">{{ $spikeDown }}</div>
                <div class="text-xs text-slate-400">Spike Turun</div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" style="background:#ede9fe">
                <i class="fas fa-trophy text-violet-500"></i>
            </div>
            <div>
                <div class="text-xl font-bold text-slate-800">{{ $milestone }}</div>
                <div class="text-xs text-slate-400">Milestone</div>
            </div>
        </div>
    </div>

    {{-- Filters + actions --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex flex-wrap gap-3 items-end justify-between">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Akun</label>
                <select name="account_id" class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                    <option value="">Semua Akun</option>
                    @foreach($accounts as $acc)
                        <option value="{{ $acc->id }}" @selected(request('account_id')==$acc->id)>{{ $acc->username }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Tipe</label>
                <select name="type" class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                    <option value="">Semua Tipe</option>
                    <option value="spike_up"   @selected(request('type')=='spike_up')>Spike Naik</option>
                    <option value="spike_down" @selected(request('type')=='spike_down')>Spike Turun</option>
                    <option value="milestone"  @selected(request('type')=='milestone')>Milestone</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Dari</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}"
                       class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Sampai</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}"
                       class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none">
            </div>
            <div class="flex items-center gap-2 pt-4">
                <label class="flex items-center gap-1.5 cursor-pointer">
                    <input type="checkbox" name="unread" value="1" {{ request('unread') ? 'checked' : '' }}
                           class="rounded" style="accent-color:#4F46E5">
                    <span class="text-sm text-slate-600">Belum dibaca saja</span>
                </label>
            </div>
            <button type="submit" class="px-4 py-2 bg-slate-700 text-white rounded-lg text-sm hover:bg-slate-600">
                <i class="fas fa-filter mr-1"></i>Filter
            </button>
        </form>

        @can('can-edit')
        <form method="POST" action="{{ route('alerts.read-all') }}">
            @csrf
            <button type="submit" class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-indigo-600 border border-indigo-200 hover:bg-indigo-50">
                <i class="fas fa-check-double"></i> Tandai Semua Dibaca
            </button>
        </form>
        @endcan
    </div>

    {{-- Alert list --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <table class="w-full text-sm">
            <thead style="background:#f8fafc; border-bottom:2px solid #e2e8f0;">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Waktu</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Tipe</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Akun</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Pesan</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Perubahan</th>
                    <th class="px-5 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
            @forelse($alerts as $alert)
                @php
                    $typeStyle = match($alert->alert_type) {
                        'spike_up'   => ['bg:#dcfce7','color:#166534','icon'=>'fa-arrow-trend-up','label'=>'Spike Naik'],
                        'spike_down' => ['bg:#fee2e2','color:#991b1b','icon'=>'fa-arrow-trend-down','label'=>'Spike Turun'],
                        default      => ['bg:#ede9fe','color:#5b21b6','icon'=>'fa-trophy','label'=>'Milestone'],
                    };
                @endphp
                <tr class="{{ !$alert->is_read ? 'bg-indigo-50/30' : '' }} hover:bg-slate-50 transition">
                    <td class="px-5 py-3 text-slate-500 text-xs whitespace-nowrap">
                        {{ $alert->triggered_at?->format('d/m/Y H:i') }}
                        <div class="text-slate-400">{{ $alert->triggered_at?->diffForHumans() }}</div>
                    </td>
                    <td class="px-5 py-3">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold"
                              style="background:{{ $typeStyle['bg'] }};color:{{ $typeStyle['color'] }}">
                            <i class="fas {{ $typeStyle['icon'] }} text-[10px]"></i>
                            {{ $typeStyle['label'] }}
                        </span>
                    </td>
                    <td class="px-5 py-3">
                        @if($alert->socialAccount)
                        <a href="{{ route('accounts.show', $alert->socialAccount) }}"
                           class="font-medium text-slate-700 hover:text-indigo-600 text-xs">
                            {{ $alert->socialAccount->username }}
                        </a>
                        <div class="text-xs text-slate-400 uppercase">{{ $alert->socialAccount->platform }}</div>
                        @else
                            <span class="text-slate-400 text-xs">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-slate-600 text-xs max-w-xs">{{ $alert->message }}</td>
                    <td class="px-5 py-3 text-right">
                        @if($alert->alert_type !== 'milestone')
                        <span class="font-semibold text-sm {{ $alert->alert_type === 'spike_up' ? 'text-green-600' : 'text-red-500' }}">
                            {{ $alert->alert_type === 'spike_up' ? '+' : '-' }}{{ number_format($alert->change_percent, 1) }}%
                        </span>
                        @endif
                        <div class="text-xs text-slate-400">{{ number_format($alert->current_value) }}</div>
                    </td>
                    <td class="px-5 py-3">
                        <div class="flex items-center justify-center gap-2">
                            @if($alert->socialAccount)
                            <a href="{{ route('accounts.show', $alert->socialAccount) }}"
                               class="px-2.5 py-1.5 rounded-lg text-xs text-indigo-600 border border-indigo-200 hover:bg-indigo-50">
                                Lihat Akun
                            </a>
                            @endif
                            @if(!$alert->is_read)
                            <form method="POST" action="{{ route('alerts.read', $alert) }}">
                                @csrf
                                <button type="submit" class="p-1.5 text-slate-400 hover:text-slate-600" title="Tandai dibaca">
                                    <i class="fas fa-check text-xs"></i>
                                </button>
                            </form>
                            @else
                            <span class="text-xs text-slate-300"><i class="fas fa-check-double"></i></span>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-12 text-center text-slate-400">Tidak ada alert ditemukan.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="px-5 py-3 border-t border-slate-100">{{ $alerts->links() }}</div>
    </div>
</div>
@endsection
