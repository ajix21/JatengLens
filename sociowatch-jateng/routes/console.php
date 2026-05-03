<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Snapshot otomatis 2x sehari: pukul 07:00 dan 19:00 WIB
Schedule::command('monitor:snapshot')->dailyAt('07:00');
Schedule::command('monitor:snapshot')->dailyAt('19:00');
