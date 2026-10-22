@extends('layouts.app')
@section('title', 'Auto-Fetch — SocioWatch Jateng')
@section('page-title', 'Auto-Fetch')
@section('breadcrumb')
<span class="text-slate-600">Auto-Fetch</span>
@endsection

@section('content')
<div class="max-w-4xl space-y-5">

    @if($igAuthError)
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-800 flex items-center gap-2">
        <i class="fas fa-exclamation-triangle"></i>
        Token Instagram ditolak — perbarui token (lihat docs/setup-auto-fetch.md).
    </div>
    @endif

    @if($igDaysLeft !== null)
    @php $igWarnClass = $igDaysLeft < 0 ? 'bg-red-50 border-red-200 text-red-800' : ($igDaysLeft <= 7 ? 'bg-yellow-50 border-yellow-200 text-yellow-800' : 'bg-gray-50 border-gray-200 text-gray-600'); @endphp
    <div class="border rounded-xl p-4 text-sm {{ $igWarnClass }}">
        <i class="fas fa-clock mr-1"></i>
        @if($igDaysLeft < 0)
            Token Instagram sudah kedaluwarsa {{ abs($igDaysLeft) }} hari yang lalu.
        @else
            Token Instagram berlaku {{ $igDaysLeft }} hari lagi.
        @endif
    </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach($platforms as $p)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h3 class="font-semibold text-gray-800 mb-3">{{ $p['label'] }}</h3>
            <dl class="text-sm space-y-2">
                <div class="flex justify-between">
                    <dt class="text-gray-500">Kredensial</dt>
                    <dd>
                        @if($p['configured'])
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Terpasang</span>
                        @else
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Belum diatur</span>
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Jalan terakhir</dt>
                    <dd class="text-gray-700">{{ $p['last_run'] ? $p['last_run']->diffForHumans() : 'Belum pernah' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Akun berhasil</dt>
                    <dd class="text-green-700 font-medium">{{ $p['ok'] }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Akun gagal</dt>
                    <dd class="text-red-700 font-medium">{{ $p['failed'] }}</dd>
                </div>
            </dl>
        </div>
        @endforeach
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 font-semibold text-gray-800 text-sm">Akun gagal diambil</div>
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-4 py-2 text-left">Akun</th>
                    <th class="px-4 py-2 text-left">Platform</th>
                    <th class="px-4 py-2 text-left">Status</th>
                    <th class="px-4 py-2 text-left">Pesan</th>
                    <th class="px-4 py-2 text-left">Waktu</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($failed as $acc)
                <tr>
                    <td class="px-4 py-2">
                        <a href="{{ route('accounts.show', $acc) }}" class="text-indigo-600 hover:underline">{{ '@' . $acc->username }}</a>
                    </td>
                    <td class="px-4 py-2 text-gray-600">{{ ucfirst($acc->platform) }}</td>
                    <td class="px-4 py-2">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $acc->last_fetch_status === \App\Enums\FetchStatus::Unsupported ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700' }}">
                            {{ $acc->last_fetch_status->label() }}
                        </span>
                    </td>
                    <td class="px-4 py-2 text-gray-500 text-xs">{{ $acc->last_fetch_error ?? '-' }}</td>
                    <td class="px-4 py-2 text-gray-400 text-xs">{{ $acc->last_fetched_at?->diffForHumans() ?? '-' }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Tidak ada akun yang gagal.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
@endsection
