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

    // --- Etapas 6: prašymas pažymėti darbą atliktu ir 60 d. priminimas (docs/STATES.md 1 sk.) ---
    'completion' => [
        'not_chosen' => 'Paprašyti pažymėti darbą atliktu gali tik išrinktas teikėjas.',
        'not_in_progress' => 'Užklausa nebevykdoma.',
        'too_soon' => 'Priminimą jau siuntėte. Kitą galėsite išsiųsti po :days d.',
        'flash' => 'Priminimas išsiųstas klientui.',
        'requested' => [
            'message' => ':provider prašo pažymėti, kad darbas „:title" atliktas',
            'subject' => 'Ar darbas „:title" atliktas?',
            'intro' => ':provider pažymėjo, kad darbas „:title" atliktas.',
            'outro' => 'Jei viskas gerai – užklausos puslapyje paspauskite „Darbas atliktas" ir įvertinkite teikėją. Jei dar ne – parašykite teikėjui žinutę.',
            'action' => 'Atidaryti užklausą',
        ],
        'reminder' => [
            'message' => 'Užklausa „:title" vykdoma jau :days d. Ar darbas atliktas?',
            'subject' => 'Ar darbas „:title" jau atliktas?',
            'intro' => 'Jūsų užklausa „:title" vykdoma jau :days dienų.',
            'outro' => 'Jei darbas atliktas – pažymėkite tai ir įvertinkite teikėją. Jei darbas neįvyko, užklausą galite atšaukti.',
            'action' => 'Atidaryti užklausą',
        ],
    ],
];
