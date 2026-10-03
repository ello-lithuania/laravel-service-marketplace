<?php

use App\Http\Controllers\Account\ProviderCategoriesController;
use App\Http\Controllers\Account\ProviderDetailsController;
use App\Http\Controllers\Account\ProviderPricesController;
use App\Http\Controllers\Account\ProviderServiceAreasController;
use App\Http\Controllers\Account\ProviderWizardController;
use App\Http\Middleware\EnsureProviderProfileExists;
use Illuminate\Support\Facades\Route;

// Paskyra: avataras, teikėjo profilio vedlys, logotipas ir viršelis, atlikti darbai (Etapas 3).
// URL – lietuviški, be diakritikų; maršrutų vardai – angliški (CLAUDE.md 6 sk.).

Route::middleware(['auth', 'verified'])->prefix('paskyra')->group(function () {
    // Viskas žemiau – tik teikėjams. role middleware – grubus filtras, Policy – konkretaus įrašo teisės
    Route::middleware('role:provider')->group(function () {
        // Vedlio „įėjimas" – nukreipia į žingsnį, nuo kurio reikia tęsti
        Route::get('profilis', ProviderWizardController::class)->name('provider.wizard');

        // 1 žingsnis – čia profilis sukuriamas, todėl jo dar gali nebūti
        Route::get('profilis/duomenys', [ProviderDetailsController::class, 'edit'])->name('provider.details.edit');
        Route::put('profilis/duomenys', [ProviderDetailsController::class, 'update'])->name('provider.details.update');

        // Kiti žingsniai ir darbai saugomi prie profilio – be jo nukreipiam į 1 žingsnį
        Route::middleware(EnsureProviderProfileExists::class)->group(function () {
            Route::get('profilis/kategorijos', [ProviderCategoriesController::class, 'edit'])->name('provider.categories.edit');
            Route::put('profilis/kategorijos', [ProviderCategoriesController::class, 'update'])->name('provider.categories.update');

            Route::get('profilis/zonos', [ProviderServiceAreasController::class, 'edit'])->name('provider.areas.edit');
            Route::put('profilis/zonos', [ProviderServiceAreasController::class, 'update'])->name('provider.areas.update');

            Route::get('profilis/kainos', [ProviderPricesController::class, 'edit'])->name('provider.prices.edit');
            Route::put('profilis/kainos', [ProviderPricesController::class, 'update'])->name('provider.prices.update');
        });
    });
});
