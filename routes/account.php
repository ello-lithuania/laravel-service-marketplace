<?php

use App\Http\Controllers\Account\AvatarController;
use App\Http\Controllers\Account\PortfolioImageController;
use App\Http\Controllers\Account\PortfolioItemController;
use App\Http\Controllers\Account\PortfolioOrderController;
use App\Http\Controllers\Account\ProviderCategoriesController;
use App\Http\Controllers\Account\ProviderDetailsController;
use App\Http\Controllers\Account\ProviderImagesController;
use App\Http\Controllers\Account\ProviderPricesController;
use App\Http\Controllers\Account\ProviderServiceAreasController;
use App\Http\Controllers\Account\ProviderWizardController;
use App\Http\Middleware\EnsureProviderProfileExists;
use App\Models\PortfolioItem;
use Illuminate\Support\Facades\Route;

// Paskyra: avataras, teikėjo profilio vedlys, logotipas ir viršelis, atlikti darbai (Etapas 3).
// URL – lietuviški, be diakritikų; maršrutų vardai – angliški (CLAUDE.md 6 sk.).

Route::middleware(['auth', 'verified'])->prefix('paskyra')->group(function () {
    // Avataras – visoms rolėms
    Route::post('avataras', [AvatarController::class, 'update'])->name('avatar.update');
    Route::delete('avataras', [AvatarController::class, 'destroy'])->name('avatar.destroy');

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

            // Logotipas ir viršelis: /paskyra/profilis/nuotraukos/logotipas, …/virselis
            Route::get('profilis/nuotraukos', [ProviderImagesController::class, 'edit'])->name('provider.images.edit');
            Route::post('profilis/nuotraukos/{collection}', [ProviderImagesController::class, 'update'])
                ->whereIn('collection', array_keys(ProviderImagesController::COLLECTIONS))
                ->name('provider.images.update');
            Route::delete('profilis/nuotraukos/{collection}', [ProviderImagesController::class, 'destroy'])
                ->whereIn('collection', array_keys(ProviderImagesController::COLLECTIONS))
                ->name('provider.images.destroy');

            // Atlikti darbai (portfolio). ->can() – Policy patikra dar prieš controller'į
            Route::get('darbai', [PortfolioItemController::class, 'index'])
                ->can('viewAny', PortfolioItem::class)->name('portfolio.index');
            Route::get('darbai/naujas', [PortfolioItemController::class, 'create'])
                ->can('create', PortfolioItem::class)->name('portfolio.create');
            Route::post('darbai', [PortfolioItemController::class, 'store'])
                ->can('create', PortfolioItem::class)->name('portfolio.store');
            Route::put('darbai/tvarka', [PortfolioOrderController::class, 'update'])
                ->can('viewAny', PortfolioItem::class)->name('portfolio.reorder');
            Route::get('darbai/{portfolioItem}/redaguoti', [PortfolioItemController::class, 'edit'])
                ->can('update', 'portfolioItem')->name('portfolio.edit');
            Route::put('darbai/{portfolioItem}', [PortfolioItemController::class, 'update'])
                ->can('update', 'portfolioItem')->name('portfolio.update');
            Route::delete('darbai/{portfolioItem}', [PortfolioItemController::class, 'destroy'])
                ->can('delete', 'portfolioItem')->name('portfolio.destroy');
            // scopeBindings(): {media} ieškomas tik tarp šio darbo nuotraukų
            Route::delete('darbai/{portfolioItem}/nuotraukos/{media}', [PortfolioImageController::class, 'destroy'])
                ->can('update', 'portfolioItem')->scopeBindings()->name('portfolio.images.destroy');
        });
    });
});
