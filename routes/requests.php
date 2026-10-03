<?php

// Užklausos, pasiūlymai, teikėjo užklausų srautas, pranešimai (Etapas 5)

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Offers\OfferController;
use App\Http\Controllers\Offers\OfferTransitionController;
use App\Http\Controllers\ServiceRequests\ProviderFeedController;
use App\Http\Controllers\ServiceRequests\ServiceRequestController;
use App\Http\Controllers\ServiceRequests\ServiceRequestPhotoController;
use App\Http\Controllers\ServiceRequests\ServiceRequestTransitionController;
use App\Http\Controllers\Settings\NotificationSettingsController;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Support\Facades\Route;

/*
| role:… – grubus filtras pagal rolę (EnsureUserHasRole). Ar vartotojas gali veikti su KONKREČIU įrašu
| (savo užklausa, tinkama užklausa teikėjui), sprendžia Policy controller'yje arba Form Request'e.
| {serviceRequest:slug} – route model binding pagal slug: Laravel pats suranda užklausą arba grąžina 404.
| https://laravel.com/docs/13.x/routing#customizing-the-key
*/

Route::middleware('auth')->group(function () {
    // Klientas
    Route::middleware('role:client')->group(function () {
        // Svečias nukreipiamas prisijungti, o po prisijungimo grąžinamas čia (auth middleware įsimena „intended" URL)
        Route::get('uzklausos/nauja', [ServiceRequestController::class, 'create'])->name('service-requests.create');
        // Precognition: formos žingsniai validuojami serverio taisyklėmis neišsaugant (Precognition antraštė)
        Route::post('uzklausos', [ServiceRequestController::class, 'store'])
            // Etapas 6: throttle – tik tikram išsaugojimui (Precognition užklausų limiter'is neriboja)
            ->middleware([HandlePrecognitiveRequests::class, 'throttle:service-requests'])
            ->name('service-requests.store');
        Route::get('mano-uzklausos', [ServiceRequestController::class, 'index'])->name('service-requests.index');
        Route::post('uzklausos/{serviceRequest:slug}/uzbaigti', [ServiceRequestTransitionController::class, 'complete'])
            ->name('service-requests.complete');
        Route::post('pasiulymai/{offer}/priimti', [OfferTransitionController::class, 'accept'])->name('offers.accept');
        Route::post('pasiulymai/{offer}/atmesti', [OfferTransitionController::class, 'decline'])->name('offers.decline');

        // --- Etapas 6: užklausos nuotraukos (kol pending / open – ServiceRequestPolicy::updatePhotos) ---
        Route::post('uzklausos/{serviceRequest:slug}/nuotraukos', [ServiceRequestPhotoController::class, 'store'])
            ->name('service-requests.photos.store');
        Route::delete('uzklausos/{serviceRequest:slug}/nuotraukos/{media}', [ServiceRequestPhotoController::class, 'destroy'])
            ->scopeBindings()
            ->name('service-requests.photos.destroy');
    });

    // Atšaukti gali klientas arba administratorius – sprendžia ServiceRequestPolicy::cancel
    Route::post('uzklausos/{serviceRequest:slug}/atsaukti', [ServiceRequestTransitionController::class, 'cancel'])
        ->name('service-requests.cancel');

    // Teikėjas
    Route::middleware('role:provider')->group(function () {
        Route::get('teikejas/uzklausos', ProviderFeedController::class)->name('provider-feed.index');
        Route::get('mano-pasiulymai', [OfferController::class, 'index'])->name('offers.index');
        Route::post('uzklausos/{serviceRequest:slug}/pasiulymai', [OfferController::class, 'store'])->name('offers.store');
        Route::post('pasiulymai/{offer}/atsaukti', [OfferTransitionController::class, 'withdraw'])->name('offers.withdraw');
    });

    // Užklausos ir pasiūlymo puslapiai – klientui, tinkamam teikėjui ir administratoriui (Policy „view")
    Route::get('uzklausos/{serviceRequest:slug}', [ServiceRequestController::class, 'show'])->name('service-requests.show');
    Route::get('uzklausos/{serviceRequest:slug}/pasiulymai/{offer}', [OfferController::class, 'show'])
        ->scopeBindings()
        ->name('offers.show');

    // Pranešimai (varpelis)
    Route::get('pranesimai', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('pranesimai/naujausi', [NotificationController::class, 'latest'])->name('notifications.latest');
    Route::post('pranesimai/perskaityti-visi', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('pranesimai/{notification}/atidaryti', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('pranesimai/{notification}/perskaityta', [NotificationController::class, 'markRead'])->name('notifications.read');

    // Nustatymai → Pranešimai (URL kaip kiti nustatymų puslapiai: /settings/…)
    Route::get('settings/notifications', [NotificationSettingsController::class, 'edit'])->name('notification-settings.edit');
    Route::patch('settings/notifications', [NotificationSettingsController::class, 'update'])->name('notification-settings.update');
});
