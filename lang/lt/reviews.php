<?php

/*
|--------------------------------------------------------------------------
| Atsiliepimų tekstai – Etapas 6
|--------------------------------------------------------------------------
*/

return [
    'attributes' => [
        'rating' => 'įvertinimas',
        'comment' => 'atsiliepimas',
        'reply' => 'atsakymas',
    ],

    'validation' => [
        'rating' => 'Pasirinkite įvertinimą nuo 1 iki 5 žvaigždučių.',
    ],

    'errors' => [
        'not_owner' => 'Įvertinti gali tik užklausą sukūręs klientas.',
        'not_completed' => 'Įvertinti galima tik atliktą darbą.',
        'window_passed' => 'Atsiliepimą palikti galima per :days d. nuo darbo užbaigimo.',
        'already_reviewed' => 'Šį darbą jau įvertinote.',
        'clients_only' => 'Atsiliepimus gali palikti tik klientai.',
        'own_profile' => 'Savo profilio vertinti negalima.',
        'provider_inactive' => 'Šis teikėjas šiuo metu neaktyvus.',
        'recently_reviewed' => 'Šiam teikėjui atsiliepimą jau palikote per pastaruosius 12 mėnesių.',
        'banned' => 'Jūsų paskyra užblokuota.',
        'reply_not_allowed' => 'Atsakyti galima tik į paskelbtą atsiliepimą apie jus ir tik vieną kartą.',
    ],

    'flash' => [
        'verified_created' => 'Ačiū! Jūsų atsiliepimas paskelbtas.',
        'invitation_created' => 'Ačiū! Atsiliepimas bus paskelbtas, kai jį peržiūrės administratorius.',
        'replied' => 'Atsakymas paskelbtas.',
    ],

    // Pranešimai: „message" – varpeliui, kiti – el. laiškui
    'notifications' => [
        'invitation' => [
            'message' => 'Įvertinkite atliktą darbą: „:title"',
            'subject' => 'Kaip sekėsi? Įvertinkite: :title',
            'intro' => 'Pažymėjote, kad darbas „:title" atliktas. Pasidalykite patirtimi – atsiliepimas padės kitiems klientams išsirinkti, o :provider – augti.',
            'deadline' => 'Atsiliepimą galite palikti per :days d.',
            'action' => 'Palikti atsiliepimą',
        ],
        'new_review' => [
            'message' => 'Gavote naują atsiliepimą (:rating★) nuo :author',
            'subject' => 'Naujas atsiliepimas: :rating★',
            'intro' => ':author paliko jums atsiliepimą (:rating★):',
            'action' => 'Peržiūrėti ir atsakyti',
        ],
        'replied' => [
            'message' => ':provider atsakė į jūsų atsiliepimą',
            'subject' => ':provider atsakė į jūsų atsiliepimą',
            'intro' => ':provider atsakė į jūsų atsiliepimą:',
            'action' => 'Peržiūrėti',
        ],
    ],
];
