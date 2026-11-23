<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// SRS Bab 1.2 & Bab 9: pelepasan jadwal pre-order/booking terjadwal ke antrean operasional.
Schedule::command('ordering:release-scheduled')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// SRS Bab 1.2 & Bab 13: pembersihan berkas ekspor laporan yang kedaluwarsa (> 24 jam).
Schedule::command('reports:cleanup-expired')
    ->hourly()
    ->withoutOverlapping();
