@extends('layouts.app')

@section('title', 'Manajemen User — SocioWatch Jateng')
@section('page-title', 'Manajemen User')
@section('breadcrumb')
    <span class="text-slate-500">Manajemen User</span>
@endsection

@section('content')
<div class="space-y-6">

    {{-- Header bar --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex items-center gap-2">
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Cari nama / email…"
                   class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none">
            <select name="role" class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:outline-none">
                <option value="">Semua Role</option>
                <option value="superadmin" @selected(request('role')=='superadmin')>Superadmin</option>
                <option value="admin"      @selected(request('role')=='admin')>Admin</option>
                <option value="viewer"     @selected(request('role')=='viewer')>Viewer</option>
            </select>
            <button type="submit" class="px-3 py-2 bg-slate-700 text-white rounded-lg text-sm hover:bg-slate-600">
                <i class="fas fa-search"></i>
            </button>
        </form>
        <a href="{{ route('users.create') }}"
           class="flex items-center gap-2 px-4 py-2 rounded-lg text-white text-sm font-medium"
           style="background:linear-gradient(135deg,#4F46E5,#7C3AED)">
            <i class="fas fa-plus"></i> Tambah User
        </a>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-2xl shadow-sm overflow-hidden border border-slate-100">
        <table class="w-full text-sm">
            <thead style="background:#f8fafc; border-bottom:2px solid #e2e8f0;">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Nama</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Email</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Role</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Last Login</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
            @forelse ($users as $user)
                <tr class="hover:bg-slate-50 transition">
                    <td class="px-5 py-3 font-medium text-slate-800">
                        {{ $user->name }}
                        @if($user->id === auth()->id())
                            <span class="ml-1 text-xs text-indigo-500">(Anda)</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-slate-600">{{ $user->email }}</td>
                    <td class="px-5 py-3">
                        @php
                            $roleColor = match($user->role) {
                                'superadmin' => 'background:#fef3c7; color:#92400e;',
                                'admin'      => 'background:#dbeafe; color:#1e40af;',
                                default      => 'background:#f1f5f9; color:#475569;',
                            };
                        @endphp
                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold" style="{{ $roleColor }}">
                            {{ strtoupper($user->role) }}
                        </span>
                    </td>
                    <td class="px-5 py-3">
                        @if($user->is_active)
                            <span class="flex items-center gap-1.5 text-green-600 text-xs font-medium">
                                <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>Aktif
                            </span>
                        @else
                            <span class="flex items-center gap-1.5 text-red-500 text-xs font-medium">
                                <span class="w-1.5 h-1.5 bg-red-400 rounded-full"></span>Nonaktif
                            </span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-slate-500 text-xs">
                        {{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Belum pernah' }}
                    </td>
                    <td class="px-5 py-3">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('users.edit', $user) }}"
                               class="px-3 py-1.5 rounded-lg text-xs font-medium text-indigo-600 border border-indigo-200 hover:bg-indigo-50">
                                <i class="fas fa-edit"></i> Edit
                            </a>

                            {{-- Toggle active --}}
                            @if($user->id !== auth()->id())
                            <form method="POST" action="{{ route('users.toggle-active', $user) }}">
                                @csrf
                                <button type="submit"
                                        class="px-3 py-1.5 rounded-lg text-xs font-medium border
                                               {{ $user->is_active ? 'text-amber-600 border-amber-200 hover:bg-amber-50' : 'text-green-600 border-green-200 hover:bg-green-50' }}">
                                    <i class="fas {{ $user->is_active ? 'fa-ban' : 'fa-check-circle' }}"></i>
                                    {{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                            </form>

                            {{-- Delete --}}
                            <form method="POST" action="{{ route('users.destroy', $user) }}"
                                  onsubmit="return confirm('Hapus user {{ $user->name }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-medium text-red-600 border border-red-200 hover:bg-red-50">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-10 text-center text-slate-400">Tidak ada user ditemukan.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="px-5 py-3 border-t border-slate-100">{{ $users->links() }}</div>
    </div>
</div>
@endsection
