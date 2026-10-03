<?php

/*
|--------------------------------------------------------------------------
| Pokalbių ir žinučių tekstai – Etapas 6
|--------------------------------------------------------------------------
|
| Failas vadinasi „messages" pagal sritį (žinutės), kaip service_requests.php ir offers.php.
| Validacijos pranešimų Laravel čia neieško – jie lang/lt/validation.php.
|
*/

return [
    'attributes' => [
        'body' => 'žinutė',
        'attachments' => 'priedai',
    ],

    'validation' => [
        'body_required' => 'Parašykite žinutę arba pridėkite priedą.',
        'attachments_max' => 'Vienoje žinutėje gali būti daugiausia :max priedai.',
    ],

    'errors' => [
        'not_participant' => 'Šis pokalbis jums nepriklauso.',
        'admin_read_only' => 'Administratorius pokalbį gali tik skaityti.',
        'closed' => 'Šiame pokalbyje rašyti nebegalima: pasiūlymas nebeaktualus.',
        'provider_cannot_start' => 'Pokalbį pradeda klientas. Kai jis parašys arba priims jūsų pasiūlymą, galėsite atsakyti.',
    ],

    // NewMessage pranešimas: „message" – varpeliui, kiti – el. laiškui
    'notification' => [
        'message' => 'Nauja žinutė nuo :sender',
        'subject' => 'Nauja žinutė nuo :sender',
        'intro' => ':sender parašė jums žinutę:',
        'action' => 'Atsakyti',
    ],

    'system_sender' => 'Sistema',
    'hidden' => 'Žinutė paslėpta administratoriaus.',
    'attachment_only' => 'Priedas',
];
