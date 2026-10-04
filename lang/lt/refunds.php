<?php

/*
|--------------------------------------------------------------------------
| Mokėjimų grąžinimai ir kreditinės sąskaitos – Etapas 9
|--------------------------------------------------------------------------
|
| Atskiras failas (ne billing.php), kad lygiagrečiai dirbant su kitais Etapo 9 darbais nesikirstų pakeitimai.
| „message" – trumpas tekstas varpeliui (notifications.data.message), kiti raktai – el. laiškui.
|
*/

return [
    'ledger' => 'Grąžintas mokėjimas :invoice (kreditinė sąskaita :credit_note)',

    'notifications' => [
        'payment_refunded' => [
            'message' => 'Mokėjimas :amount grąžintas – :item',
            'subject' => 'Mokėjimas grąžintas, kreditinė sąskaita :number',
            'intro' => 'Grąžinome jūsų mokėjimą :amount už: :item.',
            'reason' => 'Priežastis: :reason',
            'money' => 'Pinigai grąžinami tuo pačiu būdu, kuriuo mokėjote (:method). Jūsų sąskaitoje jie turėtų atsirasti per kelias darbo dienas.',
            'credits' => 'Iš kreditų balanso atimta: :credits.',
            'credits_shortfall' => 'Iš kreditų balanso atimta: :credits (dar :shortfall jau buvote išnaudoję pasiūlymams).',
            'subscription_ended' => 'Prenumerata „:plan" baigta.',
            'subscription_shortened' => 'Prenumerata „:plan" galios iki :date ir nebebus pratęsiama.',
            'credit_note' => 'Kreditinės sąskaitos faktūros numeris: :number. Ją bet kada rasite paskyroje, skiltyje „Mokėjimai".',
            'action' => 'Atsisiųsti kreditinę sąskaitą',
        ],
    ],
];
