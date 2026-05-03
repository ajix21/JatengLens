@extends('layouts.app')

@section('title', 'Log Aktivitas — SocioWatch Jateng')
@section('page-title', 'Log Aktivitas User')
@section('breadcrumb')
    <a href="{{ route('users.index') }}" class="hover:text-indigo-600">Manajemen User</a>
    <span class="breadcrumb-sep">/</span>
    <span class="text-slate-500">Log Aktivitas</span>
@endsection

@section('content')
<div class="space-y-5">

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">User</label>
            <select name="user_id" class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                <option value="">Semua User</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" @selected(request('user_id')==$u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Aksi</label>
            <select name="action" class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                <option value="">Semua Aksi</option>
                @foreach(['login','logout','create','update','delete'] as $act)
                    <option value="{{ $act }}" @selected(request('action')==$act)>{{ ucfirst($act) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Dari Tanggal</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}"
                   class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Sampai Tanggal</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}"
                   class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none">
        </div>
        <button type="submit" class="px-4 py-2 bg-slate-700 text-white rounded-lg text-sm hover:bg-slate-600">
            <i class="fas fa-filter mr-1"></i>Filter
        </button>
        <a href="{{ route('users.activity-log') }}" class="px-4 py-2 border border-slate-200 text-slate-600 rounded-lg text-sm hover:bg-slate-50">
            Reset
        </a>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <table class="w-full text-sm">
            <thead style="background:#f8fafc; border-bottom:2px solid #e2e8f0;">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Waktu</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">User</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Aksi</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Target</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">IP Address</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
            @forelse($logs as $log)
                @php
                    $actionColor = match($log->action) {
                        'login'   => 'background:#dcfce7;color:#166534;',
                        'logout'  => 'background:#f1f5f9;color:#475569;',
                        'create'  => 'background:#dbeafe;color:#1e40af;',
                        'update'  => 'background:#fef3c7;color:#92400e;',
                        'delete'  => 'background:#fee2e2;color:#991b1b;',
                        default   => 'background:#f1f5f9;color:#475569;',
                    };
                @endphp
                <tr class="hover:bg-slate-50 transition">
                    <td class="px-5 py-3 text-slate-500 text-xs whitespace-nowrap">
                        {{ $log->created_at->format('d/m/Y H:i:s') }}
                    </td>
                    <td class="px-5 py-3">
                        <div class="font-medium text-slate-800">{{ $log->user?->name ?? '—' }}</div>
                        <div class="text-xs text-slate-400">{{ $log->user?->email }}</div>
                    </td>
                    <td class="px-5 py-3">
                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold" style="{{ $actionColor }}">
                            {{ strtoupper($log->action) }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-slate-600 text-xs">
                        @if($log->target_type)
                            {{ $log->target_type }} #{{ $log->target_id }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-5 py-3 text-slate-500 text-xs font-mono">{{ $log->ip_address ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">Tidak ada log ditemukan.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="px-5 py-3 border-t border-slate-100">{{ $logs->links() }}</div>
    </div>
</div>
@endsection
