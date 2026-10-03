<?php

/**
 * 3 lygių paslaugų medis (mūsų sugalvota taksonomija, docs/DB_SCHEMA.md 2.2).
 *
 * 1 lygis: name, icon (lucide ikonos vardas), weight (populiarumas testiniams duomenims),
 *          budget [min, max] eurais (testiniams duomenims), cost (pasiūlymo kaina kreditais),
 *          children.
 * 2 lygis: name, weight, cost (nebūtina – paveldima iš 1 lygio), children.
 * 3 lygis: pavadinimas arba ['name' => …, 'cost' => …]. Užklausos priskiriamos tik 3 lygiui.
 *
 * Pavadinimai turi būti unikalūs visame medyje – iš jų daromas slug (URL).
 */

return [
    [
        'name' => 'Statyba ir remontas', 'icon' => 'hammer', 'weight' => 22, 'budget' => [200, 30000], 'cost' => 2,
        'children' => [
            ['name' => 'Apdailos darbai', 'weight' => 10, 'children' => [
                'Plytelių klijavimas', 'Sienų glaistymas', 'Sienų ir lubų dažymas', 'Gipso kartono montavimas',
                'Grindų liejimas', 'Tapetavimas',
            ]],
            ['name' => 'Grindys', 'weight' => 6, 'children' => [
                'Laminato klojimas', 'Parketo klojimas', 'Parketo šlifavimas ir lakavimas', 'Kiliminės dangos klojimas',
                'Vinilinių grindų klojimas',
            ]],
            ['name' => 'Stogai ir fasadai', 'weight' => 5, 'children' => [
                'Stogo dengimas', 'Stogo remontas', 'Fasado šiltinimas', 'Fasado dažymas', 'Lietaus nuotekų sistemos',
                'Kaminų įrengimas',
            ]],
            ['name' => 'Mūro ir betono darbai', 'weight' => 4, 'children' => [
                'Mūrijimo darbai', 'Pamatų įrengimas', 'Betonavimo darbai', 'Tinkavimas', 'Sienų griovimas',
            ]],
            ['name' => 'Langai ir durys', 'weight' => 5, 'cost' => 1, 'children' => [
                'Plastikinių langų montavimas', 'Durų montavimas', 'Langų reguliavimas', 'Palangių montavimas',
                'Stoglangių montavimas',
            ]],
            ['name' => 'Namų statyba', 'weight' => 3, 'cost' => 3, 'children' => [
                'Karkasinių namų statyba', 'Mūrinių namų statyba', 'Pirčių statyba', 'Garažų statyba', 'Namo projektavimas',
            ]],
        ],
    ],
    [
        'name' => 'Santechnika ir šildymas', 'icon' => 'wrench', 'weight' => 14, 'budget' => [30, 8000], 'cost' => 1,
        'children' => [
            ['name' => 'Santechnikos darbai', 'weight' => 10, 'children' => [
                'Maišytuvo keitimas', 'Unitazo montavimas', 'Vamzdynų keitimas', 'Kanalizacijos valymas',
                'Dušo kabinos montavimas', 'Vonios montavimas',
            ]],
            ['name' => 'Šildymo sistemos', 'weight' => 6, 'cost' => 2, 'children' => [
                'Šilumos siurblių įrengimas', 'Radiatorių montavimas', 'Grindinio šildymo įrengimas', 'Katilų montavimas',
                'Šildymo sistemos priežiūra',
            ]],
            ['name' => 'Vandentiekis ir nuotekos', 'weight' => 4, 'cost' => 2, 'children' => [
                'Vandens gręžinių įrengimas', 'Nuotekų valymo įrenginiai', 'Vandens filtrų montavimas',
                'Lauko vandentiekio tiesimas',
            ]],
            ['name' => 'Vėdinimas ir kondicionavimas', 'weight' => 5, 'children' => [
                'Kondicionierių montavimas', 'Rekuperatorių įrengimas', 'Kondicionierių valymas ir priežiūra',
                'Ventiliacijos sistemų įrengimas',
            ]],
        ],
    ],
    [
        'name' => 'Elektra ir apšvietimas', 'icon' => 'plug', 'weight' => 10, 'budget' => [30, 6000], 'cost' => 1,
        'children' => [
            ['name' => 'Elektros instaliacija', 'weight' => 8, 'children' => [
                'Elektros instaliacijos keitimas', 'Elektros skydelių montavimas', 'Lizdų ir jungiklių montavimas',
                'Elektros gedimų šalinimas', 'Įžeminimo įrengimas',
            ]],
            ['name' => 'Apšvietimas', 'weight' => 4, 'children' => [
                'Šviestuvų montavimas', 'LED apšvietimo įrengimas', 'Lauko apšvietimas',
            ]],
            ['name' => 'Saulės energetika', 'weight' => 4, 'cost' => 3, 'children' => [
                'Saulės elektrinių įrengimas', 'Saulės kolektorių montavimas', 'Elektromobilių įkrovimo stotelės',
            ]],
            ['name' => 'Silpnosios srovės sistemos', 'weight' => 3, 'children' => [
                'Apsaugos signalizacijos įrengimas', 'Vaizdo stebėjimo kamerų montavimas', 'Domofonų montavimas',
                'Išmaniųjų namų sistemos',
            ]],
        ],
    ],
    [
        'name' => 'Namų ūkis ir valymas', 'icon' => 'sparkles', 'weight' => 10, 'budget' => [20, 1500], 'cost' => 1,
        'children' => [
            ['name' => 'Patalpų valymas', 'weight' => 8, 'children' => [
                'Buto valymas', 'Generalinis valymas', 'Valymas po remonto', 'Biurų valymas', 'Langų valymas',
            ]],
            ['name' => 'Minkštų baldų ir kilimų valymas', 'weight' => 4, 'children' => [
                'Minkštų baldų valymas', 'Kilimų valymas', 'Čiužinių valymas',
            ]],
            ['name' => 'Buitinės technikos remontas', 'weight' => 5, 'children' => [
                'Skalbimo mašinų remontas', 'Šaldytuvų remontas', 'Indaplovių remontas', 'Viryklių remontas',
            ]],
            ['name' => 'Namų priežiūra', 'weight' => 3, 'children' => [
                'Auklės paslaugos', 'Senelių priežiūra', 'Gyvūnų priežiūra', 'Namų tvarkymas ir smulkūs darbai',
            ]],
        ],
    ],
    [
        'name' => 'Kraustymas ir transportas', 'icon' => 'truck', 'weight' => 8, 'budget' => [50, 3000], 'cost' => 1,
        'children' => [
            ['name' => 'Perkraustymas', 'weight' => 8, 'children' => [
                'Buto perkraustymas', 'Biuro perkraustymas', 'Krovikų paslaugos', 'Pianino pervežimas',
            ]],
            ['name' => 'Krovinių pervežimas', 'weight' => 5, 'children' => [
                'Krovinių pervežimas mikroautobusu', 'Statybinių atliekų išvežimas', 'Baldų pervežimas',
                'Automobilių pervežimas evakuatoriumi',
            ]],
            ['name' => 'Keleivių vežimas', 'weight' => 2, 'children' => [
                'Keleivių pervežimas mikroautobusu', 'Vestuvių automobilio nuoma',
            ]],
        ],
    ],
    [
        'name' => 'Aplinka ir sodas', 'icon' => 'trees', 'weight' => 8, 'budget' => [50, 15000], 'cost' => 1,
        'children' => [
            ['name' => 'Aplinkos tvarkymas', 'weight' => 6, 'children' => [
                'Žolės pjovimas', 'Vejos įrengimas', 'Gyvatvorių karpymas', 'Medžių pjovimas', 'Aplinkos projektavimas',
            ]],
            ['name' => 'Kiemo įrengimas', 'weight' => 5, 'cost' => 2, 'children' => [
                'Trinkelių klojimas', 'Tvorų montavimas', 'Vartų montavimas', 'Terasų įrengimas', 'Pavėsinių statyba',
            ]],
            ['name' => 'Laistymo ir drenažo sistemos', 'weight' => 2, 'children' => [
                'Automatinio laistymo sistemos', 'Drenažo įrengimas',
            ]],
            ['name' => 'Žemės darbai', 'weight' => 3, 'children' => [
                'Ekskavatoriaus paslaugos', 'Žemės lyginimas', 'Tvenkinių kasimas',
            ]],
        ],
    ],
    [
        'name' => 'Baldai ir interjeras', 'icon' => 'sofa', 'weight' => 7, 'budget' => [50, 10000], 'cost' => 1,
        'children' => [
            ['name' => 'Baldų gamyba', 'weight' => 5, 'cost' => 2, 'children' => [
                'Virtuvės baldų gamyba', 'Spintų gamyba', 'Baldų gamyba pagal užsakymą', 'Laiptų gamyba',
            ]],
            ['name' => 'Baldų surinkimas ir remontas', 'weight' => 5, 'children' => [
                'Baldų surinkimas', 'Baldų restauravimas', 'Minkštų baldų perdengimas',
            ]],
            ['name' => 'Interjero dizainas', 'weight' => 4, 'children' => [
                'Interjero dizaino projektas', 'Interjero konsultacija', '3D vizualizacijos',
            ]],
            ['name' => 'Užuolaidos ir žaliuzės', 'weight' => 2, 'children' => [
                'Žaliuzių montavimas', 'Roletų montavimas', 'Užuolaidų siuvimas',
            ]],
        ],
    ],
    [
        'name' => 'Automobilių paslaugos', 'icon' => 'car', 'weight' => 6, 'budget' => [20, 3000], 'cost' => 1,
        'children' => [
            ['name' => 'Automobilių remontas', 'weight' => 6, 'children' => [
                'Variklio remontas', 'Važiuoklės remontas', 'Automobilių elektrikos remontas', 'Automobilių diagnostika',
            ]],
            ['name' => 'Kėbulo darbai', 'weight' => 4, 'children' => [
                'Kėbulo remontas', 'Automobilių dažymas', 'Įlenkimų taisymas be dažymo',
            ]],
            ['name' => 'Padangos ir ratai', 'weight' => 4, 'children' => [
                'Padangų montavimas', 'Ratų suvedimas', 'Padangų saugojimas',
            ]],
            ['name' => 'Automobilių priežiūra', 'weight' => 3, 'children' => [
                'Automobilių plovimas', 'Automobilių poliravimas', 'Salono valymas',
            ]],
        ],
    ],
    [
        'name' => 'Grožis ir sveikata', 'icon' => 'scissors', 'weight' => 7, 'budget' => [15, 500], 'cost' => 1,
        'children' => [
            ['name' => 'Plaukų priežiūra', 'weight' => 5, 'children' => [
                'Kirpimas', 'Plaukų dažymas', 'Šukuosenos',
            ]],
            ['name' => 'Nagų priežiūra', 'weight' => 4, 'children' => [
                'Manikiūras', 'Pedikiūras', 'Nagų priauginimas',
            ]],
            ['name' => 'Kosmetologija', 'weight' => 4, 'children' => [
                'Veido procedūros', 'Makiažas', 'Antakių ir blakstienų priežiūra', 'Depiliacija',
            ]],
            ['name' => 'Masažai ir sportas', 'weight' => 4, 'children' => [
                'Masažas', 'Kineziterapija', 'Asmeninis treneris',
            ]],
        ],
    ],
    [
        'name' => 'IT ir kompiuteriai', 'icon' => 'laptop', 'weight' => 6, 'budget' => [30, 10000], 'cost' => 1,
        'children' => [
            ['name' => 'Kompiuterių ir telefonų remontas', 'weight' => 5, 'children' => [
                'Kompiuterių remontas', 'Telefonų remontas', 'Duomenų atkūrimas', 'Programų diegimas',
            ]],
            ['name' => 'Svetainių kūrimas', 'weight' => 5, 'cost' => 2, 'children' => [
                'Svetainės kūrimas', 'Elektroninės parduotuvės kūrimas', 'WordPress svetainių priežiūra',
                'Svetainių optimizavimas paieškai',
            ]],
            ['name' => 'Grafinis dizainas', 'weight' => 4, 'children' => [
                'Logotipo kūrimas', 'Reklaminių maketų dizainas', 'Socialinių tinklų dizainas',
            ]],
            ['name' => 'Tinklai ir IT priežiūra', 'weight' => 3, 'children' => [
                'Kompiuterių tinklų įrengimas', 'IT priežiūra įmonėms', 'Wi-Fi tinklo įrengimas',
            ]],
        ],
    ],
    [
        'name' => 'Renginiai ir šventės', 'icon' => 'party-popper', 'weight' => 6, 'budget' => [50, 8000], 'cost' => 1,
        'children' => [
            ['name' => 'Renginių organizavimas', 'weight' => 4, 'cost' => 2, 'children' => [
                'Vestuvių organizavimas', 'Gimtadienių organizavimas', 'Įmonių renginiai',
            ]],
            ['name' => 'Foto ir video', 'weight' => 5, 'children' => [
                'Fotografas', 'Vaizdo filmavimas', 'Dronų filmavimas',
            ]],
            ['name' => 'Muzika ir pramogos', 'weight' => 4, 'children' => [
                'Dainininkai ir muzikantai', 'Didžėjai', 'Renginių vedėjai', 'Animatoriai vaikams',
            ]],
            ['name' => 'Maitinimas ir dekoracijos', 'weight' => 4, 'children' => [
                'Kateringas', 'Tortai ir desertai', 'Šventinės dekoracijos', 'Gėlių kompozicijos',
            ]],
        ],
    ],
    [
        'name' => 'Mokymai ir korepetitoriai', 'icon' => 'graduation-cap', 'weight' => 5, 'budget' => [15, 1500], 'cost' => 1,
        'children' => [
            ['name' => 'Korepetitoriai', 'weight' => 6, 'children' => [
                'Matematikos korepetitorius', 'Lietuvių kalbos korepetitorius', 'Anglų kalbos korepetitorius',
                'Fizikos korepetitorius', 'Chemijos korepetitorius',
            ]],
            ['name' => 'Užsienio kalbos', 'weight' => 4, 'children' => [
                'Anglų kalbos kursai', 'Vokiečių kalbos kursai', 'Ispanų kalbos kursai', 'Lietuvių kalba užsieniečiams',
            ]],
            ['name' => 'Muzikos ir meno pamokos', 'weight' => 3, 'children' => [
                'Gitaros pamokos', 'Fortepijono pamokos', 'Dainavimo pamokos', 'Piešimo pamokos',
            ]],
            ['name' => 'Vairavimas ir kiti mokymai', 'weight' => 3, 'children' => [
                'Vairavimo pamokos', 'Programavimo kursai', 'Buhalterijos kursai',
            ]],
        ],
    ],
];
