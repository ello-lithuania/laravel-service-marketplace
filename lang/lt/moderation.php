<?php

// Etapas 8: vartotojų blokavimas ir teikėjų profilių moderavimas (Filament)

return [
    'banned_login' => 'Jūsų paskyra užblokuota. Jei manote, kad tai klaida, parašykite mums.',

    'ban' => [
        'request_cancel_reason' => 'Užklausa atšaukta, nes paskyra užblokuota dėl taisyklių pažeidimo.',
        'cannot_ban_admin' => 'Administratoriaus paskyros užblokuoti negalima.',
        'already_banned' => 'Vartotojas jau užblokuotas.',
    ],

    'provider_status' => [
        'not_allowed' => 'Teikėjo būsenos „:from" negalima pakeisti į „:to".',
        'incomplete' => 'Profilio aktyvuoti negalima: neužpildytos privalomos dalys (kategorijos ir aptarnavimo zonos).',
        'user_banned' => 'Profilio aktyvuoti negalima: teikėjo paskyra užblokuota.',
    ],
];
