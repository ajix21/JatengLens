<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-fetch YouTube/Instagram 2x sehari: pukul 08:00 dan 20:00 WIB (sebelum laporan 10:00 WIB)
Schedule::command('social:fetch')->dailyAt('08:00')->timezone('Asia/Jakarta')->withoutOverlapping(60);
Schedule::command('social:fetch')->dailyAt('20:00')->timezone('Asia/Jakarta')->withoutOverlapping(60);

// Snapshot otomatis 15 menit setelah fetch & ekstensi mulai: pukul 08:15 dan 20:15 WIB
Schedule::command('monitor:snapshot')->dailyAt('08:15')->timezone('Asia/Jakarta');
Schedule::command('monitor:snapshot')->dailyAt('20:15')->timezone('Asia/Jakarta');

// Ringkasan eksekutif AI & notifikasi Telegram 5 menit setelah snapshot: pukul 08:20 dan 20:20 WIB
Schedule::command('intel:briefing')->dailyAt('08:20')->timezone('Asia/Jakarta')->withoutOverlapping(30);
Schedule::command('intel:briefing')->dailyAt('20:20')->timezone('Asia/Jakarta')->withoutOverlapping(30);
