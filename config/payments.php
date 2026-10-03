<?php

/*
|--------------------------------------------------------------------------
| Mokėjimai, kreditai, prenumeratos (Etapas 7)
|--------------------------------------------------------------------------
|
| Slaptažodžiai ir projektų numeriai – TIK .env faile (jis Git'e ignoruojamas). Čia – tik nuorodos į juos
| ir numatytosios reikšmės. Kodas skaito config('payments.…'), o ne env(): po „php artisan config:cache"
| env() už config failų ribų grąžina null. https://laravel.com/docs/13.x/configuration#configuration-caching
|
*/

return [

    /*
    | Kuris mokėjimų tiekėjas naudojamas naujiems mokėjimams: „paysera" arba „fake".
    | „fake" – netikras tiekėjas: vietoje Paysera atidaromas vietinis puslapis su mygtukais „Apmokėti" ir
    | „Atmesti". Patogu dev'e ir testuose, bet produkcijoje jis uždraustas (PaymentGatewayManager).
    */
    'default' => env('PAYMENT_GATEWAY', 'fake'),

    'currency' => 'EUR',

    // Kai po pasiūlymo balansas nukrenta žemiau šios ribos, teikėjas gauna LowCredits pranešimą
    'low_credits_threshold' => (int) env('LOW_CREDITS_THRESHOLD', 3),

    'subscriptions' => [
        // Prieš kiek dienų iki pabaigos sukuriamas pratęsimo mokėjimas ir išsiunčiamas priminimas
        'renewal_notice_days' => (int) env('SUBSCRIPTION_RENEWAL_NOTICE_DAYS', 7),
        // Malonės laikotarpis: kiek dienų po pabaigos dar galima apmokėti pratęsimą (būsena past_due)
        'grace_days' => (int) env('SUBSCRIPTION_GRACE_DAYS', 3),
    ],

    /*
    | Paysera (WebToPay / Checkout) – https://developers.paysera.com/en/checkout/basic
    | project_id ir sign_password – Paysera savitarnoje: Projektai → jūsų projektas → Bendri nustatymai.
    */
    'paysera' => [
        'project_id' => env('PAYSERA_PROJECT_ID'),
        'sign_password' => env('PAYSERA_SIGN_PASSWORD'),
        // Testinis režimas: Paysera leidžia „apmokėti" be tikrų pinigų. Produkcijoje – false
        'test' => (bool) env('PAYSERA_TEST', true),
        'pay_url' => env('PAYSERA_PAY_URL', 'https://bank.paysera.com/pay/'),
        // Callback'o adresas pagal nutylėjimą – route('payments.callback', 'paysera'). Keisti verta, kai
        // lokaliai testuojat per tunelį (pvz. ngrok), nes Paysera serveris localhost'o nepasiekia
        'callback_url' => env('PAYSERA_CALLBACK_URL'),
        // Callback'o parašas: „ss2" – RSA (Paysera viešasis raktas, rekomenduojama), „ss1" – md5 su slaptažodžiu
        'signature' => env('PAYSERA_SIGNATURE', 'ss2'),
        // Viešasis raktas ss2 parašui: pirmiausia skaitomas failas, jei jo nėra – parsisiunčiamas ir laikomas cache
        // („php artisan payments:paysera-key" jį parsiunčia ir įrašo į failą iš anksto)
        'public_key_url' => env('PAYSERA_PUBLIC_KEY_URL', 'https://www.paysera.com/download/public.key'),
        'public_key_path' => env('PAYSERA_PUBLIC_KEY_PATH', storage_path('app/private/paysera-public.key')),
    ],

];
