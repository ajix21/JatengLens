<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1e293b; }
    .header { background: #1e2d4d; color: white; padding: 14px 20px; margin-bottom: 16px; }
    .brand-main { font-size: 16px; font-weight: bold; }
    .brand-sub  { font-size: 9px; color: #818cf8; letter-spacing: 0.1em; }
    .report-title { font-size: 13px; font-weight: bold; margin-bottom: 2px; }
    .report-meta  { font-size: 8px; color: #cbd5e1; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #1e2d4d; color: white; padding: 6px 7px; text-align: left;
         font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: .05em; }
    td { padding: 5px 7px; border-bottom: 1px solid #f1f5f9; font-size: 8px; vertical-align: top; }
    tr:nth-child(even) td { background: #f8fafc; }
    .footer { margin-top: 14px; font-size: 7.5px; color: #94a3b8; text-align: center; }
    .num { text-align: right; }
</style>
</head>
<body>

<div class="header">
    <div class="brand-main">SocioWatch Jateng</div>
    <div class="brand-sub">SISTEM PEMANTAUAN AKUN MEDIA SOSIAL · JAWA TENGAH</div>
    <br>
    <div class="report-title">REKAPITULASI WILAYAH — 35 KAB/KOTA JAWA TENGAH</div>
    <div class="report-meta">Dicetak: {{ now()->format('d F Y, H:i') }} WIB</div>
</div>

<table>
    <thead>
        <tr>
            <th style="width:3%">No</th>
            <th style="width:20%">Nama Wilayah</th>
            <th style="width:8%">Tipe</th>
            <th style="width:9%">Jml Akun</th>
            <th style="width:13%">Total Followers</th>
            <th style="width:14%">Kategori Dominan</th>
            <th style="width:33%">Breakdown Kategori</th>
        </tr>
    </thead>
    <tbody>
    @foreach($regions as $i => $region)
        @php
            $accounts   = $region->socialAccounts;
            $byCategory = $accounts->groupBy('category_id');
            $dominant   = $byCategory->sortByDesc(fn($g)=>$g->count())->first();
            $breakdown  = $byCategory->map(fn($g)=>($g->first()->category?->name??'-').': '.$g->count())->join(' | ');
        @endphp
        <tr>
            <td>{{ $i + 1 }}</td>
            <td><strong>{{ $region->name }}</strong></td>
            <td>{{ ucfirst($region->type) }}</td>
            <td class="num">{{ $accounts->count() }}</td>
            <td class="num">{{ number_format($accounts->sum('followers_count')) }}</td>
            <td>{{ $dominant?->first()?->category?->name ?? '-' }}</td>
            <td style="font-size:7.5px">{{ $breakdown ?: '-' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="footer">
    Dokumen ini digenerate secara otomatis oleh SocioWatch Jateng &mdash; Dinas Komunikasi &amp; Informatika Provinsi Jawa Tengah
</div>
</body>
</html>
