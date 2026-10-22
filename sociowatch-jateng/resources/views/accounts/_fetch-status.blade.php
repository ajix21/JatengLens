@if(in_array($account->platform, ['youtube', 'instagram']))
@php
    $fs = $account->last_fetch_status;
    $fsClass = match (true) {
        $fs === null => 'bg-gray-100 text-gray-500',
        $fs === \App\Enums\FetchStatus::Ok => 'bg-green-100 text-green-700',
        $fs === \App\Enums\FetchStatus::Unsupported => 'bg-yellow-100 text-yellow-700',
        default => 'bg-red-100 text-red-700',
    };
@endphp
<div class="flex flex-wrap items-center gap-2 mt-1 text-xs">
    <span class="inline-flex items-center px-2 py-0.5 rounded-full font-medium {{ $fsClass }}">
        {{ $fs ? $fs->label() : 'Belum pernah diambil' }}
    </span>
    @if($account->last_fetched_at)
    <span class="text-gray-400">{{ $account->last_fetched_at->diffForHumans() }}</span>
    @endif
    @unless($account->auto_fetch)
    <span class="text-gray-400">Otomatis mati</span>
    @endunless
    @if($fs === \App\Enums\FetchStatus::Unsupported)
    <span class="text-yellow-700">Tidak bisa diambil otomatis — isi manual.</span>
    @endif
</div>
@endif
