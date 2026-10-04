<?php

/**
 * Paieškos frazės nuotraukų bankams (php artisan photos:download, Etapas 10).
 *
 * Frazės angliškos: Pexels ir Openverse nuotraukų aprašymai ir žymos daugiausia angliški, lietuviškai rastų mažai.
 * Reikšmė – frazė arba kelios frazės (bandoma iš eilės, kol randama tinkama nuotrauka).
 *
 * - categories: 1 ir 2 lygio kategorijos (slug = Str::slug(pavadinimas), žr. categories.php). Kategorija be frazės
 *   praleidžiama – nuotrauką galima įkelti per admin panelę.
 * - site: svetainės dizaino vietos (SitePhotoKey) + lietuviškas alternatyvusis tekstas (jį vėliau galima pakeisti).
 * - portfolio: demo portfolio rinkinys kiekvienai 1 lygio sričiai. Frazės parinktos taip, kad rodytų
 *   **atliktą darbą ar daiktus, o ne žmones** (docs/SEEDING.md 6 sk.: demo duomenyse – jokių atpažįstamų žmonių).
 *   Komanda dar praleidžia nuotraukas, kurių aprašyme yra žodžiai „man", „woman", „people"… – bet tai tik euristika:
 *   aprašymas ne visada pasako, kas nuotraukoje, todėl atsisiuntus verta peržiūrėti storage/app/stock-photos/portfolio.
 */

