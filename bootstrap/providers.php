<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\PaymentServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    FortifyServiceProvider::class,
    // --- Etapas 7: mokėjimų tiekėjai (Paysera, testinis) ---
    PaymentServiceProvider::class,
];
