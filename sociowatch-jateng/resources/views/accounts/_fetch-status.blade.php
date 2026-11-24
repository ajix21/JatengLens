@if(in_array($account->platform, ['youtube', 'instagram', 'facebook']))
@php
    $fs = $account->last_fetch_status;
    $fsClass = match (true) {
        $fs === null => 'bg-gray-100 text-gray-500',
        $fs === \App\Enums\FetchStatus::Ok => 'bg-green-100 text-green-700',
        $fs === \App\Enums\FetchStatus::Unsupported => 'bg-yellow-100 text-yellow-700',
        default => 'bg-red-100 text-red-700',
    };
    $fsIcon = match (true) {
        $fs === null => 'fa-clock',
        $fs === \App\Enums\FetchStatus::Ok => 'fa-check-circle',
        $fs === \App\Enums\FetchStatus::Unsupported => 'fa-exclamation-triangle',
        default => 'fa-times-circle',
    };
@endphp
<div class="flex flex-wrap items-center gap-2 mt-1 text-xs">
    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full font-medium {{ $fsClass }}">
        <i class="fas {{ $fsIcon }}"></i> {{ $fs ? $fs->label() : 'Belum pernah diambil' }}
    </span>
    @if($account->last_fetched_at)
    <span class="text-gray-500">{{ $fs === \App\Enums\FetchStatus::Ok ? $account->last_fetched_at->diffForHumans() : 'terakhir berhasil ' . $account->last_fetched_at->diffForHumans() }}</span>
    @endif
    @if($account->external_id)
    <span class="text-gray-500 text-xs">ID: {{ $account->external_id }}</span>
    @endif
    @unless($account->auto_fetch)
    <span class="text-gray-500">Otomatis mati</span>
    @endunless
    @if($fs === \App\Enums\FetchStatus::Unsupported)
    <span class="text-yellow-700">Akun Personal — sinkronkan via Ekstensi Browser Companion.</span>
    @endif
</div>
@endif