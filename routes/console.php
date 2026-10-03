<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduler (≈ WordPress wp_cron, tik patikimesnis)
|--------------------------------------------------------------------------
|
| Serverio cron kas minutę paleidžia „php artisan schedule:run", o Laravel pats sprendžia, kurias užduotis
| vykdyti dabar. Dev'e: „php artisan schedule:work". Sąrašas: „php artisan schedule:list".
| https://laravel.com/docs/13.x/scheduling
|
*/

// Etapas 5: pasibaigusios užklausos (docs/STATES.md 1 sk. open → expired)
Schedule::command('service-requests:expire')
    ->hourly()
    // Jei ankstesnis paleidimas dar nebaigė – naujo nepradedam (kitaip abu imtų tas pačias užklausas)
    ->withoutOverlapping()
    ->onOneServer();

// --- Etapas 8 ---
// BDAR archyvai saugomi 7 d. (DataExportStorage::RETENTION_DAYS), paskui ištrinami
Schedule::command('privacy:prune-exports')
    ->dailyAt('03:15')
    ->onOneServer();
