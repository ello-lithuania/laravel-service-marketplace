<?php

/*
|--------------------------------------------------------------------------
| Kreditai, prenumeratos, mokėjimai, sąskaitos – Etapas 7
|--------------------------------------------------------------------------
|
| „message" – trumpas tekstas varpeliui (notifications.data.message), kiti pranešimų raktai – el. laiškui.
|
*/

return [
    'purchasable' => [
        'credit_package' => 'Kreditų paketas „:name"',
        'subscription_plan' => 'Prenumerata „:name" (:period)',
        'unknown' => 'Mokėjimas',
    ],

    // subscription_plans.features (JSON) → punktai kainų puslapyje
    'plan_features' => [
        'credits' => ':credits kreditų kas :period',
        'max_categories' => 'Iki :count paslaugų kategorijų',
        'badge' => 'Ženklelis profilyje',
        'priority_support' => 'Prioritetinė pagalba',
    ],

    'ledger' => [
        'purchase' => 'Kreditų paketas „:name"',
        'subscription' => 'Prenumerata „:plan" (:from – :to)',
        'admin_adjustment' => 'Koregavimas: :reason (:admin)',
    ],

    'errors' => [
        'package_inactive' => 'Šis kreditų paketas nebeparduodamas.',
        'plan_inactive' => 'Šis prenumeratos planas nebeparduodamas.',
        'plan_already_active' => 'Šį planą jau turite – jis pratęsiamas automatiškai.',
        'plan_scheduled' => 'Jau turite suplanuotą planą „:plan", kuris prasidės :date. Kitą planą galėsite pasirinkti po to.',
        'payment_not_payable' => 'Šio mokėjimo apmokėti nebegalima.',
    ],

    'flash' => [
        'payment_paid' => 'Ačiū! Mokėjimas gautas.',
        'payment_cancelled' => 'Mokėjimas atšauktas – pinigai nenuskaičiuoti.',
        'payment_failed' => 'Mokėjimas nepavyko. Pabandykite dar kartą.',
        'subscription_cancelled' => 'Prenumerata atšaukta. Ji galioja iki :date, vėliau nebus pratęsiama.',
        'subscription_ended' => 'Prenumerata baigta.',
    ],

    'notifications' => [
        'payment_succeeded' => [
            'message' => 'Mokėjimas :amount gautas – :item',
            'subject' => 'Mokėjimas gautas, sąskaita :number',
            'intro' => 'Gavome jūsų mokėjimą :amount už: :item. Ačiū!',
            'invoice' => 'Sąskaitos faktūros numeris: :number. Ją bet kada rasite paskyroje, skiltyje „Mokėjimai".',
            'action' => 'Atsisiųsti sąskaitą faktūrą',
        ],
        'subscription_expiring' => [
            'message' => 'Prenumerata „:plan" baigiasi :date – apmokėkite pratęsimą',
            'subject' => 'Prenumerata „:plan" baigiasi :date',
            'intro' => 'Jūsų prenumerata „:plan" galioja iki :date. Kad ji nenutrūktų, apmokėkite kitą laikotarpį.',
            'amount' => 'Pratęsimo kaina: :amount.',
            'action' => 'Apmokėti pratęsimą',
            'outro' => 'Jei pratęsti nenorite, nieko daryti nereikia – prenumerata tiesiog baigsis.',
        ],
        'subscription_past_due' => [
            'message' => 'Prenumerata „:plan" nesumokėta – apmokėkite iki :grace_date',
            'subject' => 'Prenumerata „:plan" nesumokėta',
            'intro' => 'Prenumeratos „:plan" laikotarpis baigėsi :date. Apmokėkite pratęsimą iki :grace_date, kitaip prenumerata bus nutraukta.',
        ],
        'low_credits' => [
            'message' => 'Liko tik :balance kred. – papildykite, kad galėtumėte siųsti pasiūlymus',
            'subject' => 'Baigiasi kreditai',
            'intro' => 'Jūsų kreditų likutis – :balance. Kad galėtumėte toliau siųsti pasiūlymus klientams, įsigykite kreditų paketą arba prenumeratą.',
            'action' => 'Papildyti kreditus',
        ],
    ],
];
