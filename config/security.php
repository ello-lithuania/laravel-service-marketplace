<?php

/*
|--------------------------------------------------------------------------
| Saugos antraštės (Etapas 8)
|--------------------------------------------------------------------------
|
| Naudoja App\Http\Middleware\SecurityHeaders (registruotas bootstrap/app.php kaip globalus middleware).
| Paaiškinimai – docs/DEPLOYMENT.md → „Saugumas" ir docs/drafts/etapas-8.md.
|
| Papildomi CSP šaltiniai rašomi .env kableliais atskirtu sąrašu, pvz.:
|   CSP_EXTRA_IMG_SRC=https://cdn.example.lt,https://*.amazonaws.com
|
*/

$list = fn (string $key): array => array_values(array_filter(array_map('trim', explode(',', (string) env($key, '')))));

return [

    // Visų antraščių jungiklis (pvz. jei jas jau prideda Nginx ar CDN)
    'enabled' => (bool) env('SECURITY_HEADERS_ENABLED', true),

    'csp' => [
        'enabled' => (bool) env('CSP_ENABLED', true),

        // true – naršyklė pažeidimus tik praneša (Content-Security-Policy-Report-Only), bet nieko neblokuoja.
        // Patogu pirmą kartą įjungiant produkcijoje: savaitę stebim pranešimus, tada įjungiam blokavimą.
        'report_only' => (bool) env('CSP_REPORT_ONLY', false),

        // Kur naršyklė siunčia pažeidimų pranešimus (pvz. Sentry CSP endpoint'as). Tuščia – nesiunčia.
        'report_uri' => env('CSP_REPORT_URI'),

        'extra' => [
            'img-src' => $list('CSP_EXTRA_IMG_SRC'),
            'connect-src' => $list('CSP_EXTRA_CONNECT_SRC'),
            'frame-src' => $list('CSP_EXTRA_FRAME_SRC'),
            // Paysera: mokėjimo forma nukreipia į banko puslapį (Etapas 7). Chrome form-action taiko ir nukreipimams.
            'form-action' => ['https://bank.paysera.com', ...$list('CSP_EXTRA_FORM_ACTION')],
        ],
    ],

    'hsts' => [
        // Tik produkcijoje ir tik per HTTPS: naršyklė metus jungsis tik HTTPS (sertifikatą būtina pratęsinėti!)
        'max_age' => (int) env('HSTS_MAX_AGE', 31536000),
        'include_subdomains' => (bool) env('HSTS_INCLUDE_SUBDOMAINS', true),
    ],

];
