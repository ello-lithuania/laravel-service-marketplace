<?php

/*
|--------------------------------------------------------------------------
| Pasiūlymų tekstai – Etapas 5
|--------------------------------------------------------------------------
*/

return [
    'attributes' => [
        'message' => 'žinutė klientui',
        'price' => 'kaina',
        'price_type' => 'kainos tipas',
        'duration_text' => 'trukmė',
        'start_date' => 'pradžios data',
    ],

    'validation' => [
        'price_required' => 'Nurodykite kainą arba pasirinkite „Po apžiūros".',
    ],

    'errors' => [
        'request_not_open' => 'Ši užklausa nebepriima pasiūlymų.',
        'request_closed_for_action' => 'Tai galima tik tol, kol užklausa laukia pasiūlymų. Atnaujinkite puslapį.',
        'not_eligible' => 'Šiai užklausai pasiūlymo siųsti negalite: ji ne jūsų paslaugų srityje arba aptarnavimo zonoje.',
        'duplicate' => 'Šiai užklausai pasiūlymą jau išsiuntėte.',
        'insufficient_credits' => 'Nepakanka kreditų: pasiūlymas kainuoja :required kred., o jūs turite :balance.',
        'provider_inactive' => 'Jūsų profilis neaktyvus, todėl pasiūlymų siųsti negalite.',
        'not_provider' => 'Pasiūlymus gali siųsti tik paslaugų teikėjai.',
    ],

    'flash' => [
        'sent' => 'Pasiūlymas išsiųstas. Nurašyta kreditų: :credits.',
        'withdrawn' => 'Pasiūlymas atšauktas.',
        'accepted' => 'Pasiūlymas priimtas! Teikėjas gavo jūsų kontaktus.',
        'declined' => 'Pasiūlymas atmestas.',
    ],

    'ledger' => [
        'offer' => 'Pasiūlymas: :title',
    ],
];
