# Seed'ų (testinių duomenų) planas

> **Etapas 0 · būsena: planas.** Seeder'ius rašysim Etape 2 pagal šį dokumentą ir `docs/DB_SCHEMA.md`.

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

| Lentelė                     |         `SEED_SCALE=1` | `SEED_SCALE=0.05` | Pastaba                                                          |
| --------------------------- | ---------------------: | ----------------: | ---------------------------------------------------------------- |
| `regions`                   |                     10 |                10 | vieši administraciniai duomenys (ne asmens)                      |
| `cities`                    |                     60 |                60 | vieši administraciniai duomenys (ne asmens)                      |
| `categories`                |        ≈ 12 / 80 / 450 |          tiek pat | **mūsų** sugalvotas medis (ranka rašytas failas)                 |
| `credit_packages`           |                      4 |                 4 |                                                                  |
| `subscription_plans`        |                      3 |                 3 |                                                                  |
| `users` – administratoriai  |                      3 |                 3 |                                                                  |
| `users` – klientai          |             **60 000** |             3 000 |                                                                  |
| `users` – teikėjai          |             **20 000** |             1 000 |                                                                  |
| `provider_profiles`         |             **20 000** |             1 000 |                                                                  |
| `category_provider_profile` |              ≈ 120 000 |           ≈ 6 000 | vidutiniškai 6 kategorijos teikėjui                              |
| `city_provider_profile`     |               ≈ 80 000 |           ≈ 4 000 | vid. 4–5 savivaldybės; 10 % – „visa Lietuva" (be eilučių)        |
| `portfolio_items`           |               ≈ 50 000 |           ≈ 2 500 | 60 % teikėjų turi 1–8 darbus                                     |
| `service_requests`          |            **100 000** |             5 000 |                                                                  |
| `offers`                    |            **300 000** |            15 000 |                                                                  |
| `conversations`             |               ≈ 80 000 |           ≈ 4 000 |                                                                  |
| `conversation_user`         |              ≈ 160 000 |           ≈ 8 000 | po 2 dalyvius                                                    |
| `messages`                  |            **200 000** |            10 000 |                                                                  |
| `reviews`                   |            **100 000** |             5 000 | ≈ 55 800 patvirtintų + ≈ 44 200 pakvietimo (žr. 4 sk.)           |
| `subscriptions`             |                ≈ 3 000 |             ≈ 150 | 15 % teikėjų, ≈ pusė aktyvios dabar                              |
| `payments`                  |               ≈ 27 000 |           ≈ 1 350 | kreditų paketai + prenumeratų laikotarpiai                       |
| `credit_transactions`       |              ≈ 350 000 |          ≈ 17 500 | po vieną kiekvienam pasiūlymui + pirkimai, dovanos, prenumeratos |
| `notifications`             |              ≈ 100 000 |           ≈ 5 000 | tik paskutinių 60 dienų                                          |
| `complaints`                |                ≈ 3 000 |             ≈ 150 |                                                                  |
| `media`                     |                      0 |                 0 | tik su `SEED_MEDIA=true` (7 sk.)                                 |
| **Iš viso**                 | **≈ 1,8 mln. eilučių** |          ≈ 90 000 |                                                                  |

---

## 3. Generavimo eiliškumas

Tvarka atitinka FK priklausomybes: tėvas visada sukuriamas anksčiau nei vaikas.

