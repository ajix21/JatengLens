<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-fetch YouTube/Instagram 4x sehari (2 Batch bergantian tiap 4 jam agar kuota Meta 200/jam selalu aman)
// Pagi (08:00): Batch 1 | Siang (12:00): Batch 2 | Sore (16:00): Batch 1 | Malam (20:00): Batch 2
Schedule::command('social:fetch --batch=1 --batches=2')->dailyAt('08:00')->timezone('Asia/Jakarta')->withoutOverlapping(60);
Schedule::command('social:fetch --batch=2 --batches=2')->dailyAt('12:00')->timezone('Asia/Jakarta')->withoutOverlapping(60);
Schedule::command('social:fetch --batch=1 --batches=2')->dailyAt('16:00')->timezone('Asia/Jakarta')->withoutOverlapping(60);
Schedule::command('social:fetch --batch=2 --batches=2')->dailyAt('20:00')->timezone('Asia/Jakarta')->withoutOverlapping(60);

// Arsip lokal thumbnail postingan baru agar tidak expired (15 menit setelah setiap batch fetch)
Schedule::command('media:cache-thumbnails --limit=200')->dailyAt('08:15')->timezone('Asia/Jakarta')->withoutOverlapping(20);
Schedule::command('media:cache-thumbnails --limit=200')->dailyAt('12:15')->timezone('Asia/Jakarta')->withoutOverlapping(20);
Schedule::command('media:cache-thumbnails --limit=200')->dailyAt('16:15')->timezone('Asia/Jakarta')->withoutOverlapping(20);
Schedule::command('media:cache-thumbnails --limit=200')->dailyAt('20:15')->timezone('Asia/Jakarta')->withoutOverlapping(20);

// Snapshot otomatis pagi & malam: pukul 08:20 dan 20:20 WIB
Schedule::command('monitor:snapshot')->dailyAt('08:20')->timezone('Asia/Jakarta');
Schedule::command('monitor:snapshot')->dailyAt('20:20')->timezone('Asia/Jakarta');

// Ringkasan eksekutif AI & notifikasi Telegram: pukul 08:25 (siap sebelum laporan 10:00) dan 20:25 WIB
Schedule::command('intel:briefing')->dailyAt('08:25')->timezone('Asia/Jakarta')->withoutOverlapping(30);
Schedule::command('intel:briefing')->dailyAt('20:25')->timezone('Asia/Jakarta')->withoutOverlapping(30);
