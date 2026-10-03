<?php

use App\Http\Controllers\Settings\PrivacyController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Services\Privacy\DataExportStorage;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');
});

// --- Etapas 8: BDAR – duomenų archyvas (Nustatymai → Privatumas) ---
Route::middleware(['auth'])->group(function () {
    Route::get('settings/privacy', [PrivacyController::class, 'edit'])->name('privacy.edit');
    // throttle:3,60 – archyvo kūrimas „brangus" (visi duomenys + nuotraukos), todėl ne dažniau nei 3 kartus per valandą
    Route::post('settings/privacy/export', [PrivacyController::class, 'export'])
        ->middleware('throttle:3,60')
        ->name('privacy.export');
    Route::get('settings/privacy/export/{file}', [PrivacyController::class, 'download'])
        ->where('file', DataExportStorage::FILE_PATTERN)
        ->name('privacy.download');
});
