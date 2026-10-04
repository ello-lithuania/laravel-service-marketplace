<?php

// Kainos, kreditų paketai, prenumeratos, mokėjimai (Etapas 7)

use App\Http\Controllers\Billing\CreditNoteController;
use App\Http\Controllers\Billing\CreditsController;
use App\Http\Controllers\Billing\FakeCheckoutController;
use App\Http\Controllers\Billing\InvoiceController;
use App\Http\Controllers\Billing\PaymentCallbackController;
use App\Http\Controllers\Billing\PaymentController;
use App\Http\Controllers\Billing\PricingController;
use App\Http\Controllers\Billing\PurchaseController;
use App\Http\Controllers\Billing\SubscriptionController;
use App\Http\Middleware\EnsureProviderProfileExists;
use Illuminate\Support\Facades\Route;

// Viešas kainų puslapis
Route::get('kainos', PricingController::class)->name('pricing');

/*
| Mokėjimų tiekėjo callback'as (webhook'as): jį kviečia Paysera SERVERIS, ne vartotojo naršyklė.
| Todėl – be prisijungimo ir be CSRF (išimtis bootstrap/app.php); apsauga – tiekėjo parašas.
| GET ir POST: Paysera gali siųsti abiem būdais.
*/
Route::match(['get', 'post'], 'mokejimai/callback/{gateway}', PaymentCallbackController::class)
    ->whereIn('gateway', ['paysera', 'fake'])
    ->name('payments.callback');

// Teikėjo kreditai, pirkimai, prenumerata, mokėjimų istorija
Route::middleware(['auth', 'verified', 'role:provider', EnsureProviderProfileExists::class])->group(function () {
    Route::get('teikejas/kreditai', CreditsController::class)->name('credits.index');
    Route::post('teikejas/kreditai/{creditPackage}/pirkti', [PurchaseController::class, 'package'])->name('credits.purchase');
    Route::post('teikejas/prenumerata/{subscriptionPlan:slug}/pirkti', [PurchaseController::class, 'plan'])->name('subscriptions.purchase');
    Route::post('teikejas/prenumerata/{subscription}/atsaukti', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');
    Route::get('teikejas/mokejimai', [PaymentController::class, 'index'])->name('payments.index');
});

/*
| Konkretaus mokėjimo puslapiai: {payment} – uuid (Payment::getRouteKeyName), ne id, kad svetimų mokėjimų
| numerių nebūtų galima atspėti. Kas ką gali – PaymentPolicy (savininkas; sąskaitą – ir administratorius).
*/
Route::middleware('auth')->whereUuid('payment')->group(function () {
    // Paysera accepturl ir priminimų nuoroda – mokėjimo būsena ir „Apmokėti"
    Route::get('mokejimai/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    Route::post('mokejimai/{payment}/apmoketi', [PaymentController::class, 'pay'])->name('payments.pay');
    // Paysera cancelurl – pirkėjas atšaukė mokėjimą Paysera puslapyje
    Route::get('mokejimai/{payment}/atsaukti', [PaymentController::class, 'cancel'])->name('payments.cancel');
    Route::get('mokejimai/{payment}/saskaita', InvoiceController::class)->name('payments.invoice');
    // --- Etapas 9b: grąžinto mokėjimo kreditinė sąskaita faktūra ---
    Route::get('mokejimai/{payment}/kreditine-saskaita', CreditNoteController::class)->name('payments.credit-note');

    // Netikras mokėjimų tiekėjas (tik ne produkcijoje): „Paysera" puslapio imitacija
    Route::get('mokejimai/{payment}/testinis', [FakeCheckoutController::class, 'show'])->name('payments.fake.show');
    Route::post('mokejimai/{payment}/testinis', [FakeCheckoutController::class, 'complete'])->name('payments.fake.complete');
});
