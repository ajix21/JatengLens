@extends('layouts.app')
@section('title', 'Setting Alert — SocioWatch Jateng')
@section('page-title', 'Setting Alert')
@section('breadcrumb')
    <a href="{{ route('alerts.index') }}" class="hover:text-indigo-600">Notifikasi</a>
    <span class="breadcrumb-sep">/</span>
    <span class="text-slate-500">Setting</span>
@endsection

@section('content')
<div class="max-w-2xl space-y-6">

    {{-- Global setting --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-800 mb-1">Setting Global</h3>
        <p class="text-xs text-slate-400 mb-5">Berlaku untuk semua akun yang tidak punya override sendiri.</p>

        <form method="POST" action="{{ route('alerts.settings.save') }}" class="space-y-5">
            @csrf

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        Threshold Spike Naik (%)
                    </label>
                    <div class="relative">
                        <input type="number" name="spike_up_threshold" step="0.1" min="0.1" max="999"
                               value="{{ old('spike_up_threshold', $global->spike_up_threshold) }}"
                               class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">%</span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Alert jika followers naik ≥ nilai ini</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        Threshold Spike Turun (%)
                    </label>
                    <div class="relative">
                        <input type="number" name="spike_down_threshold" step="0.1" min="0.1" max="999"
                               value="{{ old('spike_down_threshold', $global->spike_down_threshold) }}"
                               class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">%</span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Alert jika followers turun ≥ nilai ini</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">
                    Milestone Values <span class="text-slate-400 font-normal">(pisahkan dengan koma)</span>
                </label>
                <input type="text" name="milestone_values"
                       value="{{ old('milestone_values', $global->milestone_values ? implode(', ', $global->milestone_values) : '') }}"
                       placeholder="1000, 5000, 10000, 50000, 100000"
                       class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                <p class="text-xs text-slate-400 mt-1">Alert ketika followers melewati nilai ini</p>
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_active" value="1" id="is_active"
                       {{ $global->is_active ? 'checked' : '' }} class="rounded" style="accent-color:#4F46E5">
                <label for="is_active" class="text-sm text-slate-700">Aktifkan sistem alert</label>
            </div>

            <button type="submit"
                    class="px-5 py-2.5 rounded-xl text-white text-sm font-semibold"
                    style="background:linear-gradient(135deg,#4F46E5,#7C3AED)">
                Simpan Setting Global
            </button>
        </form>
    </div>

    {{-- Per-account overrides --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-800 mb-1">Override Per Akun</h3>
        <p class="text-xs text-slate-400 mb-4">Akun dengan setting sendiri akan mengabaikan setting global.</p>

        <div class="space-y-3">
            @foreach($accounts as $acc)
            @php $accSetting = $acc->alertSetting; @endphp
            <div class="flex items-center justify-between py-3 border-b border-slate-50 last:border-0">
                <div>
                    <span class="font-medium text-slate-700 text-sm">{{ $acc->username }}</span>
                    <span class="text-xs text-slate-400 ml-2 uppercase">{{ $acc->platform }}</span>
                    @if($accSetting)
                    <span class="ml-2 text-xs px-2 py-0.5 rounded-full"
                          style="background:#dbeafe;color:#1e40af">
                        ↑{{ $accSetting->spike_up_threshold }}% / ↓{{ $accSetting->spike_down_threshold }}%
                    </span>
                    @else
                    <span class="ml-2 text-xs text-slate-300">(global)</span>
                    @endif
                </div>
                <a href="{{ route('accounts.show', $acc->id) }}#alert-tab"
                   class="text-xs text-indigo-500 hover:underline">
                    Edit di detail akun →
                </a>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
