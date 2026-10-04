<?php

/*
|--------------------------------------------------------------------------
| Platformos produkto taisyklės (Etapas 9)
|--------------------------------------------------------------------------
|
| Skaičiai, kuriuos savininkas gali keisti be programuotojo: .env faile arba čia. Kodas skaito
| config('marketplace.…'), o ne env() – po „php artisan config:cache" env() už config failų grąžina null.
| https://laravel.com/docs/13.x/configuration#accessing-configuration-values
|
| Planų privalumai (max_categories, badge) laikomi subscription_plans.features (DB), o čia – tik tai,
| kas galioja teikėjui BE prenumeratos.
|
*/

return [

    /*
    | Kiek kategorijų (category_provider_profile eilučių) gali pasirinkti teikėjas be prenumeratos.
    | Visa 2 lygio grupė („Apdailos darbai") skaičiuojama kaip viena. Produkto sprendimas: 5 – užtenka
    | vienai sričiai, o norintiems daugiau – prenumerata (planų ribos – database/data/monetization.php).
    | Pakeitus skaičių, esamų teikėjų kategorijos neištrinamos: viršijantys ribą jas pasilieka, tik naujų
    | pridėti negali (SyncProviderCategories).
    */
    'free_max_categories' => (int) env('FREE_MAX_CATEGORIES', 5),

];
