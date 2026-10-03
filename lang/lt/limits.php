<?php

/*
|--------------------------------------------------------------------------
| Dažnio ribos (rate limiting) – Etapas 6
|--------------------------------------------------------------------------
|
| Rodoma, kai vartotojas per trumpą laiką atlieka per daug veiksmų (AppServiceProvider::configureRateLimiting()).
|
*/

return [
    'too_many' => 'Per daug bandymų per trumpą laiką. Bandykite dar kartą po :minutes min.',
];
