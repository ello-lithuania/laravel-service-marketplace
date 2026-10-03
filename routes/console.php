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

// --- Etapas 7: prenumeratos ---
// Kasdien ryte (Lietuvos laiku): pasibaigusios → past_due / expired, artėjančioms – pratęsimo mokėjimas ir priminimas
Schedule::command('subscriptions:renew')
    ->dailyAt('08:00')
    ->timezone('Europe/Vilnius')
    ->withoutOverlapping()
    ->onOneServer();

// Kas valandą: kreditai už prasidėjusius apmokėtus laikotarpius (idempotentiška – dvigubai nesuteiks)
Schedule::command('subscriptions:grant-credits')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

// --- Etapas 6: priminimas klientui, kai užklausa vykdoma 60+ d. (docs/STATES.md 1 sk., automatiškai neužbaigiam) ---
Schedule::command('service-requests:remind-completion')
    // Ne naktį: laiškas ateina darbo dienos pradžioje Lietuvos laiku
    ->dailyAt('09:00')
    ->timezone('Europe/Vilnius')
    ->withoutOverlapping()
    ->onOneServer();
