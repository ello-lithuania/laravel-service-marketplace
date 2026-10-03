<?php

// Žinutės, atsiliepimai, skundai (Etapas 6)

use App\Http\Controllers\Complaints\ComplaintController;
use App\Http\Controllers\Messages\ConversationController;
use App\Http\Controllers\Messages\MessageController;
use App\Http\Controllers\PrivateMediaController;
use App\Http\Controllers\Reviews\ProviderReviewController;
use App\Http\Controllers\Reviews\ReviewInvitationController;
use App\Http\Controllers\Reviews\ServiceRequestReviewController;
use App\Http\Middleware\EnsureProviderProfileExists;
use Illuminate\Support\Facades\Route;

/*
| Teisės – Policies (ConversationPolicy, ReviewPolicy, …) controller'iuose ir Form Request'uose.
| Rašymo veiksmams (POST) – „verified": nepatvirtinto el. pašto paskyros negali siųsti žinučių ar rašyti
| atsiliepimų (šlamšto ir netikrų paskyrų apsauga).
*/

Route::middleware('auth')->group(function () {
    // --- Pokalbiai ir žinutės ---
    Route::get('zinutes', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('zinutes/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');

    Route::middleware('verified')->group(function () {
        Route::post('zinutes/{conversation}', [MessageController::class, 'store'])->name('messages.store');
        Route::post('pasiulymai/{offer}/pokalbis', [ConversationController::class, 'store'])->name('conversations.store');
    });

    // --- Privatūs failai: žinučių priedai, užklausų nuotraukos (teisės – savininko Policy „view") ---
    Route::get('failai/{media}/{conversion?}', PrivateMediaController::class)
        ->whereIn('conversion', ['thumb', 'large'])
        ->name('media.show');

    // --- Atsiliepimai ---
    Route::middleware('verified')->group(function () {
        // Patvirtintas: klientas įvertina atliktą darbą (forma užklausos puslapyje)
        Route::post('uzklausos/{serviceRequest:slug}/atsiliepimas', [ServiceRequestReviewController::class, 'store'])
            ->name('reviews.store');

        // Pagal teikėjo pakvietimą: pasirašyta nuoroda (signed – parašas ir galiojimo laikas tikrinami prieš controller'į)
        Route::get('atsiliepimas/{providerProfile:slug}', [ReviewInvitationController::class, 'show'])
            ->middleware('signed')
            ->name('reviews.invitation.show');
        Route::post('atsiliepimas/{providerProfile:slug}', [ReviewInvitationController::class, 'store'])
            ->middleware('signed')
            ->name('reviews.invitation.store');
    });

    // --- Skundai: „Pranešti apie pažeidimą" (užklausa, pasiūlymas, atsiliepimas, žinutė, profilis) ---
    Route::post('skundai', [ComplaintController::class, 'store'])
        ->middleware('verified')
        ->name('complaints.store');

    // Teikėjo „Atsiliepimai": gauti atsiliepimai, atsakymai, pakvietimo nuoroda
    Route::middleware(['verified', 'role:provider', EnsureProviderProfileExists::class])->prefix('paskyra')->group(function () {
        Route::get('atsiliepimai', [ProviderReviewController::class, 'index'])->name('provider-reviews.index');
        Route::post('atsiliepimai/{review}/atsakymas', [ProviderReviewController::class, 'reply'])->name('provider-reviews.reply');
    });
});
