<?php

// Etapas 8: BDAR – duomenų eksportas ir paskyros ištrynimas (anonimizavimas)

return [
    'deleted_user_name' => 'Ištrintas vartotojas',
    'deleted_provider_name' => 'Ištrintas teikėjas',
    'request_cancel_reason' => 'Klientas ištrynė paskyrą.',

    'export' => [
        'queued' => 'Ruošiame jūsų duomenų archyvą. Kai jis bus paruoštas, gausite el. laišką (paprastai per kelias minutes).',
        'mail_subject' => 'Jūsų duomenų archyvas paruoštas',
        'mail_intro' => 'Jūsų prašymu paruošėme visų jūsų duomenų archyvą (ZIP: duomenys JSON formatu ir jūsų įkeltos nuotraukos).',
        'mail_expiry' => 'Archyvą atsisiųsti galėsite :days d. – vėliau jis bus automatiškai ištrintas.',
        'mail_action' => 'Atsisiųsti nustatymuose',
        'mail_outro' => 'Jei šio archyvo neprašėte, nedelsdami pakeiskite slaptažodį.',
        'message' => 'Jūsų duomenų archyvas paruoštas. Atsisiųsti galite: Nustatymai → Privatumas.',
        'readme' => "Šiame archyve – visi :app saugomi jūsų duomenys (BDAR 15 ir 20 straipsniai).\n\n"
            ."duomenys.json – paskyra, teikėjo profilis, užklausos, pasiūlymai, pokalbiai, atsiliepimai, mokėjimai,\n"
            ."kreditų operacijos, prenumeratos, pranešimai ir skundai. Datos – UTC laiku (ISO 8601), pinigai – centais.\n"
            ."nuotraukos/ – jūsų įkeltos nuotraukos (originalai).\n\n"
            .'Archyvas paruoštas :date.',
    ],

    'delete' => [
        'admin_forbidden' => 'Administratoriaus paskyros ištrinti negalima – kreipkitės į kitą administratorių.',
        'deleted' => 'Paskyra ištrinta. Jūsų asmens duomenys pašalinti.',
    ],
];
