<?php

/*
|--------------------------------------------------------------------------
| Sąskaitos faktūros (Etapas 7)
|--------------------------------------------------------------------------
|
| Pardavėjo rekvizitai – .env faile (čia tik numatytosios reikšmės-pavyzdžiai). Pavadinimas, jei nenurodytas,
| imamas iš config('app.name'). Sąskaitos išrašymo metu rekvizitai įrašomi į payments.billing_details,
| todėl vėliau juos pakeitus jau išrašytos sąskaitos nesikeičia.
|
*/

return [

    // Numerio formatas: SF-2026-000123 (serija – metai – eilės numeris tų metų viduje)
    'prefix' => env('INVOICE_PREFIX', 'SF'),

    // --- Etapas 9b ---
    // Kreditinių sąskaitų (grąžinimų) serija su savo ištisine numeracija: KS-2026-000001
    'credit_note_prefix' => env('INVOICE_CREDIT_NOTE_PREFIX', 'KS'),

    // Sąskaitos data ir metai skaičiuojami Lietuvos laiku (DB laikas – UTC)
    'timezone' => 'Europe/Vilnius',

    'seller' => [
        'name' => env('INVOICE_SELLER_NAME'),
        'company_code' => env('INVOICE_SELLER_CODE', '000000000'),
        'vat_code' => env('INVOICE_SELLER_VAT_CODE'),
        'address' => env('INVOICE_SELLER_ADDRESS', 'Gatvė 1, LT-00000 Vilnius'),
        'email' => env('INVOICE_SELLER_EMAIL', env('MAIL_FROM_ADDRESS')),
        'bank_account' => env('INVOICE_SELLER_IBAN'),
    ],

    /*
    | PVM. Kainos DB (price_cents) – galutinės, kurias moka teikėjas.
    | vat_payer = false – pardavėjas ne PVM mokėtojas: „Sąskaita faktūra", PVM eilutės nėra.
    | vat_payer = true – „PVM sąskaita faktūra": kaina laikoma su PVM, iš jos išskiriama suma be PVM ir PVM.
    */
    'vat_payer' => (bool) env('INVOICE_VAT_PAYER', false),
    'vat_rate' => (int) env('INVOICE_VAT_RATE', 21),

];
