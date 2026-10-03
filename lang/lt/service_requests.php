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
    ],

    'validation' => [
        'category_leaf' => 'Pasirinkite konkrečią paslaugą (trečio lygio kategoriją).',
        'budget_max_gte' => 'Biudžetas „iki" negali būti mažesnis už biudžetą „nuo".',
        'start_date_required' => 'Nurodykite datą, kada norite pradėti.',
    ],

    'flash' => [
        'published' => 'Užklausa paskelbta! Tinkami teikėjai jau gavo pranešimą.',
        'pending' => 'Užklausa gauta. Ją peržiūrės administratorius ir paskelbs per kelias valandas.',
        'cancelled' => 'Užklausa atšaukta.',
        'completed' => 'Puiku! Užklausa pažymėta kaip atlikta.',
    ],

    'cancel' => [
        'reason_required' => 'Vykdomą užklausą galima atšaukti tik nurodžius priežastį.',
    ],

    'refund' => [
        'cancelled' => 'Grąžinimas: užklausa „:title" atšaukta',
        'expired' => 'Grąžinimas: užklausa „:title" pasibaigė, pasiūlymas neatidarytas',
    ],
];
