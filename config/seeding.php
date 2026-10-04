<?php

/**
 * Testinių duomenų (seed'ų) nustatymai – docs/SEEDING.md 9 sk.
 * Kode skaitom config('seeding.scale'), o ne env() – po config:cache env() grąžina null.
 */

return [
    // Kiekių daugiklis: 1 – pilnas (≈1,8 mln. eilučių), 0.05 – greitas dev'ui
    'scale' => (float) env('SEED_SCALE', 1),

    // Kiek eilučių įterpiama vienu INSERT
    'chunk' => (int) env('SEED_CHUNK', 1000),

    // Atsitiktinumo „sėkla": ta pati reikšmė – tie patys duomenys
    'faker_seed' => (int) env('SEED_FAKER_SEED', 2026),

    // Ar kurti didelius testinius duomenis (false – tik žinyniniai duomenys)
    'demo' => (bool) env('SEED_DEMO', true),

    // Ar prisegti demo paveikslėlius (logotipai, viršeliai, portfolio). Išjungta: lėčiau ir užima vietos diske
    'media' => (bool) env('SEED_MEDIA', false),

    // --- Etapas 10a ---
    // Ar naudoti atsisiųstas nuotraukas (storage/app/stock-photos, php artisan photos:download): kategorijų ir
    // svetainės nuotraukos prisegamos visada, demo portfolio ir viršeliai – kai SEED_MEDIA=true. Testuose – false
    'stock_photos' => (bool) env('SEED_STOCK_PHOTOS', true),

    // Kokia dalis atliktų užklausų gauna patvirtintą atsiliepimą
    'verified_review_ratio' => (float) env('SEED_VERIFIED_REVIEW_RATIO', 0.9),

    // Tiksliniai kiekiai, kai scale = 1
    'counts' => [
        'clients' => 60_000,
        'providers' => 20_000,
        'service_requests' => 100_000,
        'offers' => 300_000,
        'reviews' => 100_000,
        'messages' => 200_000,
    ],
];
