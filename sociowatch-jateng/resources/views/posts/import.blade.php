@extends('layouts.app')
@section('title', 'Import Postingan — SocioWatch Jateng')
@section('page-title', 'Import Postingan')
@section('breadcrumb')
<a href="{{ route('posts.index') }}" class="hover:text-indigo-600">Postingan</a>
<span class="breadcrumb-sep">/</span><span class="text-slate-600">Import</span>
@endsection

@section('content')
<div class="max-w-3xl space-y-5">

    @if(session('import_errors'))
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
        <p class="text-sm font-semibold text-amber-800 mb-2"><i class="fas fa-exclamation-triangle mr-1"></i>Beberapa baris dilewati:</p>
        <ul class="text-xs text-amber-700 list-disc list-inside space-y-0.5 max-h-28 overflow-y-auto">
            @foreach(session('import_errors') as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h2 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-info-circle text-blue-500"></i> Petunjuk Import
        </h2>
        <ol class="text-sm text-gray-600 list-decimal list-inside space-y-2 mb-4">
            <li>Download template CSV di bawah</li>
            <li>Isi data postingan — kolom <strong>platform</strong>, <strong>username</strong>, <strong>content</strong>, <strong>posted_at</strong> wajib diisi</li>
            <li>Kolom <strong>platform</strong>: instagram / twitter / facebook / tiktok / youtube</li>
            <li>Kolom <strong>username</strong> harus sesuai akun yang sudah terdaftar</li>
            <li>Keyword akan terdeteksi otomatis dari konten saat proses import</li>
        </ol>
        <a href="{{ route('posts.import.template') }}"
           class="inline-flex items-center gap-2 bg-green-50 hover:bg-green-100 text-green-700 border border-green-200 text-sm font-medium px-4 py-2 rounded-lg transition">
            <i class="fas fa-download"></i> Download Template CSV
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6"
         x-data="{ fileName: '' }">
        <h2 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-upload text-indigo-500"></i> Upload File
        </h2>
        <form method="POST" action="{{ route('posts.import.process') }}" enctype="multipart/form-data">
            @csrf
            <label for="fileInput"
                   class="flex flex-col items-center justify-center w-full h-40 border-2 border-dashed rounded-xl cursor-pointer transition border-gray-300 bg-gray-50 hover:bg-gray-100"
                   @dragover.prevent @drop.prevent="fileName=$event.dataTransfer.files[0]?.name">
                <i class="fas fa-file-csv text-3xl text-gray-400 mb-2"></i>
                <p class="text-sm text-gray-600 font-medium" x-text="fileName || 'Klik atau seret file CSV/Excel ke sini'"></p>
                <p class="text-xs text-gray-400 mt-1">Format: .csv, .xlsx, .xls · Maks. 10MB</p>
                <input id="fileInput" type="file" name="file" class="hidden" accept=".csv,.xlsx,.xls"
                       @change="fileName=$event.target.files[0]?.name">
            </label>
            @error('file')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror

            <div class="flex gap-3 mt-4">
                <a href="{{ route('posts.index') }}"
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
