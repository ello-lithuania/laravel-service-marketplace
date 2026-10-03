<?php

/*
|--------------------------------------------------------------------------
| Pranešimų (Notifications) tekstai – Etapas 5
|--------------------------------------------------------------------------
|
| „message" – trumpas tekstas varpeliui (notifications.data.message), tokio pat formato kaip seed'uose.
| Kiti raktai – el. laiškui.
|
*/

return [
    'greeting' => 'Sveiki!',
    'settings_saved' => 'Pranešimų nustatymai išsaugoti.',
    'settings_hint' => 'Kuriuos pranešimus gauti el. paštu, galite pasirinkti paskyros nustatymuose („Pranešimai").',

    'new_matching_request' => [
        'message' => 'Nauja užklausa jūsų srityje: „:title"',
        'subject' => 'Nauja užklausa: :title',
        'intro' => 'Jūsų srityje paskelbta nauja užklausa „:title".',
        'details' => 'Paslauga: :category · Vieta: :city · Biudžetas: :budget',
        'action' => 'Peržiūrėti užklausą',
        'outro' => 'Pasiūlymą galite išsiųsti užklausos puslapyje. Pasiūlymas kainuoja :credits kred.',
    ],

    'new_offer' => [
        'message' => ':provider atsiuntė pasiūlymą užklausai „:title"',
        'subject' => 'Naujas pasiūlymas: :title',
        'intro' => ':provider atsiuntė pasiūlymą jūsų užklausai „:title".',
        'price' => 'Kaina: :price',
        'action' => 'Peržiūrėti pasiūlymą',
    ],

    'offer_accepted' => [
        'message' => 'Jūsų pasiūlymas užklausai „:title" priimtas!',
        'subject' => 'Jūsų pasiūlymas priimtas: :title',
        'intro' => 'Klientas priėmė jūsų pasiūlymą užklausai „:title".',
        'contacts' => 'Kliento kontaktai ir darbų adresas matomi užklausos puslapyje.',
        'action' => 'Atidaryti užklausą',
    ],

    'offer_declined' => [
        'client_declined' => 'Klientas atmetė jūsų pasiūlymą užklausai „:title"',
        'other_accepted' => 'Klientas pasirinko kitą pasiūlymą užklausai „:title"',
        'request_cancelled' => 'Užklausa „:title" atšaukta',
        'request_expired' => 'Užklausa „:title" pasibaigė – klientas pasiūlymo nepasirinko',
        'refunded' => 'Grąžinta kreditų: :credits.',
        'subject' => 'Pasiūlymo būsena: :title',
        'action' => 'Peržiūrėti užklausą',
    ],

    'service_request_cancelled' => [
        'message' => 'Klientas atšaukė vykdomą užklausą „:title"',
        'subject' => 'Užklausa atšaukta: :title',
        'intro' => 'Klientas atšaukė užklausą „:title", kurią vykdėte.',
        'reason' => 'Priežastis: :reason',
        'action' => 'Peržiūrėti užklausą',
    ],

    'service_request_published' => [
        'message' => 'Jūsų užklausa „:title" paskelbta',
        'subject' => 'Užklausa paskelbta: :title',
        'intro' => 'Administratorius patvirtino jūsų užklausą „:title". Tinkami teikėjai jau gavo pranešimą.',
        'action' => 'Peržiūrėti užklausą',
    ],

    'service_request_rejected' => [
        'message' => 'Jūsų užklausa „:title" atmesta: :reason',
        'subject' => 'Užklausa atmesta: :title',
        'intro' => 'Administratorius atmetė arba atšaukė jūsų užklausą „:title".',
        'reason' => 'Priežastis: :reason',
        'outro' => 'Galite sukurti naują užklausą, pataisę nurodytus dalykus.',
        'action' => 'Sukurti naują užklausą',
    ],
];
