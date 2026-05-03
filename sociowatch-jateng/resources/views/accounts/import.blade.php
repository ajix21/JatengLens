@extends('layouts.app')
@section('title', 'Import Akun — SocioWatch Jateng')
@section('page-title', 'Import Akun')
@section('breadcrumb')
<a href="{{ route('accounts.index') }}" class="hover:text-indigo-600">Akun</a>
<span class="breadcrumb-sep">/</span>
<span class="text-slate-600">Import</span>
@endsection

@section('content')
<div class="max-w-3xl space-y-5">

    {{-- Import errors --}}
    @if(session('import_errors'))
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
        <p class="text-sm font-semibold text-amber-800 mb-2 flex items-center gap-2">
            <i class="fas fa-exclamation-triangle"></i> Beberapa baris dilewati:
        </p>
        <ul class="text-xs text-amber-700 space-y-1 list-disc list-inside">
            @foreach(session('import_errors') as $err)
            <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- Instructions --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h2 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-info-circle text-blue-500"></i> Petunjuk Import
        </h2>
        <div class="space-y-3 text-sm text-gray-600">
            <ol class="list-decimal list-inside space-y-2">
                <li>Download template CSV di bawah ini</li>
                <li>Isi data akun sesuai format (baris pertama adalah header)</li>
                <li>Kolom <strong>platform</strong> dan <strong>username</strong> wajib diisi</li>
                <li>Platform valid: <code class="bg-gray-100 px-1 rounded text-xs">instagram, twitter, facebook, tiktok, youtube</code></li>
                <li>Kolom <strong>kategori</strong>: nama kategori harus persis sama (case-insensitive)</li>
                <li>Kolom <strong>wilayah</strong>: nama wilayah harus persis sama (contoh: "Kota Semarang")</li>
                <li>Akun dengan platform+username yang sudah ada akan dilewati</li>
            </ol>

            <div class="pt-3 border-t border-gray-100 flex gap-3 flex-wrap">
                <a href="{{ route('accounts.import.template') }}"
                   class="flex items-center gap-2 bg-green-50 hover:bg-green-100 text-green-700 border border-green-200 text-sm font-medium px-4 py-2 rounded-lg transition">
                    <i class="fas fa-download"></i> Download Template CSV
                </a>
            </div>
        </div>
    </div>

    {{-- Kategori & Wilayah reference --}}
    <div class="grid grid-cols-2 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <h3 class="font-semibold text-gray-700 text-sm mb-3 flex items-center gap-2">
                <i class="fas fa-tags text-indigo-500"></i> Kategori Tersedia
            </h3>
            <div class="flex flex-wrap gap-1.5">
                @foreach($categories as $cat)
                <span class="text-xs px-2 py-0.5 rounded-full text-white" style="background:{{ $cat->color }}">
                    {{ $cat->name }}
                </span>
                @endforeach
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <h3 class="font-semibold text-gray-700 text-sm mb-3 flex items-center gap-2">
                <i class="fas fa-map-pin text-blue-500"></i> Contoh Nama Wilayah
            </h3>
            <div class="text-xs text-gray-500 space-y-0.5 max-h-24 overflow-y-auto">
                @foreach($regions->take(8) as $r)
                <div>{{ $r->name }}</div>
                @endforeach
                @if($regions->count() > 8)
                <div class="text-gray-400">+ {{ $regions->count() - 8 }} lainnya</div>
                @endif
            </div>
        </div>
    </div>

    {{-- Upload form --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6"
         x-data="{ dragging: false, fileName: '' }">
        <h2 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-upload text-indigo-500"></i> Upload File
        </h2>
        <form method="POST" action="{{ route('accounts.import.process') }}" enctype="multipart/form-data">
            @csrf

            {{-- Dropzone --}}
            <label for="fileInput"
                   class="flex flex-col items-center justify-center w-full h-40 border-2 border-dashed rounded-xl cursor-pointer transition"
                   :class="dragging ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300 bg-gray-50 hover:bg-gray-100'"
                   @dragover.prevent="dragging=true"
                   @dragleave.prevent="dragging=false"
                   @drop.prevent="dragging=false; fileName=$event.dataTransfer.files[0]?.name; $el.querySelector('input').files=$event.dataTransfer.files">
                <i class="fas fa-file-csv text-3xl text-gray-400 mb-2"></i>
                <p class="text-sm text-gray-600 font-medium" x-text="fileName || 'Klik atau seret file CSV/Excel ke sini'"></p>
                <p class="text-xs text-gray-400 mt-1">Format: .csv, .xlsx, .xls · Maks. 5MB</p>
                <input id="fileInput" type="file" name="file" class="hidden" accept=".csv,.xlsx,.xls"
                       @change="fileName=$event.target.files[0]?.name">
            </label>

            @error('file')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror

            <div class="flex gap-3 mt-4">
                <a href="{{ route('accounts.index') }}"
                   class="flex-1 text-center border border-gray-200 text-gray-600 hover:bg-gray-50 text-sm font-medium py-2.5 rounded-lg transition">
                    Batal
                </a>
                <button type="submit"
                        class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2.5 rounded-lg transition flex items-center justify-center gap-2">
                    <i class="fas fa-file-import"></i> Import Sekarang
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
