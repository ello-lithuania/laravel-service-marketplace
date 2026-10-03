<?php

/*
|--------------------------------------------------------------------------
| Skundų tekstai – Etapas 6
|--------------------------------------------------------------------------
*/

return [
    'attributes' => [
        'type' => 'objekto tipas',
        'id' => 'objektas',
        'reason' => 'priežastis',
        'description' => 'aprašymas',
    ],

    'validation' => [
        'description_required' => 'Pasirinkus „Kita", trumpai aprašykite problemą.',
        'not_found' => 'Turinys, apie kurį norite pranešti, nebeegzistuoja.',
    ],

    'errors' => [
        'own_content' => 'Savo turinio skųsti negalima.',
        'banned' => 'Jūsų paskyra užblokuota.',
        'admin' => 'Administratorius turinį tvarko tiesiogiai, be skundų.',
        'duplicate' => 'Apie tai jau pranešėte – skundas nagrinėjamas.',
        'already_handled' => 'Šis skundas jau išnagrinėtas.',
    ],

    'flash' => [
        'created' => 'Ačiū! Pranešimą gavome – administratorius jį peržiūrės.',
    ],

    'notifications' => [
        'resolved' => [
            'message' => 'Jūsų pranešimas apie „:type" išnagrinėtas: :outcome',
            'subject' => 'Jūsų pranešimas išnagrinėtas',
            'intro' => 'Išnagrinėjome jūsų pranešimą apie „:type" (priežastis: :reason).',
            'outcome' => 'Rezultatas: :outcome.',
            'note' => 'Administratoriaus komentaras: :note',
            'outcomes' => [
                'resolved' => 'pažeidimas patvirtintas, imtasi veiksmų',
                'rejected' => 'pažeidimo nerasta',
            ],
        ],
    ],
];
