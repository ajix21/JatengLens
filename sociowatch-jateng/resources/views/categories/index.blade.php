@extends('layouts.app')
@section('title', 'Manajemen Kategori — SocioWatch Jateng')
@section('page-title', 'Manajemen Kategori')

@section('content')
<div class="space-y-5">
    @can('can-edit')
    <div class="flex justify-end">
        <a href="{{ route('categories.create') }}" class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm px-4 py-2.5 rounded-lg transition flex items-center gap-2">
            <i class="fas fa-plus"></i> Tambah Kategori
        </a>
    </div>
    @endcan

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        @forelse($categories as $cat)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-start justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white text-xl font-bold shadow-sm"
                         style="background:{{ $cat->color }}">
                        {{ strtoupper(substr($cat->name, 0, 1)) }}
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">{{ $cat->name }}</h3>
                        <div class="flex items-center gap-1.5 mt-0.5">
                            <span class="w-3 h-3 rounded-full" style="background:{{ $cat->color }}"></span>
                            <code class="text-xs text-gray-400">{{ $cat->color }}</code>
                        </div>
                    </div>
                </div>
                <div class="flex gap-1">
                    @can('can-edit')
                    <a href="{{ route('categories.edit', $cat) }}"
                       class="p-1.5 text-amber-500 hover:bg-amber-50 rounded-lg transition">
                        <i class="fas fa-pen text-xs"></i>
                    </a>
                    @endcan
                    @can('can-delete')
                    <form method="POST" action="{{ route('categories.destroy', $cat) }}" class="inline"
                          onsubmit="return confirm('Hapus kategori ini?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="p-1.5 text-red-400 hover:bg-red-50 rounded-lg transition">
                            <i class="fas fa-trash text-xs"></i>
                        </button>
                    </form>
                    @endcan
                </div>
            </div>
            @if($cat->description)
            <p class="text-sm text-gray-500 mb-3">{{ $cat->description }}</p>
            @endif
            <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                <span class="text-sm text-gray-600">
                    <span class="font-bold text-gray-800">{{ $cat->social_accounts_count }}</span> akun
                </span>
                <div class="flex gap-1">
                    @foreach(['#fff','#ffd','#ffa','#f66','#900'] as $i => $c)
                    <div class="w-5 h-5 rounded" style="background:{{ $c }};opacity:{{ 0.3 + $i*0.17 }};border:1px solid {{ $cat->color }}33"></div>
                    @endforeach
                    <div class="w-5 h-5 rounded" style="background:{{ $cat->color }}"></div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-span-3 text-center py-12 text-gray-400">
            <i class="fas fa-tags text-3xl mb-3 block"></i>
            Belum ada kategori. Tambahkan kategori pertama.
        </div>
        @endforelse
    </div>
</div>
@endsection
