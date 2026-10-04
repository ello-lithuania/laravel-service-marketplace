<?php

/**
 * Etapas 10: nuotraukų atsisiuntimas iš nemokamų nuotraukų bankų (php artisan photos:download).
 * Pexels raktas – config/services.php (pexels.key), paieškos frazės – database/data/photo_queries.php.
 */

return [
    // Vietinė nuotraukų biblioteka (storage/app/stock-photos, Git'e ignoruojama): atsisiųsti originalai ir credits.json.
    // Iš jos nuotraukos vėl prisegamos po migrate:fresh --seed – be interneto ir be API limitų.
    'disk' => 'stock-photos',

    // Didžiausias atsisiunčiamo failo dydis (baitais): didesni failai atmetami dar neatsisiuntus iki galo
    'max_bytes' => 10 * 1024 * 1024,

    // Mažiausi matmenys (px): kategorijų juosta 1600×700, svetainės nuotraukos iki 1920, portfolio – 960×720
    'min_size' => [
        'categories' => [1200, 700],
        'site' => [1200, 700],
        'portfolio' => [800, 600],
    ],

    // Didesni originalai bibliotekoje sumažinami: greitesnės miniatiūros (ir seed'as), mažiau vietos diske.
    // Kategorijoms užtenka 1600 – tokio pločio plačioji juosta (wide 1600×700)
    'max_dimension' => [
        'categories' => 1600,
        'site' => 2000,
        'portfolio' => 1600,
    ],

    // Pauzė tarp paieškos užklausų (ms). Openverse be rakto leidžia ~20 užklausų per minutę ir ~200 per parą,
    // Pexels – 200 per valandą ir 20 000 per mėnesį
    'pause_ms' => [
        'pexels' => (int) env('PHOTOS_PEXELS_PAUSE_MS', 300),
        'openverse' => (int) env('PHOTOS_OPENVERSE_PAUSE_MS', 3100),
    ],

    // Užklausos laiko limitas (s)
    'timeout' => 30,

    // Openverse prašo aiškaus User-Agent. Tuščia – „{APP_NAME be diakritikų} photo-downloader/1.0 (+APP_URL)"
    'user_agent' => env('PHOTOS_USER_AGENT'),
];
