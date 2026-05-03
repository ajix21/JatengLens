<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1e293b; }
    .header { background: #1e2d4d; color: white; padding: 14px 20px; margin-bottom: 16px; }
    .header-top { display: flex; align-items: center; gap: 12px; margin-bottom: 6px; }
    .logo-box { width: 36px; height: 36px; background: linear-gradient(135deg,#4F46E5,#7C3AED);
                border-radius: 8px; display: flex; align-items: center; justify-content: center; }
    .brand-main { font-size: 16px; font-weight: bold; }
    .brand-sub  { font-size: 9px; color: #818cf8; letter-spacing: 0.1em; }
    .report-title { font-size: 13px; font-weight: bold; margin-bottom: 2px; }
    .report-meta  { font-size: 8px; color: #cbd5e1; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #1e2d4d; color: white; padding: 6px 7px; text-align: left;
         font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: .05em; }
    td { padding: 5px 7px; border-bottom: 1px solid #f1f5f9; font-size: 8px; }
    tr:nth-child(even) td { background: #f8fafc; }
    .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 7px; font-weight: bold; }
    .badge-active { background: #dcfce7; color: #166534; }
    .badge-inactive { background: #fee2e2; color: #991b1b; }
    .footer { margin-top: 14px; font-size: 7.5px; color: #94a3b8; text-align: center; }
    .stat-row { display: inline-block; margin-right: 20px; }
    .stat-val  { font-size: 14px; font-weight: bold; color: #4F46E5; }
    .stat-label { font-size: 7.5px; color: #64748b; }
    .stats-bar { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;
                 padding: 8px 14px; margin-bottom: 14px; }
</style>
</head>
<body>

{{-- Header --}}
<div class="header">
    <div class="header-top">
        <div>
            <div class="brand-main">SocioWatch Jateng</div>
            <div class="brand-sub">SISTEM PEMANTAUAN AKUN MEDIA SOSIAL · JAWA TENGAH</div>
        </div>
    </div>
    <div class="report-title">LAPORAN DATA AKUN MEDIA SOSIAL</div>
    <div class="report-meta">
        Dicetak: {{ now()->format('d F Y, H:i') }} WIB
        &nbsp;·&nbsp;
        Filter: {{ $filters }}
    </div>
</div>

{{-- Stats bar --}}
<div class="stats-bar">
    <span class="stat-row">
        <div class="stat-val">{{ $accounts->count() }}</div>
        <div class="stat-label">Total Akun</div>
    </span>
    <span class="stat-row">
        <div class="stat-val">{{ number_format($accounts->sum('followers_count')) }}</div>
        <div class="stat-label">Total Followers</div>
    </span>
    <span class="stat-row">
        <div class="stat-val">{{ $accounts->groupBy('platform')->count() }}</div>
        <div class="stat-label">Platform</div>
    </span>
    <span class="stat-row">
        <div class="stat-val">{{ $accounts->groupBy('region_id')->count() }}</div>
        <div class="stat-label">Wilayah</div>
    </span>
</div>

{{-- Data table --}}
<table>
    <thead>
        <tr>
            <th style="width:3%">No</th>
            <th style="width:9%">Platform</th>
            <th style="width:13%">Username</th>
            <th style="width:16%">Display Name</th>
            <th style="width:10%">Kategori</th>
            <th style="width:12%">Kota/Kab</th>
            <th style="width:9%">Followers</th>
            <th style="width:14%">Admin Akun</th>
            <th style="width:6%">Status</th>
            <th style="width:8%">Tgl Input</th>
        </tr>
    </thead>
    <tbody>
    @foreach($accounts as $i => $acc)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td><strong>{{ strtoupper($acc->platform) }}</strong></td>
            <td>{{ $acc->username }}</td>
            <td>{{ $acc->display_name }}</td>
            <td>{{ $acc->category?->name ?? '-' }}</td>
            <td>{{ $acc->region?->name ?? '-' }}</td>
            <td>{{ number_format($acc->followers_count) }}</td>
            <td>{{ $acc->admins->pluck('full_name')->join(', ') ?: '-' }}</td>
            <td>
                <span class="badge {{ $acc->is_active ? 'badge-active' : 'badge-inactive' }}">
                    {{ $acc->is_active ? 'Aktif' : 'Nonaktif' }}
                </span>
            </td>
            <td>{{ $acc->created_at?->format('d/m/Y') }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="footer">
    Dokumen ini digenerate secara otomatis oleh SocioWatch Jateng &mdash; Dinas Komunikasi &amp; Informatika Provinsi Jawa Tengah
</div>
</body>
</html>
