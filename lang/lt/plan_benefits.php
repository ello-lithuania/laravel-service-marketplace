<?php

/*
|--------------------------------------------------------------------------
| Prenumeratų privalumai – Etapas 9c
|--------------------------------------------------------------------------
|
| Kategorijų riba vedlyje (SyncProviderCategories). :noun – „kategorijos" / „kategorijų" pagal skaičių
| (trans_choice su lietuviškomis daugiskaitos formomis: 1, 21… | 2–9, 22–29… | 10–20, 30…).
|
*/

return [
    'categories' => [
        // Kilmininkas po „iki": iki 1 kategorijos, iki 5 kategorijų, iki 10 kategorijų
        'noun_genitive' => 'kategorijos|kategorijų|kategorijų',
        'over_limit' => 'Galite pasirinkti iki :limit :noun, o pažymėjote :count. Pašalinkite nereikalingas arba rinkitės planą su daugiau kategorijų.',
        'over_limit_legacy' => 'Jūsų planas leidžia iki :limit :noun, o dabar turite :count. Esamas galite palikti arba pašalinti, o naują pridėti galėsite tik tada, kai iš viso liks ne daugiau kaip :limit.',
    ],
];
