<?php

/**
 * Užklausų tekstų bankas (docs/SEEDING.md 5 sk.). Vietos rezervavimas:
 * {paslauga} – 3 lygio kategorija („Plytelių klijavimas"), {paslauga_m} – mažąja raide,
 * {miestas} – vietininkas („Vilniuje"), {plotas}, {kiekis}, {patalpa}, {terminas}.
 * details – pagal 1 lygio kategorijos slug.
 */

return [
    'titles' => [
        '{paslauga} {miestas}',
        '{paslauga} – ieškau meistro',
        'Reikalinga paslauga: {paslauga_m}',
        'Ieškau specialisto: {paslauga_m}',
        '{paslauga} {miestas}, skubiai',
        '{paslauga} – prašau pasiūlymų',
        'Reikia pagalbos: {paslauga_m}',
    ],
    'openings' => [
        'Laba diena.',
        'Sveiki,',
        'Laba diena, ieškau patikimo specialisto.',
        'Sveiki, reikia pagalbos.',
        'Laba diena, gal kas galėtų padėti?',
        'Ieškau žmogaus, kuris atliktų darbus kokybiškai.',
    ],
    'details' => [
        'statyba-ir-remontas' => [
            'Reikia atlikti darbus {patalpa}, plotas apie {plotas} m².',
            'Butas senos statybos, sienos nelygios.',
            'Namas naujos statybos, darbai – nuo nulio.',
            'Dalis darbų jau atlikta, reikia užbaigti.',
            'Prieš tai buvo dirbęs kitas meistras, reikia pataisyti jo darbą.',
        ],
        'santechnika-ir-sildymas' => [
            'Reikia pakeisti įrangą {patalpa}.',
            'Varva vanduo, problema kartojasi jau kelias savaites.',
            'Įranga jau nupirkta, reikia tik sumontuoti.',
            'Namas apie {plotas} m², šildomas dujomis.',
            'Norime modernizuoti sistemą, kad būtų pigiau šildyti.',
        ],
        'elektra-ir-apsvietimas' => [
            'Reikia darbų {patalpa}, apie {kiekis} taškų.',
            'Kartais išmuša saugiklius, reikia rasti priežastį.',
            'Instaliacija sena, aliumininiai laidai.',
            'Reikalinga atlikti darbus ir pateikti matavimų protokolą.',
            'Namas apie {plotas} m², norime įsirengti naują sistemą.',
        ],
        'namu-ukis-ir-valymas' => [
            'Butas apie {plotas} m², 2 kambariai.',
            'Reikalingas vienkartinis darbas.',
            'Norėtume, kad tai būtų daroma reguliariai, kas dvi savaites.',
            'Yra naminių gyvūnų.',
            'Darbus reikia atlikti savaitgalį.',
        ],
        'kraustymas-ir-transportas' => [
            'Reikia pervežti daiktus iš {kiekis} kambarių buto.',
            'Yra keli stambūs baldai, reikės nešti į {kiekis} aukštą be lifto.',
            'Pervežimas mieste, atstumas apie {plotas} km.',
            'Reikalingi ir krovikai.',
            'Daiktai bus supakuoti iš anksto.',
        ],
        'aplinka-ir-sodas' => [
            'Sklypas apie {plotas} arų.',
            'Reikia sutvarkyti kiemą prieš sezoną.',
            'Teritorija apleista, daug krūmų.',
            'Norėtume, kad darbai būtų atliekami visą sezoną.',
            'Turime eskizą, kaip turėtų atrodyti.',
        ],
        'baldai-ir-interjeras' => [
            'Reikia darbų {patalpa}, plotas apie {plotas} m².',
            'Turiu brėžinį su matmenimis.',
            'Norėtųsi natūralių medžiagų.',
            'Baldai jau nupirkti, reikia tik surinkti.',
            'Ieškau idėjų, nes dar nesu tikras dėl stiliaus.',
        ],
        'automobiliu-paslaugos' => [
            'Automobilis – {kiekis} metų senumo.',
            'Gedimas atsirado prieš kelias dienas.',
            'Reikia, kad būtų atlikta greitai, automobilis reikalingas darbui.',
            'Galiu atvažiuoti pats arba atgabenti evakuatoriumi.',
            'Dalis detalių jau nupirkta.',
        ],
        'grozis-ir-sveikata' => [
            'Paslaugos reikėtų {terminas}.',
            'Pageidautina, kad specialistas galėtų atvykti į namus.',
            'Esu pirmą kartą, reikės konsultacijos.',
            'Ieškau specialisto ilgalaikiam bendradarbiavimui.',
            'Svarbu, kad būtų naudojamos kokybiškos priemonės.',
        ],
        'it-ir-kompiuteriai' => [
            'Reikia sprendimo mažai įmonei, apie {kiekis} darbuotojų.',
            'Turiu pavyzdžių, kas patinka.',
            'Svarbu, kad būtų galima toliau prižiūrėti patiems.',
            'Problema atsirado po atnaujinimo.',
            'Biudžetas ribotas, bet kokybė svarbiausia.',
        ],
        'renginiai-ir-sventes' => [
            'Renginys vyks {terminas}, svečių apie {kiekis}0.',
            'Šventė vyks lauke, jei bus gražus oras.',
            'Ieškome profesionalo su atsiliepimais.',
            'Renginys truks apie {kiekis} valandas.',
            'Norėtume suderinti viską vienoje vietoje.',
        ],
        'mokymai-ir-korepetitoriai' => [
            'Mokinys mokosi {kiekis} klasėje.',
            'Pamokų reikėtų du kartus per savaitę.',
            'Galima ir nuotoliniu būdu.',
            'Ruošiamės egzaminams.',
            'Esu suaugęs, mokytis noriu nuo pradžių.',
        ],
    ],
    'materials' => [
        'Medžiagas turiu.',
        'Medžiagas reikėtų atsivežti.',
        'Dėl medžiagų galime susitarti.',
        'Medžiagų dar neturiu, reikės patarimo.',
    ],
    'timing' => [
        'Darbus norėčiau pradėti {terminas}.',
        'Laikas lankstus.',
        'Darbai turėtų būti baigti per {kiekis} savaites.',
        'Skubu, nes netrukus atvyksta svečių.',
    ],
    'closings' => [
        'Prašau nurodyti preliminarią kainą.',
        'Laukiu pasiūlymų su kaina ir terminais.',
        'Galiu atsiųsti nuotraukų.',
        'Būtų gerai, jei galėtumėte atvykti apžiūrėti.',
        'Ačiū!',
    ],
    'placeholders' => [
        'patalpa' => ['vonios kambaryje', 'virtuvėje', 'svetainėje', 'koridoriuje', 'miegamajame', 'name', 'bute', 'biure'],
        'terminas' => ['kuo skubiau', 'šią savaitę', 'kitą savaitę', 'per mėnesį', 'po švenčių', 'kitą mėnesį'],
    ],
];
