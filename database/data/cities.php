<?php

/**
 * Lietuvos apskritys ir 60 savivaldybių (vieši administraciniai duomenys, ne asmens duomenys).
 *
 * Pavadinimo taisyklė (docs/DB_SCHEMA.md → cities): miesto savivaldybė – miesto vardu („Kaunas"),
 * rajono, kai yra to miesto savivaldybė – „X r." („Kauno r."), kitos – centro miesto vardu („Jonava").
 *
 * population – apytikslis gyventojų skaičius; naudojamas tik kaip svoris testiniams duomenims
 * (docs/SEEDING.md 4 sk.), todėl tikslumas nesvarbus. lat/lng – savivaldybės centras.
 */

return [
    'Alytaus apskritis' => [
        ['Alytus', 'Alytuje', 54.3963, 24.0459, 51000],
        ['Alytaus r.', 'Alytaus rajone', 54.3450, 24.0950, 25000],
        ['Druskininkai', 'Druskininkuose', 54.0154, 23.9737, 21000],
        ['Lazdijai', 'Lazdijuose', 54.2341, 23.5156, 18000],
        ['Varėna', 'Varėnoje', 54.2207, 24.5778, 20000],
    ],
    'Kauno apskritis' => [
        ['Kaunas', 'Kaune', 54.8985, 23.9036, 305000],
        ['Kauno r.', 'Kauno rajone', 54.9500, 23.9800, 99000],
        ['Birštonas', 'Birštone', 54.6047, 24.0264, 4000],
        ['Jonava', 'Jonavoje', 55.0727, 24.2797, 41000],
        ['Kaišiadorys', 'Kaišiadoryse', 54.8667, 24.4500, 29000],
        ['Kėdainiai', 'Kėdainiuose', 55.2883, 23.9747, 46000],
        ['Prienai', 'Prienuose', 54.6333, 23.9500, 24000],
        ['Raseiniai', 'Raseiniuose', 55.3797, 23.1239, 30000],
    ],
    'Klaipėdos apskritis' => [
        ['Klaipėda', 'Klaipėdoje', 55.7033, 21.1443, 159000],
        ['Klaipėdos r.', 'Klaipėdos rajone', 55.7128, 21.4011, 59000],
        ['Kretinga', 'Kretingoje', 55.8888, 21.2422, 40000],
        ['Neringa', 'Neringoje', 55.3033, 21.0058, 4000],
        ['Palanga', 'Palangoje', 55.9175, 21.0686, 18000],
        ['Skuodas', 'Skuode', 56.2667, 21.5333, 16000],
        ['Šilutė', 'Šilutėje', 55.3489, 21.4834, 36000],
    ],
    'Marijampolės apskritis' => [
        ['Marijampolė', 'Marijampolėje', 54.5594, 23.3541, 56000],
        ['Kalvarija', 'Kalvarijoje', 54.4167, 23.2333, 10000],
        ['Kazlų Rūda', 'Kazlų Rūdoje', 54.7489, 23.4900, 11000],
        ['Šakiai', 'Šakiuose', 54.9500, 23.0500, 25000],
        ['Vilkaviškis', 'Vilkaviškyje', 54.6500, 23.0333, 33000],
    ],
    'Panevėžio apskritis' => [
        ['Panevėžys', 'Panevėžyje', 55.7348, 24.3575, 87000],
        ['Panevėžio r.', 'Panevėžio rajone', 55.7700, 24.4500, 35000],
        ['Biržai', 'Biržuose', 56.2000, 24.7500, 23000],
        ['Kupiškis', 'Kupiškyje', 55.8333, 24.9667, 15000],
        ['Pasvalys', 'Pasvalyje', 56.0667, 24.4000, 22000],
        ['Rokiškis', 'Rokiškyje', 55.9667, 25.5833, 27000],
    ],
    'Šiaulių apskritis' => [
        ['Šiauliai', 'Šiauliuose', 55.9349, 23.3137, 108000],
        ['Šiaulių r.', 'Šiaulių rajone', 55.8900, 23.2400, 40000],
        ['Naujoji Akmenė', 'Naujojoje Akmenėje', 56.3167, 22.9000, 19000],
        ['Joniškis', 'Joniškyje', 56.2333, 23.6167, 21000],
        ['Kelmė', 'Kelmėje', 55.6333, 22.9333, 26000],
        ['Pakruojis', 'Pakruojyje', 55.9667, 23.8500, 19000],
        ['Radviliškis', 'Radviliškyje', 55.8167, 23.5333, 33000],
    ],
    'Tauragės apskritis' => [
        ['Tauragė', 'Tauragėje', 55.2522, 22.2897, 37000],
        ['Jurbarkas', 'Jurbarke', 55.0833, 22.7667, 25000],
        ['Pagėgiai', 'Pagėgiuose', 55.1333, 21.9000, 7000],
        ['Šilalė', 'Šilalėje', 55.4833, 22.1833, 21000],
    ],
    'Telšių apskritis' => [
        ['Telšiai', 'Telšiuose', 55.9833, 22.2500, 38000],
        ['Mažeikiai', 'Mažeikiuose', 56.3167, 22.3333, 52000],
        ['Plungė', 'Plungėje', 55.9167, 21.8500, 33000],
        ['Rietavas', 'Rietave', 55.7228, 21.9306, 7000],
    ],
    'Utenos apskritis' => [
        ['Utena', 'Utenoje', 55.4978, 25.5992, 37000],
        ['Anykščiai', 'Anykščiuose', 55.5333, 25.1000, 23000],
        ['Ignalina', 'Ignalinoje', 55.3500, 26.1667, 14000],
        ['Molėtai', 'Molėtuose', 55.2333, 25.4167, 17000],
        ['Visaginas', 'Visagine', 55.5980, 26.4380, 18000],
        ['Zarasai', 'Zarasuose', 55.7333, 26.2500, 14000],
    ],
    'Vilniaus apskritis' => [
        ['Vilnius', 'Vilniuje', 54.6872, 25.2797, 600000],
        ['Vilniaus r.', 'Vilniaus rajone', 54.7500, 25.3500, 107000],
        ['Elektrėnai', 'Elektrėnuose', 54.7833, 24.6667, 23000],
        ['Šalčininkai', 'Šalčininkuose', 54.3000, 25.3833, 30000],
        ['Širvintos', 'Širvintose', 55.0500, 24.9500, 15000],
        ['Švenčionys', 'Švenčionyse', 55.1333, 26.1667, 23000],
        ['Trakai', 'Trakuose', 54.6333, 24.9333, 32000],
        ['Ukmergė', 'Ukmergėje', 55.2500, 24.7500, 35000],
    ],
];
