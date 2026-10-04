<?php

// Svetainės informaciniai puslapiai (Etapas 10)

use App\Http\Controllers\Site\PhotoCreditsController;
use Illuminate\Support\Facades\Route;

// Nuotraukų autoriai ir licencijos (CC BY reikalauja nurodyti autorių). Nuoroda – svetainės poraštėje
Route::get('nuotrauku-autoriai', PhotoCreditsController::class)->name('photo-credits');