return [
    'categories' => [
        // 1 lygis
        'statyba-ir-remontas' => ['home renovation interior construction', 'house renovation tools'],
        'santechnika-ir-sildymas' => ['plumbing pipes bathroom sink', 'modern bathroom faucet'],
        'elektra-ir-apsvietimas' => ['electrical wiring installation', 'electrical panel wires'],
        'namu-ukis-ir-valymas' => ['house cleaning supplies', 'clean bright apartment'],
        'kraustymas-ir-transportas' => ['moving boxes van', 'cardboard moving boxes'],
        'aplinka-ir-sodas' => ['garden landscaping lawn', 'beautiful backyard garden'],
        'baldai-ir-interjeras' => ['modern furniture interior design', 'living room furniture'],
        'automobiliu-paslaugos' => ['car repair garage', 'auto service workshop'],
        'grozis-ir-sveikata' => ['beauty salon interior', 'spa cosmetics'],
        'it-ir-kompiuteriai' => ['computer repair laptop', 'laptop workspace technology'],
        'renginiai-ir-sventes' => ['event decoration party table', 'festive table decoration'],
        'mokymai-ir-korepetitoriai' => ['books study desk', 'notebook books learning'],

        // 2 lygis: Statyba ir remontas
        'apdailos-darbai' => ['wall painting renovation interior', 'paint roller wall'],
        'grindys' => ['wooden floor installation', 'parquet floor'],
        'stogai-ir-fasadai' => ['roof tiles house', 'house facade exterior'],
        'muro-ir-betono-darbai' => ['brick wall masonry', 'concrete construction'],
        'langai-ir-durys' => ['new windows house', 'front door house'],
        'namu-statyba' => ['house under construction', 'new house construction'],
        // Santechnika ir šildymas
        'santechnikos-darbai' => ['modern bathroom faucet', 'bathroom sink'],
        'sildymo-sistemos' => ['radiator heating system', 'heating boiler'],
        'vandentiekis-ir-nuotekos' => ['water pipes plumbing', 'water supply pipes'],
        'vedinimas-ir-kondicionavimas' => ['air conditioner unit wall', 'ventilation system'],
        // Elektra ir apšvietimas
        'elektros-instaliacija' => ['electrical panel wires', 'electrical installation'],
        'apsvietimas' => ['modern lighting fixtures interior', 'pendant lights'],
        'saules-energetika' => ['solar panels roof', 'solar panels'],
        'silpnosios-sroves-sistemos' => ['security camera', 'network cables'],
        // Namų ūkis ir valymas
        'patalpu-valymas' => ['clean bright living room', 'cleaning supplies'],
        'minkstu-baldu-ir-kilimu-valymas' => ['sofa upholstery cleaning', 'carpet cleaning'],
        'buitines-technikos-remontas' => ['washing machine appliance', 'kitchen appliances'],
        'namu-prieziura' => ['home maintenance tools', 'toolbox tools'],
        // Kraustymas ir transportas
        'perkraustymas' => ['moving boxes apartment', 'cardboard boxes moving'],
        'kroviniu-pervezimas' => ['cargo van delivery truck', 'delivery truck road'],
        'keleiviu-vezimas' => ['minibus passenger transport', 'passenger van'],
        // Aplinka ir sodas
        'aplinkos-tvarkymas' => ['lawn mowing garden', 'garden lawn mower'],
        'kiemo-irengimas' => ['paved backyard patio', 'garden patio'],
        'laistymo-ir-drenazo-sistemos' => ['garden sprinkler irrigation', 'lawn sprinkler'],
        'zemes-darbai' => ['excavator earthworks', 'excavator construction site'],
        // Baldai ir interjeras
        'baldu-gamyba' => ['carpentry workshop wood furniture', 'woodworking workshop'],
        'baldu-surinkimas-ir-remontas' => ['furniture assembly tools', 'flat pack furniture'],
        'interjero-dizainas' => ['interior design living room', 'modern interior'],
        'uzuolaidos-ir-zaliuzes' => ['window curtains', 'window blinds'],
        // Automobilių paslaugos
        'automobiliu-remontas' => ['car engine repair', 'car engine'],
        'kebulo-darbai' => ['car body paint shop', 'car body repair'],
        'padangos-ir-ratai' => ['car tires wheels', 'car tire'],
        'automobiliu-prieziura' => ['car wash detailing', 'clean car'],
        // Grožis ir sveikata
        'plauku-prieziura' => ['hair salon chairs', 'hairdresser tools'],
        'nagu-prieziura' => ['manicure nail polish', 'nail polish bottles'],
        'kosmetologija' => ['skincare cosmetics products', 'cosmetics'],
        'masazai-ir-sportas' => ['massage spa stones', 'gym equipment'],
        // IT ir kompiuteriai
        'kompiuteriu-ir-telefonu-remontas' => ['smartphone repair', 'computer repair'],
        'svetainiu-kurimas' => ['web design laptop code', 'programming code screen'],
        'grafinis-dizainas' => ['graphic design workspace', 'designer desk'],
        'tinklai-ir-it-prieziura' => ['server room network', 'network switch cables'],
        // Renginiai ir šventės
        'renginiu-organizavimas' => ['event venue decoration', 'banquet hall'],
        'foto-ir-video' => ['camera photography equipment', 'video camera'],
        'muzika-ir-pramogos' => ['concert stage lights', 'dj equipment'],
        'maitinimas-ir-dekoracijos' => ['catering buffet table', 'catering food'],
        // Mokymai ir korepetitoriai
        'korepetitoriai' => ['notebook study desk', 'homework notebook'],
        'uzsienio-kalbos' => ['language learning books', 'dictionary books'],
        'muzikos-ir-meno-pamokos' => ['piano keys music lesson', 'piano keys'],
        'vairavimas-ir-kiti-mokymai' => ['driving school car', 'car steering wheel'],
    ],

    'site' => [
        'hero' => [
            'queries' => ['handyman renovating apartment', 'apartment renovation tools'],
            'alt' => 'Meistras atlieka buto remonto darbus',
        ],
        'providers' => [
            'queries' => ['craftsman tools workshop', 'carpenter workshop tools'],
            'alt' => 'Meistro įrankiai dirbtuvėse',
        ],
        'request' => [
            'queries' => ['couple planning home renovation', 'home renovation plans'],
            'alt' => 'Namų remonto planavimas',
        ],
        'auth' => [
            'queries' => ['bright modern living room interior', 'cozy scandinavian living room'],
            'alt' => 'Šviesus modernus svetainės interjeras',
        ],
    ],

    'portfolio' => [
        'statyba-ir-remontas' => ['renovated bathroom tiles interior', 'new laminate floor room', 'freshly painted room interior', 'new roof house exterior', 'brick house facade'],
        'santechnika-ir-sildymas' => ['modern bathroom shower', 'new kitchen sink faucet', 'radiator heating room', 'boiler room pipes'],
        'elektra-ir-apsvietimas' => ['electrical panel installation', 'modern ceiling lights interior', 'solar panels roof', 'wall sockets switches'],
        'namu-ukis-ir-valymas' => ['clean modern kitchen', 'clean living room sofa', 'clean bathroom interior', 'clean apartment windows'],
        'kraustymas-ir-transportas' => ['moving boxes room', 'cargo van', 'packed boxes apartment', 'delivery truck road'],
        'aplinka-ir-sodas' => ['landscaped garden', 'paved garden path', 'green lawn backyard', 'wooden terrace garden'],
        'baldai-ir-interjeras' => ['new kitchen cabinets', 'built-in wardrobe', 'handmade wooden furniture', 'modern living room interior'],
        'automobiliu-paslaugos' => ['car engine bay', 'polished car', 'new car tires', 'car service garage'],
        'grozis-ir-sveikata' => ['manicure nails closeup', 'hair salon interior', 'spa treatment room', 'cosmetics products'],
        'it-ir-kompiuteriai' => ['website design laptop screen', 'computer motherboard', 'server rack cables', 'graphic design desk'],
        'renginiai-ir-sventes' => ['wedding table decoration', 'party balloons decoration', 'catering buffet food', 'event stage lights'],
        'mokymai-ir-korepetitoriai' => ['notebooks books desk', 'piano keys', 'empty classroom desks', 'math homework notebook'],
    ],
];
