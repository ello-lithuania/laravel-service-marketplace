# Seed'ų (testinių duomenų) planas

> **Etapas 2 · būsena: įgyvendinta.** Kodas – `database/seeders/DemoDataSeeder.php` ir `database/seeders/Demo/`.
> Kiekiai ir laikai lentelėse – išmatuoti (2026-10-03).

**Sąvokos:**

- **Seeder** – klasė, kuri užpildo DB duomenimis (WordPress analogas – demo turinio importas, tik generuojamas
  kodu). → https://laravel.com/docs/13.x/seeding
- **Factory** – vieno modelio įrašo „receptas" su netikrais duomenimis. Naudojamas testuose ir seeder'iuose.
  → https://laravel.com/docs/13.x/eloquent-factories
- **Faker** – biblioteka, generuojanti netikrus vardus, adresus, telefonus. → https://fakerphp.org

## Turinys

1. [Tikslai](#1-tikslai)
2. [Kiekiai](#2-kiekiai)
3. [Generavimo eiliškumas](#3-generavimo-eiliškumas)
4. [Realistiškumo taisyklės](#4-realistiškumo-taisyklės)
5. [Lietuviški tekstai ir Faker lt_LT](#5-lietuviški-tekstai-ir-faker-lt_lt)
6. [Jokių tikrų duomenų](#6-jokių-tikrų-duomenų)
7. [Greitis: kaip įterpti ~1,8 mln. eilučių](#7-greitis-kaip-įterpti-18-mln-eilučių)
8. [Patikrinimai po seed'inimo](#8-patikrinimai-po-seedinimo)
9. [Nustatymai ir komandos](#9-nustatymai-ir-komandos)

---

## 1. Tikslai

1. **Realistiškas kiekis.** Puslapiavimo, indeksų, N+1 ir lėtų užklausų problemos turi išlįsti dev'e, o ne produkcijoje.
2. **Jokių tikrų asmens duomenų** (6 sk.).
3. **Nuoseklumas.** Duomenys laikosi tų pačių verslo taisyklių kaip programa: pasiūlymus siunčia tik tinkami
   teikėjai, kreditų balansas lygus ledger sumai, atsiliepimai rašomi tik po atlikto darbo ir t.t.
4. **Atkartojamumas.** Tas pats `SEED_FAKER_SEED` visada duoda tuos pačius duomenis (datos skaičiuojamos nuo
   paleidimo dienos).
5. **Mastelis.** `SEED_SCALE=1` – pilnas kiekis (MySQL, našumo bandymams), `SEED_SCALE=0.05` – greitas mažas
   seed'as kasdieniam darbui su SQLite.

---

## 2. Kiekiai

Kiekiai **pagrindinėse** lentelėse yra tikslūs (padauginti iš `SEED_SCALE`). Kitose jie išplaukia iš taisyklių,
todėl nurodyti apytiksliai (≈).

| Lentelė                     | `SEED_SCALE=1` | `SEED_SCALE=0.05` | Pastaba                                                          |
| --------------------------- | -------------: | ----------------: | ---------------------------------------------------------------- |
| `regions`                   |             10 |                10 | vieši administraciniai duomenys (ne asmens)                      |
| `cities`                    |             60 |                60 | vieši administraciniai duomenys (ne asmens)                      |
| `categories`                |  12 / 49 / 190 |          tiek pat | **mūsų** sugalvotas medis (`database/data/categories.php`)       |
| `credit_packages`           |              4 |                 4 |                                                                  |
| `subscription_plans`        |              3 |                 3 |                                                                  |
| `users` – administratoriai  |              3 |                 3 |                                                                  |
| `users` – klientai          |     **60 000** |             3 000 |                                                                  |
| `users` – teikėjai          |     **20 000** |             1 000 |                                                                  |
| `provider_profiles`         |     **20 000** |             1 000 |                                                                  |
| `category_provider_profile` |      ≈ 104 700 |           ≈ 5 300 | vid. 5–6 kategorijos teikėjui (15 % – viena visa 2 lygio)        |
| `city_provider_profile`     |       ≈ 69 800 |           ≈ 3 500 | vid. 4 savivaldybės; 10 % – „visa Lietuva" (be eilučių)          |
| `portfolio_items`           |       ≈ 53 900 |           ≈ 2 750 | 60 % teikėjų turi 1–8 darbus                                     |
| `service_requests`          |    **100 000** |             5 000 |                                                                  |
| `offers`                    |    **300 000** |            15 000 |                                                                  |
| `conversations`             |         80 000 |             4 000 | 68 000 priimtų + 12 000 su klausimu                              |
| `conversation_user`         |        160 000 |             8 000 | po 2 dalyvius                                                    |
| `messages`                  |    **200 000** |            10 000 |                                                                  |
| `reviews`                   |    **100 000** |             5 000 | 55 800 patvirtintų + 44 200 pakvietimo (žr. 4 sk.)               |
| `subscriptions`             |        ≈ 2 800 |             ≈ 130 | 15 % teikėjų, ≈ pusė aktyvios dabar                              |
| `payments`                  |       ≈ 30 900 |           ≈ 1 550 | kreditų paketai + prenumeratų laikotarpiai                       |
| `credit_transactions`       |      ≈ 368 000 |          ≈ 18 600 | po vieną kiekvienam pasiūlymui + pirkimai, dovanos, prenumeratos |
| `notifications`             |      ≈ 230 000 |          ≈ 10 500 | tik paskutinių 60 dienų įvykiai                                  |
| `complaints`                |          3 000 |               150 |                                                                  |
| `media`                     |              0 |                 0 | lentelė sukurta Etape 3; demo nuotraukų dar nėra (`SEED_MEDIA`)  |
| **Iš viso**                 | **≈ 1,9 mln.** |          ≈ 95 000 |                                                                  |

Pranešimų išėjo daugiau nei planuota (≈ 100 000): paskutinėmis 60 dienomis yra visos atviros ir vykdomos
užklausos, todėl ir pasiūlymų bei žinučių apie jas daug. Tai realistiška, todėl palikom.

---

## 3. Generavimo eiliškumas

Tvarka atitinka FK priklausomybes: tėvas visada sukuriamas anksčiau nei vaikas.

**Žinyniniai duomenys** (`DatabaseSeeder`, visada pilni, nepriklausomai nuo `SEED_SCALE`):

| #   | Seeder                                          | Ką daro                                                                                  |
| --- | ----------------------------------------------- | ---------------------------------------------------------------------------------------- |
| 1   | `GeographySeeder`                               | apskritys ir savivaldybės iš `database/data/cities.php` (su vietininku ir koordinatėmis) |
| 2   | `CategorySeeder`                                | 3 lygių medis iš `database/data/categories.php` (su `offer_cost_credits`)                |
| 3   | `CreditPackageSeeder`, `SubscriptionPlanSeeder` | 4 paketai, 3 planai iš `database/data/monetization.php`                                  |
| 4   | `AdminSeeder`                                   | 3 administratoriai: `admin1@example.test`…                                               |

Visi jie **idempotentiški** (`updateOrCreate` pagal slug ar el. paštą): paleidus antrą kartą nieko nedubliuoja.

**Demo duomenys** (`DemoDataSeeder`, tik kai `SEED_DEMO=true`). Vietoj 12 atskirų seeder'ių – vienas seeder'is ir
generatorių klasės `database/seeders/Demo/`. Jis dirba dviem fazėmis:

1. **Planavimas atmintyje.** Kas, kada ir kam – be DB. Taip galima, pavyzdžiui, teikėjo registracijos datą
   parinkti _pagal jo pirmą pasiūlymą_, o kliento – pagal pirmą užklausą.
2. **Įrašymas FK tvarka** masiniais `INSERT`.

| #   | Klasė                     | Ką daro                                                                                                       |
| --- | ------------------------- | ------------------------------------------------------------------------------------------------------------- |
| 1   | `DemoContext`             | bendra būsena (skaičių masyvai), žinyniniai duomenys, atsitiktinumas, tekstai, `insert()` dalimis             |
| 2   | `ProviderGenerator`       | teikėjai: statusas, miestas, zonos, kategorijos, aktyvumas (Pareto); paskyros, profiliai, pivot'ai, portfolio |
| 3   | `ClientGenerator`         | klientai; registracijos data – prieš pirmą užklausą                                                           |
| 4   | `ServiceRequestGenerator` | užklausos: tikslūs statusų kiekiai, datos pagal statusą, kategorija ir miestas su tinkamais teikėjais         |
| 5   | `OfferGenerator`          | pasiūlymai tik tinkamų teikėjų, statusai ir peržiūros, priėmimas, grąžinimai, `accepted_offer_id`             |
| 6   | `ConversationGenerator`   | pokalbiai, dalyviai ir žinutės (pakaitomis, 8–21 val.)                                                        |
| 7   | `ReviewGenerator`         | patvirtinti ir pakvietimo atsiliepimai                                                                        |
| 8   | `MonetizationGenerator`   | prenumeratos, mokėjimai ir kreditų ledger – chronologiškai kiekvienam teikėjui                                |
| 9   | `NotificationGenerator`   | pranešimai apie paskutinių 60 dienų įvykius                                                                   |
| 10  | `ComplaintGenerator`      | skundai                                                                                                       |
| 11  | `CounterSync`             | perskaičiuoja denormalizuotus skaitliukus (`DB_SCHEMA.md` 2.10) vienu `UPDATE` lentelei                       |
| –   | `WeightedPicker`          | svertinis atsitiktinis parinkimas (miestai pagal gyventojus, kategorijos pagal populiarumą)                   |

Paveikslėlių (`MediaSeeder`, `SEED_MEDIA`) dar nėra: `media` lentelė sukurta Etape 3, o demo nuotraukos – vėliau.

---

## 4. Realistiškumo taisyklės

### Vartotojai

- Registracijos datos išdėstomos per 36 mėnesius. Platforma „auga", todėl naujesnių paskyrų daugiau.
- Vardai ir pavardės – Faker `lt_LT`, su teisinga gimine (≈ 50/50).
- Klientas visada užsiregistruoja **prieš** savo pirmą užklausą.
- Užklausų skaičius klientui: 1 (60 %), 2 (25 %), 3 (10 %), 4–6 (5 %). Galutinis skaičius pakoreguojamas,
  kad bendra suma būtų lygiai 100 000.

### Teikėjai

- Tipas: fizinis asmuo 65 %, įmonė 35 %.
- Statusas: `active` 92 %, `pending` 4 %, `hidden` 3 %, `suspended` 1 %. Patikrinti (`verified_at`) – 30 %.
- **Kategorijos:** pasirenkama viena 1 lygio sritis (pagal populiarumą), joje 2–10 kategorijų. 15 % teikėjų
  pasirenka visą 2 lygio kategoriją.
- **Bazinis miestas** parenkamas pagal gyventojų svorį: daugiausia Vilniuje, paskui Kaune, Klaipėdoje ir t.t.
- **Zonos:** bazinė savivaldybė + 0–6 tos pačios apskrities savivaldybės. 10 % teikėjų – „visa Lietuva".
- **Aktyvumas** pasiskirsto pagal Pareto: 20 % aktyviausių teikėjų išsiunčia ≈ 60 % visų pasiūlymų.

### Užklausos

- `published_at` išdėstoma per 24 mėnesius, naujesnių daugiau.
- Kategorija (visada 3 lygio) parenkama pagal populiarumą. Miestas 85 % atvejų sutampa su kliento miestu.
- Biudžetas nurodytas 60 % užklausų, diapazonas pagal kategoriją (`categories.php`).
- Statusai ir datos turi derėti tarpusavyje:

| Statusas      | Kiekis (scale 1) | Datos taisyklė                                         |
| ------------- | ---------------: | ------------------------------------------------------ |
| `completed`   |           62 000 | `completed_at` = priėmimas + 1–30 d.                   |
| `in_progress` |            6 000 | publikuota prieš 5–60 d.                               |
| `open`        |           12 000 | publikuota per paskutines 30 d., `expires_at` ateityje |
| `expired`     |           11 000 | `expires_at` praeityje, niekas nepriimta               |
| `cancelled`   |            8 000 | `cancelled_at` po publikavimo                          |
| `pending`     |            1 000 | sukurta per paskutines 2 d., `published_at = NULL`     |

### Pasiūlymai

- Pasiūlymą siunčia **tik tinkamas teikėjas**: kategorija sutampa (įskaitant tėvus), miestas yra jo zonoje
  (arba jis aptarnauja visą Lietuvą), statusas `active`. Tai ta pati taisyklė kaip programoje.
- Pasiskirstymas pagal užklausos statusą: `pending` – 0; `open` – 0–8; `in_progress` ir `completed` – 1–10,
  iš jų lygiai 1 `accepted`; `expired` ir `cancelled` – 0–6. Bendra suma pakoreguojama iki lygiai 300 000.
- Statusai: 68 000 `accepted` (po vieną `in_progress`/`completed` užklausai), atvirų užklausų pasiūlymai –
  `pending`, likę – `declined` arba `withdrawn`.
- `created_at` – nuo 10 min. iki 5 d. po publikavimo (dauguma per pirmą parą).
- `credits_spent` = užklausos kategorijos `offer_cost_credits` (dažniausiai 1, brangiems darbams 2–3).

### Pokalbiai ir žinutės

- Pokalbis sukuriamas visiems priimtiems pasiūlymams (68 000) ir ≈ 12 000 kitų, kur klientas uždavė klausimą.
- Žinučių skaičius: priimtiems – 2–5, kitiems – 1–3. Bendra suma pakoreguojama iki lygiai 200 000.
- Siuntėjai keičiasi pakaitomis, laikas tik didėja, žinutės siunčiamos 8–21 val. Lietuvos laiku.
  `last_message_at` sutampa su paskutine žinute.
- Senesni pokalbiai perskaityti (`last_read_message_id` = paskutinė žinutė), naujausiuose dalis žinučių neperskaityta.

### Atsiliepimai

- 100 000 atsiliepimų yra daugiau nei 62 000 atliktų užklausų, o vienai užklausai leidžiamas tik vienas
  atsiliepimas. Todėl:
    - ≈ 90 % atliktų užklausų gauna **patvirtintą** atsiliepimą (≈ 55 800). Jo autorius – užklausos klientas,
      teikėjas – priimto pasiūlymo teikėjas, data – 0–14 d. po `completed_at`;
    - likę ≈ 44 200 – **pakvietimo** atsiliepimai (`service_request_id = NULL`), jų autorius – atsitiktinis klientas.
    - Santykis yra parametras (9 sk.). Jei norėsi daugiau patvirtintų, galima didinti `completed` dalį.
- Įvertinimai: 5★ 62 %, 4★ 22 %, 3★ 7 %, 2★ 3 %, 1★ 6 % (tipiškas „J" formos pasiskirstymas).
- 20 % atsiliepimų turi teikėjo atsakymą. Statusai: `published` 97 %, `hidden` 2 %, `pending` 1 %.

### Kreditai, mokėjimai, prenumeratos

- Kiekvienas naujas teikėjas gauna dovanų 5 kreditus (`bonus`).
- 15 % teikėjų bent kartą turėjo prenumeratą, vidutiniškai 4 laikotarpius. Kiekvienas laikotarpis = mokėjimas
    - `subscription` kreditų įrašas.
- Ledger generuojamas **chronologiškai kiekvienam teikėjui**. Einama per jo pasiūlymus laiko tvarka; jei
  balanso neužtenka, prieš pasiūlymą įterpiamas paketo pirkimas (mokėjimas `paid` + `purchase` įrašas).
  Taip `balance_after` niekada nebūna neigiamas, o pabaigoje `credits_balance` lygus paskutiniam `balance_after`.
- 3 % mokėjimų – `failed` arba `cancelled` (be kreditų).
- **Etapas 7:** prenumeratos laikotarpio mokėjimas turi `subscription_id`, o prenumeratos
  `credits_granted_until = ends_at` – kreditai už visus laikotarpius jau įrašyti, todėl Scheduler'is
  (`subscriptions:grant-credits`) jų antrą kartą nesuteiks. Sąskaitų numeriai `SF-{metai}-{mokėjimo ID}`;
  naujų mokėjimų numeracija tęsiama nuo didžiausio tų metų numerio (`invoice_sequences`).
- Kreditų grąžinimai (`refund`) generuojami pagal `docs/STATES.md` 3 sk.: atšauktų atvirų užklausų `pending`
  pasiūlymams ir pasibaigusių užklausų pasiūlymams, kurių klientas neatidarė.

### Pranešimai ir skundai

- Pranešimai kuriami tik paskutinių 60 dienų įvykiams (`NewOffer`, `OfferAccepted`, `NewMessage`, `NewReview`,
  `NewMatchingRequest` – iki 20 teikėjų vienai užklausai). 70 % perskaityti.
- Skundai: 50 % dėl atsiliepimų, 25 % dėl užklausų, 15 % dėl žinučių, 10 % dėl profilių.
  Statusai: `resolved` 60 %, `rejected` 25 %, `open` 10 %, `in_review` 5 %.

---

## 5. Lietuviški tekstai ir Faker lt_LT

### Ką moka Faker `lt_LT` (patikrinta su fakerphp/faker 1.24)

| Metodas                                                | Pavyzdys                                     | Naudosim?                                                                     |
| ------------------------------------------------------ | -------------------------------------------- | ----------------------------------------------------------------------------- |
| `firstName('male')`, `lastName('male')` / `('female')` | Jokimas Stankevičius, Olivija Kavaliauskienė | ✔ vardai su teisinga gimine (pavardės -ienė, -ytė, -ūtė)                      |
| `company()`                                            | MB Pocius ir Urbonas, AB "Kateiva"           | ✔ įmonių pavadinimai                                                          |
| `streetAddress()`                                      | 93 Pranciškus gatvė                          | ✔ gatvės akivaizdžiai netikros (vardai vietoj gatvių)                         |
| `city()`                                               | Tauragė                                      | ✗ miestus imam iš savo `cities`                                               |
| `phoneNumber()`                                        | +370 660 26 652                              | ✗ formatas tikras, todėl numeris gali priklausyti realiam žmogui (6 sk.)      |
| `safeEmail()`                                          | zilvinas50@example.com                       | ✗ naudojam deterministinius `…@example.test` (greičiau, garantuotai unikalūs) |
| `personalIdentityNumber()`                             | asmens kodas                                 | ✗ **draudžiama**. Asmens kodų nesaugom ir negeneruojam.                       |
| `realText()`, `sentence()`, `paragraph()`              | angliškas / lotyniškas tekstas               | ✗ `lt_LT` neturi teksto tiekėjo                                               |

Laravel'yje lokalė nustatoma `.env` faile: `APP_FAKER_LOCALE=lt_LT`. Tada `fake()->firstName()` jau duoda lietuviškus vardus.

### Savi tekstų bankai

Lietuviškiems sakiniams darom šablonų bankus `database/data/texts/*.php`. Kiekviena 1 lygio sritis turi savo
frazes, kurios jungiamos atsitiktinai su vietos rezervavimo laukais (`{paslauga}`, `{miestas}` – vietininkas,
`{plotas}`, `{kiekis}`, `{patalpa}`, `{terminas}`, `{metai}`). Bankai kol kas nedideli (užklausoms – 7 pavadinimai,
6 pradžios, po 5 detales kiekvienai sričiai, 4 medžiagų, 4 terminų ir 5 pabaigų frazės), bet su 190 paslaugų
ir 60 savivaldybių pavadinimais tai jau tūkstančiai skirtingų tekstų. Bankus galima pildyti nekeičiant kodo.
Kreipinys su kableliu („Sveiki,") atskiriamas nauja eilute, kaip laiške.

| Bankas                           | Pavyzdžiai                                                                                                                                             |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `requests.php` – pavadinimai     | „Plytelių klijavimas Kaune", „Reikia pakeisti maišytuvą virtuvėje"                                                                                     |
| `requests.php` – aprašymai       | „Reikia suklijuoti plyteles vonios kambaryje, plotas apie {plotas} m²." · „Medžiagas turiu, reikia tik darbo." · „Darbus norėčiau pradėti {terminas}." |
| `offers.php`                     | „Laba diena, galiu atvykti apžiūrėti jau šią savaitę." · „Kaina galutinė, įskaitant medžiagų atvežimą."                                                |
| `messages.php`                   | „Ar galėtumėte atvykti rytoj po 17 val.?" · „Taip, tinka. Iki pasimatymo!"                                                                             |
| `reviews.php` (pagal įvertinimą) | 5★ „Puikiai atliktas darbas, labai rekomenduoju!" · 1★ „Vėlavo dvi dienas, darbas liko nebaigtas."                                                     |
| `providers.php`                  | antraštės ir aprašymai: „{Paslauga} {miestas_vietininkas}, {metai} m. patirtis"                                                                        |

Linksniai: vietininką imam iš `cities.name_locative` (Vilniuje, Kaune, Vilniaus rajone).
Kategorijų pavadinimai rašomi vardininku, todėl tinka pavadinimams („Plytelių klijavimas Kaune").

---

## 6. Jokių tikrų duomenų

| Duomuo                      | Taisyklė                                                                                                                                                                                  |
| --------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| El. paštas                  | `klientas{n}@example.test`, `teikejas{n}@example.test`, `admin{n}@example.test`. `.test` domenas rezervuotas (RFC 2606), todėl laiškas niekada nepasieks žmogaus.                         |
| Telefonas                   | `+3700` + 7 skaitmenys (pvz. `+37000001234`). Lietuvos numeris po `+370` niekada neprasideda `0`, todėl toks numeris negali priklausyti realiam žmogui. Faker `phoneNumber()` nenaudojam. |
| Asmens kodai                | negeneruojam ir nesaugom (BDAR, be to, mums jų nereikia)                                                                                                                                  |
| Įmonės kodas                | `999` + 6 skaitmenys, PVM kodas `LT999…`, kad iš karto būtų matyti, jog netikri                                                                                                           |
| Vardai, pavardės            | Faker deriniai. Atsitiktinai jie gali sutapti su realiu asmeniu, bet nesiejami su jokiais tikrais duomenimis.                                                                             |
| Įmonių pavadinimai          | Faker `company()`, kurie paremti dažnomis pavardėmis. Taisyklė ta pati.                                                                                                                   |
| Adresai                     | Faker `streetAddress()` (netikros gatvės) + mūsų savivaldybė                                                                                                                              |
| Svetainės                   | `https://{slug}.example.test`                                                                                                                                                             |
| Nuotraukos                  | tik mūsų sugeneruoti abstraktūs paveikslėliai iš `database/data/images/`, be žmonių. Iš interneto nieko nesiunčiam.                                                                       |
| Slaptažodžiai               | visiems `password` (**tik dev!**)                                                                                                                                                         |
| Apskritys, savivaldybės     | tikri **vieši administraciniai** duomenys. Tai ne asmens duomenys, ir be jų svetainė neveiktų.                                                                                            |
| Kategorijos, kainos, planai | sugalvoti mūsų                                                                                                                                                                            |

---

## 7. Greitis: kaip įterpti ~1,8 mln. eilučių

Jei kiekvieną įrašą kurtume per `Model::create()`, tai užtruktų valandas. Kodėl ir ką darom vietoj to:

| Problema                                                                  | Sprendimas                                                                                                                                               |
| ------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Model::create()` = 1 SQL užklausa + model events kiekvienai eilutei      | **masinis įterpimas**: `DB::table('offers')->insert($rows)` po 1 000 eilučių (`SEED_CHUNK`)                                                              |
| `Hash::make()` (bcrypt) užtrunka ~50–100 ms. 80 000 kartų – virš valandos | slaptažodžio hash'as **suskaičiuojamas vieną kartą** ir naudojamas visiems                                                                               |
| Observeriai ir pranešimai seed'o metu išsiųstų 300 000 laiškų             | `use WithoutModelEvents;` `DatabaseSeeder` klasėje (jei skeletone jo dar nėra, pridedam). → https://laravel.com/docs/13.x/seeding#muting-model-events    |
| SQLite be transakcijos kiekvieną INSERT rašo į diską atskirai             | kiekviena dalis (chunk) įterpiama `DB::transaction()` viduje                                                                                             |
| Kad žinotume naujų įrašų ID, reikėtų `SELECT`                             | **ID priskiriam patys** (1…N iš eilės). Taip ryšius galima sudėlioti atmintyje be papildomų užklausų. Veikia, nes seed'inam tuščią DB (`migrate:fresh`). |
| 300 000 Eloquent objektų netelpa į atmintį                                | atmintyje laikom tik skaičių masyvus (pvz. „kategorija → teikėjų ID"), o eilutes generuojam dalimis                                                      |
| Skaitliukų atnaujinimas po vieną eilutę                                   | pabaigoje vienas `UPDATE … SET reviews_count = (SELECT COUNT(*) …)` visai lentelei (`CounterSyncSeeder`)                                                 |
| `fake()->unique()` 80 000 kartų lėtėja ir gali „išsekti"                  | unikalumui naudojam eilės numerį (`teikejas{n}@…`)                                                                                                       |
| SQLite riboja kintamųjų skaičių vienoje užklausoje                        | 1 000 eilučių × ~16 stulpelių ≈ 16 000 < 32 766 (SQLite ≥ 3.32 riba)                                                                                     |

**Factory ar tiesioginis eilučių generavimas?** Factory – vieno įrašo receptas: testuose kviečiamas
`ServiceRequest::factory()->completed()->create()`. Planavome dideliame seed'e naudoti `factory()->raw()`, bet
įgyvendinant pasirinkom generuoti eilutes tiesiogiai generatoriuose (`yield [...]`):

- beveik visi laukai vis tiek priklauso nuo plano (ID, datos, statusai, ryšiai), todėl factory reikšmes reikėtų
  perrašyti, o `raw()` dar ir sukurtų tėvinius įrašus FK laukams, apibrėžtiems kaip `User::factory()`;
- `raw()` nepritaiko cast'ų (enum'ą reikia paversti `->value`, masyvą – `json_encode()`), o generatoriuje tai
  matosi iš karto;
- generatorius (`yield`) grąžina po vieną eilutę, todėl 300 000 eilučių vienu metu atmintyje nėra.

Factories liko testams. → https://laravel.com/docs/13.x/eloquent-factories · https://www.php.net/manual/en/language.generators.overview.php

**Atkartojamumas.** Atsitiktinumą valdo `fake()->seed(SEED_FAKER_SEED)` ir `mt_srand(SEED_FAKER_SEED)`.
Seeder'iuose nenaudojam `random_int()` ir `Str::random()`, nes jie kriptografiški ir jų „užsėti" negalima.

**Išmatuotas laikas** (tikslas buvo: pilnas seed'as MySQL ≤ 10 min., `SEED_SCALE=0.05` SQLite ≤ 1 min.):

| DB                             | `SEED_SCALE` | Demo duomenys | Visas `migrate:fresh --seed` | Atminties pikas |
| ------------------------------ | -----------: | ------------: | ---------------------------: | --------------: |
| SQLite                         |         0.05 |         2,2 s |                        4,4 s |           70 MB |
| SQLite                         |            1 |          50 s |                         52 s |          365 MB |
| MySQL 8.0, numatyti nustatymai |            1 |         147 s |                      2,5 min |          365 MB |

Matuota Claude cloud konteineryje. MySQL – 8.0 iš Ubuntu saugyklos (8.4 LTS ten nėra), be jokio derinimo, todėl
produkcijos serveryje bus panašiai arba greičiau. Lėčiausios vietos – lentelės su daugiausia indeksų
(`service_requests`, `offers`, `notifications`): kiekvienas indeksas – papildomas įrašas kiekvienai eilutei.

**SQLite pagreitinimas seed'o metu:** `PRAGMA synchronous = OFF` (nelaukti disko po kiekvieno įrašo) ir
`PRAGMA cache_size` (256 MB DB puslapių atmintyje). Be jų pilnas seed'as truko 71 s. Seed'ui patikimumas
nesvarbus – jei kompiuteris užlūš, DB vis tiek kuriama iš naujo. `memory_limit` seed'o metu pakeliamas iki 2 GB.

**`SEED_MEDIA=true`** (planuojama; `media` lentelė jau yra nuo Etapo 3): prie ≈ 1 000 portfolio darbų ir ≈ 500 profilių
prisegami paveikslėliai iš `database/data/images/`. Pagal nutylėjimą išjungta: medialibrary kiekvienam įrašui
kopijuoja failą ir daro miniatiūras, o tai lėta ir užima vietos.

---

## 8. Patikrinimai po seed'inimo

Pest testas `tests/Feature/Seeding/SeedIntegrityTest.php`, paleidžiamas su `SEED_SCALE=0.01` (praeina ir su SQLite, ir su MySQL).

- [x] Pagrindinių lentelių kiekiai = tiksliniai × `SEED_SCALE`.
- [x] Kiekvieno pasiūlymo teikėjas tinka užklausai (kategorija su tėvais + zona arba „visa Lietuva", `active`).
- [x] Kiekviena `in_progress`/`completed` užklausa turi `accepted_offer_id`. Tas pasiūlymas yra `accepted` ir
      priklauso tai pačiai užklausai. Kitos užklausos `accepted_offer_id` neturi.
- [x] `open` užklausų `expires_at` ateityje, `pending` – `published_at IS NULL`.
- [x] Patvirtintas atsiliepimas: užklausa `completed`, autorius = užklausos klientas, teikėjas = priimto
      pasiūlymo teikėjas, sukurtas po `completed_at`.
- [x] Kiekvienam teikėjui `SUM(amount) = credits_balance`, joks `balance_after` nėra neigiamas.
- [x] `refund` įrašai yra tik ten, kur juos leidžia `docs/STATES.md` 3 sk., ir už vieną pasiūlymą – ne daugiau kaip vienas.
- [x] `rating_avg`, `reviews_count`, `completed_jobs_count`, `offers_count`, `last_message_at` sutampa su perskaičiuotais.
- [x] Žinutės siuntėjas yra pokalbio dalyvis, žinučių laikas didėja.
- [x] Visi el. paštai baigiasi `@example.test`, visi telefonai prasideda `+3700`.

---

## 9. Nustatymai ir komandos

| `.env` kintamasis            | Numatyta | Ką reiškia                                                 |
| ---------------------------- | -------- | ---------------------------------------------------------- |
| `SEED_SCALE`                 | `1`      | kiekių daugiklis; `.env.example` – `0.05` greitam dev'ui   |
| `SEED_CHUNK`                 | `1000`   | kiek eilučių įterpiama vienu INSERT                        |
| `SEED_FAKER_SEED`            | `2026`   | atsitiktinumo „sėkla" atkartojamumui                       |
| `SEED_DEMO`                  | `true`   | ar kurti demo duomenis (`false` – tik žinyniniai)          |
| `SEED_MEDIA`                 | `false`  | ar prisegti paveikslėlius (Etapas 3)                       |
| `SEED_VERIFIED_REVIEW_RATIO` | `0.9`    | kokia dalis atliktų užklausų gauna patvirtintą atsiliepimą |

Kintamieji skaitomi per `config/seeding.php`. Testuose (`phpunit.xml`) – `SEED_DEMO=false`, `SEED_SCALE=0.01`. Kode niekada nekviečiam `env()` tiesiogiai už config failų ribų,
nes po `php artisan config:cache` `env()` grąžina `null`.

```bash
php artisan migrate:fresh --seed                     # pilnas seed'as (MySQL)
SEED_SCALE=0.05 php artisan migrate:fresh --seed     # mažas ir greitas (SQLite dev)
SEED_DEMO=false php artisan migrate:fresh --seed     # tik žinyniniai duomenys
php artisan db:seed --class=CategorySeeder           # vienas seeder'is (kai priklausomybės jau yra)
php artisan db:show --counts                         # visos lentelės su eilučių skaičiumi
php artisan db:table service_requests                # lentelės stulpeliai, indeksai, FK
```
