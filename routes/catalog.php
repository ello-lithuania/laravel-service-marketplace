<?php

// Vieša dalis: katalogas, kategorijos, teikėjų sąrašas ir profiliai, paieška (Etapas 4)

use App\Http\Controllers\Catalog\CategoryController;
use App\Http\Controllers\Catalog\HomeController;
use App\Http\Controllers\Catalog\ProviderController;
use App\Http\Controllers\Catalog\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('paslaugos', [CategoryController::class, 'index'])->name('categories.index');
Route::get('paslaugos/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');
// withoutScopedBindings: kitaip Laravel ieškotų miesto per $category->cities() ryšį (kurio nėra),
// nes du {modelis:slug} parametrai viename URL pagal nutylėjimą laikomi tėvu ir vaiku
Route::get('paslaugos/{category:slug}/{city:slug}', [CategoryController::class, 'city'])
    ->withoutScopedBindings()
    ->name('categories.city');

Route::get('meistrai', [ProviderController::class, 'index'])->name('providers.index');
Route::get('meistrai/{providerProfile:slug}', [ProviderController::class, 'show'])->name('providers.show');

Route::get('paieska', SearchController::class)->name('search');