| #   | Seeder                                          | Ką daro                                                                                                                               |
| --- | ----------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------- |
| 1   | `RegionSeeder`, `CitySeeder`                    | apskritys ir savivaldybės iš `database/data/cities.php` (su vietininku, koordinatėmis ir gyventojų „svoriu" atsitiktiniam parinkimui) |
| 2   | `CategorySeeder`                                | 3 lygių medis iš `database/data/categories.php` (su populiarumo svoriu ir `offer_cost_credits`)                                       |
| 3   | `CreditPackageSeeder`, `SubscriptionPlanSeeder` | 4 paketai, 3 planai (kainos sugalvotos)                                                                                               |
| 4   | `AdminSeeder`                                   | 3 administratoriai: `admin1@example.test`…                                                                                            |
| 5   | `ClientSeeder`                                  | 60 000 klientų                                                                                                                        |
| 6   | `ProviderSeeder`                                | 20 000 teikėjų paskyrų + `provider_profiles` + kategorijų ir zonų pivot'ai                                                            |
| 7   | `PortfolioSeeder`                               | portfolio darbai                                                                                                                      |
| 8   | `ServiceRequestSeeder`                          | 100 000 užklausų (statusai, datos)                                                                                                    |
| 9   | `OfferSeeder`                                   | 300 000 pasiūlymų. Kiekvienai `in_progress`/`completed` užklausai nustato `accepted_offer_id`                                         |
| 10  | `ConversationSeeder`                            | pokalbiai, dalyviai ir 200 000 žinučių                                                                                                |
| 11  | `ReviewSeeder`                                  | 100 000 atsiliepimų                                                                                                                   |
| 12  | `MonetizationSeeder`                            | prenumeratos, mokėjimai ir kreditų ledger (chronologiškai kiekvienam teikėjui)                                                        |
| 13  | `NotificationSeeder`                            | pranešimai apie paskutinių 60 dienų įvykius                                                                                           |
| 14  | `ComplaintSeeder`                               | skundai                                                                                                                               |
| 15  | `CounterSyncSeeder`                             | perskaičiuoja visus denormalizuotus skaitliukus (`DB_SCHEMA.md` 2.10)                                                                 |
| 16  | `MediaSeeder` (nebūtinas)                       | tik su `SEED_MEDIA=true`                                                                                                              |

Žinyniniai duomenys (1–3) visada sukuriami pilni, nepriklausomai nuo `SEED_SCALE`.

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
frazes, kurios jungiamos atsitiktinai su vietos rezervavimo laukais (`{plotas}`, `{kiekis}`, `{terminas}`,
`{miestas_vietininkas}`). Iš 20 pradžių × 30 detalių × 15 pabaigų gaunama tūkstančiai skirtingų tekstų.

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

**Factory ir masinio seed'o derinimas.** Factory – vieno įrašo receptas: testuose kviečiamas
`ServiceRequest::factory()->completed()->create()`. Dideliame seed'e naudojam tą patį receptą per
`ServiceRequest::factory()->raw([...])`: gaunam masyvą, kuris nieko nerašo į DB, ir įterpiam masiškai.
Svarbu žinoti:

- `raw()` **nepritaiko cast'ų**, todėl enum'ą reikia paversti `->value`, masyvą – `json_encode()`, datą – eilute;
- FK laukus, kurie factory apibrėžti kaip `User::factory()`, **būtina perrašyti** konkrečiu ID. Kitaip `raw()`
  sukurs papildomus tėvinius įrašus.
  → https://laravel.com/docs/13.x/eloquent-factories#factory-states

**Atkartojamumas.** Atsitiktinumą valdo `fake()->seed(SEED_FAKER_SEED)` ir `mt_srand(SEED_FAKER_SEED)`.
Seeder'iuose nenaudojam `random_int()` ir `Str::random()`, nes jie kriptografiški ir jų „užsėti" negalima.

**Tikslinis laikas** (išmatuosim Etape 2 ir įrašysim čia): pilnas seed'as MySQL ≤ 10 min., `SEED_SCALE=0.05`
SQLite ≤ 1 min.

**`SEED_MEDIA=true`:** prie ≈ 1 000 portfolio darbų ir ≈ 500 profilių prisegami paveikslėliai iš
`database/data/images/`. Pagal nutylėjimą išjungta: medialibrary kiekvienam įrašui kopijuoja failą ir daro
miniatiūras, o tai lėta ir užima vietos.

---

## 8. Patikrinimai po seed'inimo

Etape 2 tai bus Pest testas (`tests/Feature/Seeding/SeedIntegrityTest.php`), paleidžiamas su mažu `SEED_SCALE`.

- [ ] Pagrindinių lentelių kiekiai = tiksliniai × `SEED_SCALE`.
- [ ] Kiekvieno pasiūlymo teikėjas tinka užklausai (kategorija su tėvais + zona arba „visa Lietuva", `active`).
- [ ] Kiekviena `in_progress`/`completed` užklausa turi `accepted_offer_id`. Tas pasiūlymas yra `accepted` ir
      priklauso tai pačiai užklausai. Kitos užklausos `accepted_offer_id` neturi.
- [ ] `open` užklausų `expires_at` ateityje, `pending` – `published_at IS NULL`.
- [ ] Patvirtintas atsiliepimas: užklausa `completed`, autorius = užklausos klientas, teikėjas = priimto
      pasiūlymo teikėjas, sukurtas po `completed_at`.
- [ ] Kiekvienam teikėjui `SUM(amount) = credits_balance`, joks `balance_after` nėra neigiamas.
- [ ] `refund` įrašai yra tik ten, kur juos leidžia `docs/STATES.md` 3 sk., ir už vieną pasiūlymą – ne daugiau kaip vienas.
- [ ] `rating_avg`, `reviews_count`, `completed_jobs_count`, `offers_count`, `last_message_at` sutampa su perskaičiuotais.
- [ ] Žinutės siuntėjas yra pokalbio dalyvis, žinučių laikas didėja.
- [ ] Visi el. paštai baigiasi `@example.test`, visi telefonai prasideda `+3700`.

---

## 9. Nustatymai ir komandos

| `.env` kintamasis            | Numatyta | Ką reiškia                                                 |
| ---------------------------- | -------- | ---------------------------------------------------------- |
| `SEED_SCALE`                 | `1`      | kiekių daugiklis (`0.05` – greitam dev'ui)                 |
| `SEED_CHUNK`                 | `1000`   | kiek eilučių įterpiama vienu INSERT                        |
| `SEED_FAKER_SEED`            | `2026`   | atsitiktinumo „sėkla" atkartojamumui                       |
| `SEED_MEDIA`                 | `false`  | ar prisegti paveikslėlius                                  |
| `SEED_VERIFIED_REVIEW_RATIO` | `0.9`    | kokia dalis atliktų užklausų gauna patvirtintą atsiliepimą |

Kintamieji bus skaitomi per `config/seeding.php`. Kode niekada nekviečiam `env()` tiesiogiai už config failų ribų,
nes po `php artisan config:cache` `env()` grąžina `null`.

```bash
php artisan migrate:fresh --seed                     # pilnas seed'as (MySQL)
SEED_SCALE=0.05 php artisan migrate:fresh --seed     # mažas ir greitas (SQLite dev)
php artisan db:seed --class=CategorySeeder           # vienas seeder'is (kai priklausomybės jau yra)
php artisan db:show --counts                         # visos lentelės su eilučių skaičiumi
php artisan db:table service_requests                # lentelės stulpeliai, indeksai, FK
```
