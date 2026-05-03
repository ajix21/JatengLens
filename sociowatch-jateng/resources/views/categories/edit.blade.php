@extends('layouts.app')
@section('title', 'Edit Kategori')
@section('page-title', 'Edit Kategori: {{ $category->name }}')

@section('content')
<div class="max-w-lg">
<form method="POST" action="{{ route('categories.update', $category) }}">
@csrf @method('PUT')
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
    <h3 class="font-semibold text-gray-700 flex items-center gap-2">
        <i class="fas fa-tag text-indigo-500"></i> Edit Kategori
    </h3>
    <div>
        <label class="block text-sm font-medium text-gray-600 mb-1">Nama Kategori <span class="text-red-500">*</span></label>
        <input type="text" name="name" value="{{ old('name', $category->name) }}" required
               class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none {{ $errors->has('name') ? 'border-red-400' : '' }}">
        @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>
    <div x-data="{ color: '{{ old('color', $category->color) }}' }">
        <label class="block text-sm font-medium text-gray-600 mb-1">Warna Marker <span class="text-red-500">*</span></label>
        <div class="flex items-center gap-3">
            <input type="color" x-model="color" @input="$refs.colorText.value = color"
                   :value="color" class="w-12 h-12 rounded-lg border border-gray-200 cursor-pointer p-1">
            <input type="text" name="color" x-ref="colorText" :value="color" @input="color = $event.target.value"
                   maxlength="7" pattern="#[0-9A-Fa-f]{6}"
                   class="w-36 text-sm border border-gray-200 rounded-lg px-3 py-2.5 font-mono focus:ring-2 focus:ring-indigo-300 focus:outline-none">
            <div class="w-10 h-10 rounded-lg border border-gray-200" :style="`background:${color}`"></div>
        </div>
        <div class="flex flex-wrap gap-2 mt-2">
            @foreach(['#3B82F6','#EF4444','#10B981','#F59E0B','#8B5CF6','#EC4899','#14B8A6','#F97316'] as $preset)
            <button type="button" @click="color = '{{ $preset }}'; $refs.colorText.value = '{{ $preset }}'"
                    class="w-7 h-7 rounded-full border-2 border-white shadow-sm hover:scale-110 transition"
                    style="background:{{ $preset }}"></button>
            @endforeach
        </div>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-600 mb-1">Deskripsi</label>
        <textarea name="description" rows="3"
                  class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-300 focus:outline-none resize-none">{{ old('description', $category->description) }}</textarea>
    </div>
    <div class="flex gap-3 pt-2">
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-lg text-sm font-medium transition">
            <i class="fas fa-save mr-2"></i>Simpan Perubahan
        </button>
        <a href="{{ route('categories.index') }}" class="border border-gray-200 text-gray-600 hover:bg-gray-50 px-5 py-2.5 rounded-lg text-sm transition">Batal</a>
    </div>
</div>
</form>
</div>
@endsection
