<?php

/*
|--------------------------------------------------------------------------
| Užklausų tekstai – Etapas 5
|--------------------------------------------------------------------------
|
| attributes – laukų pavadinimai validacijos klaidoms („Lauko pavadinimas reikšmė…").
|
*/

return [
    'attributes' => [
        'category_id' => 'paslauga',
        'city_id' => 'miestas / rajonas',
        'title' => 'pavadinimas',
        'description' => 'aprašymas',
        'address' => 'adresas',
        'budget_min' => 'biudžetas nuo',
        'budget_max' => 'biudžetas iki',
        'start_preference' => 'pradžia',
        'start_date' => 'pradžios data',
        'reason' => 'priežastis',
        'photos' => 'nuotraukos',
    ],

    'validation' => [
        'category_leaf' => 'Pasirinkite konkrečią paslaugą (trečio lygio kategoriją).',
        'budget_max_gte' => 'Biudžetas „iki" negali būti mažesnis už biudžetą „nuo".',
        'start_date_required' => 'Nurodykite datą, kada norite pradėti.',
        // Etapas 6
        'photos_max' => 'Užklausoje gali būti daugiausia :max nuotraukų.',
        'photos_required' => 'Pasirinkite bent vieną nuotrauką.',
    ],

    'flash' => [
        'published' => 'Užklausa paskelbta! Tinkami teikėjai jau gavo pranešimą.',
        'pending' => 'Užklausa gauta. Ją peržiūrės administratorius ir paskelbs per kelias valandas.',
        'cancelled' => 'Užklausa atšaukta.',
        'completed' => 'Puiku! Užklausa pažymėta kaip atlikta.',
        // Etapas 6
        'photos_added' => 'Nuotraukos pridėtos.',
        'photo_deleted' => 'Nuotrauka pašalinta.',
    ],

    'cancel' => [
        'reason_required' => 'Vykdomą užklausą galima atšaukti tik nurodžius priežastį.',
    ],

    'refund' => [
        'cancelled' => 'Grąžinimas: užklausa „:title" atšaukta',
        'expired' => 'Grąžinimas: užklausa „:title" pasibaigė, pasiūlymas neatidarytas',
    ],
];
