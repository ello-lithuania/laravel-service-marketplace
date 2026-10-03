<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'public/Home')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

// Maršrutai suskirstyti pagal sritis – kiekviena sritis savo faile (ROADMAP etapai)
require __DIR__.'/settings.php';
require __DIR__.'/account.php';
require __DIR__.'/catalog.php';
require __DIR__.'/requests.php';
require __DIR__.'/messages.php';
require __DIR__.'/billing.php';
