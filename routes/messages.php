<?php

// Žinutės, atsiliepimai, skundai (Etapas 6)

use App\Http\Controllers\Messages\ConversationController;
use App\Http\Controllers\Messages\MessageController;
use Illuminate\Support\Facades\Route;

/*
| Teisės – Policies (ConversationPolicy, …) controller'iuose ir Form Request'uose.
| Rašymo veiksmams (POST) – „verified": nepatvirtinto el. pašto paskyros negali siųsti žinučių (šlamšto apsauga).
*/

Route::middleware('auth')->group(function () {
    // --- Pokalbiai ir žinutės ---
    Route::get('zinutes', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('zinutes/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');

    Route::middleware('verified')->group(function () {
        Route::post('zinutes/{conversation}', [MessageController::class, 'store'])->name('messages.store');
        Route::post('pasiulymai/{offer}/pokalbis', [ConversationController::class, 'store'])->name('conversations.store');
    });
});
