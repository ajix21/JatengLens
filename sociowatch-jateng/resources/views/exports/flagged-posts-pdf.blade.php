<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        @font-face { font-family: 'DejaVu Sans'; src: url('{{ storage_path('fonts/DejaVuSans.ttf') }}'); }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1e293b; background: #fff; }
        .header { background: #1e2d4d; color: #fff; padding: 14px 20px; margin-bottom: 16px; }
        .header h1 { font-size: 14pt; font-weight: bold; letter-spacing: .03em; }
        .header p  { font-size: 8pt; opacity: .75; margin-top: 3px; }
        .meta { display: flex; gap: 20px; padding: 0 20px 12px; border-bottom: 2px solid #e2e8f0; margin-bottom: 14px; font-size: 8pt; color: #475569; }
        .meta strong { color: #1e2d4d; }
        .account-block { margin: 0 20px 16px; page-break-inside: avoid; }
        .account-header { background: #f1f5f9; border-left: 4px solid #ef4444; padding: 7px 12px; font-size: 9pt; font-weight: bold; color: #1e293b; margin-bottom: 6px; }
        .account-header small { font-size: 7.5pt; color: #64748b; font-weight: normal; margin-left: 8px; }
        .post-row { border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px 12px; margin-bottom: 6px; page-break-inside: avoid; }
        .flag-reason { font-size: 7.5pt; color: #dc2626; margin-bottom: 4px; }
        .content { font-size: 8.5pt; color: #334155; line-height: 1.5; margin-bottom: 6px; }
        .stats { font-size: 7.5pt; color: #64748b; }
        .keywords { margin-top: 4px; }
        .kw-badge { display: inline-block; font-size: 7pt; padding: 1px 5px; border-radius: 3px; margin-right: 3px; }
        .footer { text-align: center; font-size: 7pt; color: #94a3b8; padding: 12px 20px; border-top: 1px solid #e2e8f0; margin-top: 8px; }
    </style>
</head>
<body>
<div class="header">
    <h1>Laporan Postingan Terpantau</h1>
    <p>SocioWatch Jateng — Monitoring Media Sosial 35 Kab/Kota Jawa Tengah</p>
</div>

<div class="meta">
    <span>Tanggal cetak: <strong>{{ now()->format('d F Y, H:i') }} WIB</strong></span>
    <span>Total postingan: <strong>{{ $posts->count() }}</strong></span>
    <span>Total akun: <strong>{{ $posts->pluck('social_account_id')->unique()->count() }}</strong></span>
</div>

@foreach($posts->groupBy('social_account_id') as $accountId => $accountPosts)
@php $account = $accountPosts->first()->socialAccount; @endphp
<div class="account-block">
    <div class="account-header">
        {{ $account->display_name }}
        <small>@{{ $account->username }} · {{ ucfirst($account->platform) }} · {{ $account->region->name ?? '-' }}</small>
    </div>

    @foreach($accountPosts as $post)
    <div class="post-row">
        @if($post->flag_reason)
        <div class="flag-reason">⚑ {{ $post->flag_reason }}</div>
        @endif
        <div class="content">{{ $post->content }}</div>
        <div class="stats">
            ♥ {{ number_format($post->likes_count) }} &nbsp;
            ✉ {{ number_format($post->comments_count) }} &nbsp;
            ↗ {{ number_format($post->shares_count) }} &nbsp;
            &bull; {{ $post->posted_at->format('d M Y, H:i') }}
        </div>
        @if($post->keywords->isNotEmpty())
        <div class="keywords">
            @foreach($post->keywords as $kw)
            <span class="kw-badge" style="background:{{ $kw->color }}22;color:{{ $kw->color }};border:1px solid {{ $kw->color }}44">
                {{ $kw->word }}
            </span>
            @endforeach
        </div>
        @endif
    </div>
    @endforeach
</div>
@endforeach

<div class="footer">
    Dicetak oleh: {{ auth()->user()->name }} ({{ auth()->user()->role }}) &mdash;
    SocioWatch Jateng &mdash; Dokumen ini bersifat internal
</div>
</body>
</html>
