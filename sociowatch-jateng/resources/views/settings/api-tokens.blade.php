@extends('layouts.app')
@section('title', 'API Tokens — SocioWatch Jateng')
@section('page-title', 'API Tokens')
@section('breadcrumb')
<span class="text-slate-600">API Tokens</span>
@endsection

@section('content')
<div class="max-w-3xl space-y-5">

    {{-- New token banner --}}
    @if(session('new_token'))
    <div class="bg-green-50 border border-green-200 rounded-xl p-4" x-data>
        <p class="text-sm font-semibold text-green-800 mb-2 flex items-center gap-2">
            <i class="fas fa-check-circle"></i> Token baru berhasil dibuat — salin sekarang!
        </p>
        <div class="flex items-center gap-2">
            <code class="flex-1 bg-white border border-green-200 rounded-lg px-3 py-2 text-xs font-mono text-green-900 break-all">
                {{ session('new_token') }}
            </code>
            <button @click="navigator.clipboard.writeText('{{ session('new_token') }}')"
                    class="flex-shrink-0 bg-green-600 text-white text-xs px-3 py-2 rounded-lg hover:bg-green-700 transition">
                <i class="fas fa-copy mr-1"></i>Salin
            </button>
        </div>
        <p class="text-xs text-green-600 mt-2">Token ini hanya ditampilkan sekali. Simpan di tempat yang aman.</p>
    </div>
    @endif

    {{-- Create token --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h2 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-plus-circle text-indigo-500"></i> Buat Token Baru
        </h2>
        <form method="POST" action="{{ route('api-tokens.store') }}" class="flex gap-3">
            @csrf
            <input type="text" name="name" placeholder="Nama token (contoh: Monitoring Bot, Scraper v1)"
                   required
                   class="flex-1 border border-gray-200 rounded-lg px-4 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
            <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition flex items-center gap-2">
                <i class="fas fa-key"></i> Buat Token
            </button>
        </form>
        @error('name')
        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>

    {{-- Token list --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-semibold text-gray-800 flex items-center gap-2">
                <i class="fas fa-list text-gray-400"></i> Token Aktif ({{ $tokens->count() }})
            </h2>
        </div>

        @if($tokens->isEmpty())
        <div class="flex flex-col items-center py-12 text-gray-400">
            <i class="fas fa-key text-3xl mb-3 opacity-30"></i>
            <p class="text-sm">Belum ada token API.</p>
        </div>
        @else
        <div class="divide-y divide-gray-50">
            @foreach($tokens as $token)
            <div class="flex items-center gap-4 px-6 py-4">
                <div class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-key text-indigo-600 text-sm"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-800">{{ $token->name }}</p>
                    <p class="text-xs text-gray-400">
                        Dibuat: {{ $token->created_at->format('d M Y, H:i') }}
                        @if($token->last_used_at)
                        · Terakhir digunakan: {{ $token->last_used_at->diffForHumans() }}
                        @else
                        · Belum pernah digunakan
                        @endif
                    </p>
                </div>
                <form method="POST" action="{{ route('api-tokens.destroy', $token->id) }}"
                      onsubmit="return confirm('Hapus token ini?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="text-red-500 hover:text-red-700 text-xs border border-red-200 hover:bg-red-50 px-3 py-1.5 rounded-lg transition flex items-center gap-1">
                        <i class="fas fa-trash text-xs"></i> Hapus
                    </button>
                </form>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- API Usage --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h2 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-code text-gray-400"></i> Cara Penggunaan API
        </h2>
        <div class="space-y-3 text-sm text-gray-600">
            <p>Sertakan token di header setiap request:</p>
            <pre class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs font-mono overflow-x-auto">Authorization: Bearer {your-token}
Accept: application/json</pre>

            <p class="font-semibold text-gray-700 mt-4">Endpoint tersedia:</p>
            <div class="space-y-2">
                @foreach([
                    ['GET',  '/api/accounts',         'Daftar semua akun aktif'],
                    ['GET',  '/api/regions',           'Data wilayah beserta statistik'],
                    ['GET',  '/api/alerts/unread',     'Alert belum dibaca (5 terbaru)'],
                    ['POST', '/api/accounts/{id}/update-stats', 'Update statistik akun'],
                ] as [$method, $path, $desc])
                <div class="flex items-start gap-3 bg-gray-50 rounded-lg p-3">
                    <span class="text-xs font-bold px-2 py-0.5 rounded flex-shrink-0 {{ $method === 'GET' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700' }}">
                        {{ $method }}
                    </span>
                    <code class="text-xs font-mono text-gray-700 flex-shrink-0">{{ $path }}</code>
                    <span class="text-xs text-gray-500">{{ $desc }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
