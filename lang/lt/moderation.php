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

    // --- Etapas 9c: pranešimai teikėjui apie administratoriaus veiksmus (ProviderStatusChanged, ProviderVerified) ---
    'provider_notifications' => [
        'status' => [
            'hidden' => [
                'subject' => 'Jūsų profilis paslėptas',
                'intro' => 'Administratorius paslėpė jūsų profilį „:name". Jis nerodomas kataloge ir paieškoje, naujų užklausų negausite.',
                'message' => 'Jūsų profilis paslėptas – jis nerodomas kataloge ir paieškoje.',
            ],
            'suspended' => [
                'subject' => 'Jūsų profilis užblokuotas',
                'intro' => 'Administratorius užblokavo jūsų profilį „:name". Jis nerodomas kataloge, o siųsti pasiūlymų negalite.',
                'message' => 'Jūsų profilis užblokuotas – jis nerodomas kataloge, pasiūlymų siųsti negalite.',
            ],
            'active' => [
                'subject' => 'Jūsų profilis vėl aktyvus',
                'intro' => 'Jūsų profilis „:name" vėl rodomas kataloge: galite gauti užklausas ir siųsti pasiūlymus.',
                'message' => 'Jūsų profilis vėl aktyvus ir rodomas kataloge.',
            ],
            'pending' => [
                'subject' => 'Jūsų profilis atkurtas',
                'intro' => 'Jūsų profilis „:name" atkurtas, bet jame trūksta privalomų duomenų. Užbaikite profilio pildymą – tada jis bus rodomas kataloge.',
                'message' => 'Jūsų profilis atkurtas – užbaikite jo pildymą, kad būtų rodomas kataloge.',
            ],
            'reason' => 'Priežastis: :reason',
            'outro' => 'Jei manote, kad tai klaida, parašykite mums.',
            'actions' => [
                'profile' => 'Peržiūrėti profilį',
                'dashboard' => 'Atidaryti paskyrą',
                'wizard' => 'Užbaigti profilį',
            ],
        ],
        'verified' => [
            'subject' => 'Jūsų profilis patikrintas',
            'intro' => 'Administratorius patikrino jūsų duomenis. Prie profilio „:name" kataloge ir viešame puslapyje dabar rodomas ženklelis „Patikrintas" – klientams tai svarbus pasitikėjimo ženklas.',
            'message' => 'Jūsų profilis patikrintas – rodomas ženklelis „Patikrintas".',
        ],
    ],
];
