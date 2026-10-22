<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-fetch YouTube/Instagram 10 menit sebelum snapshot, supaya snapshot memakai data terbaru (WIB)
Schedule::command('social:fetch')->dailyAt('06:50')->timezone('Asia/Jakarta')->withoutOverlapping();
Schedule::command('social:fetch')->dailyAt('18:50')->timezone('Asia/Jakarta')->withoutOverlapping();

// Snapshot otomatis 2x sehari: pukul 07:00 dan 19:00 WIB
Schedule::command('monitor:snapshot')->dailyAt('07:00')->timezone('Asia/Jakarta');
Schedule::command('monitor:snapshot')->dailyAt('19:00')->timezone('Asia/Jakarta');
