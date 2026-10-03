# Mokymosi užrašai

Šis failas pildomas **kiekvieno etapo pabaigoje**: kas padaryta ir kodėl, išmoktos sąvokos, naudingos artisan
komandos, dažnos klaidos.

Kaip naudoti:

- Prieš pradėdamas naują etapą, perskaityk ankstesnio etapo skiltį.
- Nuorodos veda į oficialią dokumentaciją (Laravel 13.x).

---

## Etapas 0 – Planavimas: DB schema ir seed'ų planas

### Ką darėm ir kodėl

Prieš rašydami kodą suprojektavom duomenų bazę (`docs/DB_SCHEMA.md`), testinių duomenų planą (`docs/SEEDING.md`)
ir susitarėm dėl taisyklių (`CLAUDE.md`). DB schemą vėliau keisti brangiausia: pasikeitus stulpeliui, reikia
migracijos, modelio, formų, testų ir duomenų perkėlimo. Todėl ją apgalvojam pirmiausia.

### Išmoktos sąvokos

#### 1. Migracija

PHP klasė su metodais `up()` (ką sukurti ar pakeisti) ir `down()` (kaip atšaukti). Migracijos saugomos Git'e,
todėl kiekvienas programuotojas ir serveris gauna tą pačią DB struktūrą viena komanda `php artisan migrate`.
WordPress analogas – `dbDelta()` įskiepio aktyvavimo metu, tik su versijomis ir atšaukimu.
Migracijų tvarka svarbi: FK gali rodyti tik į jau sukurtą lentelę (`DB_SCHEMA.md` 7 sk.).
→ https://laravel.com/docs/13.x/migrations

#### 2. Eloquent ryšiai

Ryšys – modelio metodas, kuris aprašo, kaip lentelės susijusios. Tada rašom `$request->offers`, o ne SQL JOIN.

| Ryšys                                  | Mūsų pavyzdys                                                | Kur yra FK                                     | Dokumentacija                                                                                 |
| -------------------------------------- | ------------------------------------------------------------ | ---------------------------------------------- | --------------------------------------------------------------------------------------------- |
| `hasOne` / `belongsTo` (1:1)           | `User` → `providerProfile`                                   | `provider_profiles.user_id`                    | [one-to-one](https://laravel.com/docs/13.x/eloquent-relationships#one-to-one)                 |
| `hasMany` (1:N)                        | `ProviderProfile` → `offers`                                 | `offers.provider_profile_id`                   | [one-to-many](https://laravel.com/docs/13.x/eloquent-relationships#one-to-many)               |
| `belongsTo` (N:1, atvirkštinis)        | `ServiceRequest` → `category`                                | `service_requests.category_id`                 | [inverse](https://laravel.com/docs/13.x/eloquent-relationships#one-to-many-inverse)           |
| `belongsToMany` (N:M per pivot)        | `ProviderProfile` ↔ `categories` (+ `price_from_cents`)      | `category_provider_profile`                    | [many-to-many](https://laravel.com/docs/13.x/eloquent-relationships#many-to-many)             |
| `hasManyThrough`                       | `User` → `offers` per `ProviderProfile`                      | –                                              | [has-many-through](https://laravel.com/docs/13.x/eloquent-relationships#has-many-through)     |
| `morphTo` / `morphMany` (polimorfinis) | `Complaint` → `reportable` (atsiliepimas, žinutė, profilis…) | `complaints.reportable_type` + `reportable_id` | [polymorphic](https://laravel.com/docs/13.x/eloquent-relationships#polymorphic-relationships) |
| Ryšys su savimi                        | `Category` → `parent` / `children`                           | `categories.parent_id`                         | `belongsTo`/`hasMany` į tą patį modelį                                                        |

Taisyklė: FK stulpelis visada yra **„daug" pusės** lentelėje (`belongsTo` pusėje).
Kai FK pavadinimas nestandartinis, jį nurodom: `belongsTo(User::class, 'client_id')`.

#### 3. Indeksai

Indeksas – surikiuota „rodyklė", kaip abėcėlinė knygos rodyklė gale. Be jo DB turi perskaityti visą lentelę
(full table scan).

- **Sudėtinis indeksas ir „kairiojo prefikso" taisyklė.** Indeksas `(provider_profile_id, status, published_at)`
  veikia kaip telefonų knyga, surikiuota pagal (pavardė, vardas, gimimo data). Joje greitai rasi visus
  „Kazlauskus", „Kazlauskus Jonus" arba „Kazlauskus Jonus, gimusius po 1990". Bet visų „Jonų" ji nepadės
  rasti, nes vardai išmėtyti po visas pavardes. Indeksu galima naudotis tik **iš kairės, be tarpų**.
  → https://dev.mysql.com/doc/refman/8.4/en/multiple-column-indexes.html
- **Stulpelių tvarka:** pirmiausia lygybės sąlygos (`=`, `IN`), paskui rikiavimas ar intervalas (`ORDER BY`, `>`).
- **UNIQUE** ne tik greitina paiešką, bet ir saugo duomenų teisingumą DB lygiu (dvi vienodos registracijos,
  dvigubas pasiūlymas, pakartotinis mokėjimo callback'as).
- **Selektyvumas.** Stulpelis su 3 reikšmėmis (`role`) vienas pats beveik neatsijoja eilučių, todėl atskiras jo
  indeksas mažai naudingas. Naudingesnis jis kaip sudėtinio indekso dalis.
- **Indeksas turi kainą:** kiekvienas INSERT ir UPDATE atnaujina visus lentelės indeksus, be to, jie užima vietos.
  Indeksus dedam pagal realias užklausas, o ne „dėl viso pikto".
- **FK indeksai:** MySQL (InnoDB) automatiškai sukuria indeksą FK stulpeliui, jei jo dar nėra. Jei sudėtinis
  indeksas jau **prasideda** tuo stulpeliu, naujo nekuria. SQLite ir PostgreSQL to automatiškai nedaro.
- **InnoDB triukas:** kiekviename antriniame indekse paslėptas ir pirminis raktas. Todėl `(conversation_id)`
  iš tikrųjų yra `(conversation_id, id)`.
- **FULLTEXT** – paieška žodžiais tekste. Yra MySQL, nėra SQLite.
  → https://laravel.com/docs/13.x/migrations#indexes

#### 4. Normalizacija ir denormalizacija

Normalizuota schema kiekvieną faktą saugo vienoje vietoje. Denormalizacija – sąmoningas dubliavimas dėl greičio
(`rating_avg`, `reviews_count`, `offers_count`, `credits_balance`). WordPress daro tą patį su
`wp_posts.comment_count`. Kaina: dublį reikia patikimai atnaujinti (observer, job, transakcija) ir tikrinti testu.

#### 5. Soft deletes

`deleted_at` vietoj tikro ištrynimo (WordPress analogas – „Šiukšliadėžė"). Eloquent automatiškai slepia tokius
įrašus, o `withTrashed()` juos parodo. → https://laravel.com/docs/13.x/eloquent#soft-deleting

#### 6. PHP enum ir cast'as statusams

`enum OfferStatus: string { case Pending = 'pending'; … }` + modelyje `casts()` → `'status' => OfferStatus::class`.
DB saugo paprastą eilutę, o kode turim tipą su metodais (`label()`, `isFinal()`). MySQL `ENUM` nenaudojam,
nes jį keisti sunku. → https://laravel.com/docs/13.x/eloquent-mutators#enum-casting

#### 7. Pinigai centais

`float` dvejetainėje sistemoje negali tiksliai saugoti 0,1, todėl kaupiasi apvalinimo klaidos. Sveikieji centai
visada tikslūs. Stulpelių pavadinimai baigiasi `_cents`, kad niekas nesupainiotų su eurais.

#### 8. Ledger, transakcijos ir užraktai

Kreditų pokyčiai įrašomi kaip nekeičiamos eilutės, o balansas – tik jų suma (cache). Kad du lygiagretūs
veiksmai nenurašytų kreditų dvigubai: `DB::transaction()` + `lockForUpdate()` teikėjo eilutei.
→ https://laravel.com/docs/13.x/database#database-transactions ·
https://laravel.com/docs/13.x/queries#pessimistic-locking

#### 9. Idempotencija

Operacija idempotentiška, jei pakartota kelis kartus duoda tą patį rezultatą kaip įvykdyta vieną kartą.
Mokėjimų tiekėjai callback'ą gali atsiųsti pakartotinai, todėl `UNIQUE(gateway, gateway_reference)` ir
statuso patikra garantuoja, kad kreditai bus užskaityti tik kartą.

#### 10. Polimorfiniai ryšiai ir morph map

Vienas ryšys gali rodyti į skirtingus modelius: skundas – į atsiliepimą, žinutę ar profilį. DB saugo dvi
reikšmes: `*_type` (kuris modelis) ir `*_id`. Su `Relation::enforceMorphMap()` vietoj `App\Models\Review`
saugom trumpą `review`.
→ https://laravel.com/docs/13.x/eloquent-relationships#custom-polymorphic-types

#### 11. ON DELETE taisyklės

`cascade` – ištrinti vaikus kartu, `restrict` – neleisti trinti tėvo, kol yra vaikų, `set null` – palikti vaiką
be nuorodos. Laravel'yje: `->cascadeOnDelete()`, `->restrictOnDelete()`, `->nullOnDelete()`.
→ https://laravel.com/docs/13.x/migrations#foreign-key-constraints

#### 12. Seeder, Factory, Faker

Factory – vieno įrašo receptas (testams). Seeder – DB užpildymo scenarijus. Faker – netikrų duomenų generatorius
(`APP_FAKER_LOCALE=lt_LT`). Dideliam kiekiui naudojamas masinis įterpimas (`DB::table()->insert()` dalimis),
o ne `create()` po vieną (`docs/SEEDING.md` 7 sk.).
→ https://laravel.com/docs/13.x/seeding · https://laravel.com/docs/13.x/eloquent-factories

#### 13. Būsenų mašina

Aiški taisyklė, iš kurios būsenos į kurią galima pereiti (pvz. `open → in_progress`), kas tai daro ir kas turi
įvykti kartu (kreditai, pranešimai). Be jos statusą galima pakeisti bet kur į bet ką, ir duomenys tampa nelogiški.
Mūsų taisyklės – `docs/STATES.md`. Kode: enum metodas `canTransitionTo()` + Action klasė kiekvienam perėjimui.

### Naudingos artisan komandos (naudosim nuo Etapo 1–2)

| Komanda                                                 | Ką daro                                                            |
| ------------------------------------------------------- | ------------------------------------------------------------------ |
| `php artisan make:model ServiceRequest -mfs`            | modelis + migracija (`m`) + factory (`f`) + seeder (`s`)           |
| `php artisan make:migration add_city_id_to_users_table` | nauja migracija esamai lentelei keisti                             |
| `php artisan make:enum Enums/OfferStatus`               | PHP enum klasė                                                     |
| `php artisan migrate`                                   | paleidžia naujas migracijas                                        |
| `php artisan migrate:status`                            | kurios migracijos paleistos, kurios ne                             |
| `php artisan migrate:rollback`                          | atšaukia paskutinę migracijų grupę                                 |
| `php artisan migrate:fresh --seed`                      | ištrina **visas** lenteles, sukuria iš naujo ir užpildo (tik dev!) |
| `php artisan db:seed --class=CitySeeder`                | paleidžia vieną seeder'į                                           |
| `php artisan make:notifications-table`                  | sukuria `notifications` lentelės migraciją                         |
| `php artisan model:show User`                           | modelio stulpeliai, ryšiai, cast'ai                                |
| `php artisan db:show --counts`                          | DB informacija ir visos lentelės su eilučių skaičiumi              |
| `php artisan db:table service_requests`                 | vienos lentelės stulpeliai, indeksai, FK                           |
| `php artisan tinker`                                    | interaktyvi konsolė, kurioje galima bandyti Eloquent               |

### Dažnos klaidos

- Lentelę pavadinti `jobs` (konfliktas su Laravel eilėmis) arba modelį – `Request`.
- FK į lentelę, kuri dar nesukurta: bloga migracijų tvarka arba žiedinė nuoroda vienoje migracijoje.
- `float` pinigams.
- Indeksai „visur" arba „niekur". Bloga stulpelių tvarka sudėtiniame indekse.
- MySQL `ENUM` statusams: vėliau naujo statuso pridėjimas = `ALTER TABLE` didelėje lentelėje.
- Pamiršti, kad SQLite ≠ MySQL: nėra FULLTEXT, FK indeksai nesukuriami automatiškai, ribotas `ALTER TABLE`.
- Pivot lentelės pavadinimas ne pagal konvenciją: tada jį reikia nurodyti ranka `belongsToMany(…, 'table')`.
- Denormalizuotą skaitliuką atnaujinti ne toje pačioje transakcijoje: atsiranda neatitikimų.
- Seed'e: `Hash::make()` cikle, `fake()->unique()` dešimtims tūkstančių, `realText()` su `lt_LT`
  (grąžina anglišką tekstą), `create()` šimtams tūkstančių eilučių.
- Saugoti tai, ką galima pigiai apskaičiuoti (pvz. `is_verified`, kai užtenka `service_request_id IS NOT NULL`).

### Penki svarbiausi dalykai iš Etapo 0

1. **`ServiceRequest` ir `Offer` sieja du ryšiai.** `ServiceRequest hasMany Offer` – FK yra `offers.service_request_id`
   (užklausa turi daug pasiūlymų). `ServiceRequest belongsTo acceptedOffer` – FK yra
   `service_requests.accepted_offer_id` (kuris pasiūlymas laimėjo). Lentelės rodo viena į kitą (žiedinė nuoroda),
   todėl antrą FK pridedam atskira migracija, kai abi lentelės jau sukurtos.

2. **Kaip veikia indeksas `(category_id, status, published_at)`.**
    - `WHERE category_id = 5` – naudoja (kairysis stulpelis).
    - `WHERE status = 'open'` – nenaudoja: `status` ne pirmas, todėl DB nežino, nuo kur ieškoti.
    - `WHERE category_id = 5 AND status = 'open' ORDER BY published_at DESC` – naudoja pilnai: dvi lygybės ir
      rikiavimas be papildomo rūšiavimo.
    - `WHERE published_at > '2026-01-01'` – nenaudoja (tas pats „kairiojo prefikso" principas).

    Todėl viešam sąrašui „visos naujausios užklausos" turim atskirą indeksą `(status, published_at)`.

3. **Kodėl `rating_avg` saugom teikėjo profilyje.** Katalogas rikiuojamas pagal reitingą. Skaičiuojant `AVG()` per
   100 000 atsiliepimų kiekvieną kartą atidarius puslapį, jis būtų lėtas. Kaina – reikšmę reikia atnaujinti
   pasikeitus atsiliepimams (observer → job), o testas tikrina, kad ji sutampa su tikra.

4. **Pinigai centais.** `float` negali tiksliai saugoti 0,1, todėl atsiranda apvalinimo klaidų (`0.1 + 0.2 ≠ 0.3`).
   24,90 € DB saugoma kaip `2490` stulpelyje `price_cents`.

5. **NULL ir UNIQUE kartu.** `reviews.service_request_id` turi UNIQUE indeksą: vienai užklausai – ne daugiau kaip
   vienas atsiliepimas. NULL UNIQUE indekse nelaikomas lygiu kitam NULL (ir MySQL, ir SQLite), todėl NULL reikšmių
   gali būti daug. Verslo prasme NULL reiškia atsiliepimą pagal teikėjo pakvietimą (darbas atliktas ne per
   platformą), ir UI jis rodomas kaip nepatvirtintas.

---

## Etapas 1 – Projekto pagrindas

### Ką darėm ir kodėl

- **Laravel 13 projektas iš oficialaus Vue starter kit.** Starter kit duoda paruoštą autentifikaciją (Fortify),
  Inertia + Vue + Tailwind sujungimą, išdėstymus ir testus. Alternatyva – tuščias projektas ir viskas ranka:
  daugiau darbo ir daugiau vietų suklysti.
- **Autentifikacijos funkcijos.** Su `install:features` palikom el. pašto patvirtinimą, registraciją ir
  slaptažodžio patvirtinimą. 2FA ir passkeys pašalinom, nes jų nėra mūsų DB schemoje. Įrankis paliko likučių
  (tuščią metodą, praleidžiamus testus). PHPStan juos pagavo, ir mes juos išvalėm.
- **Lietuvių kalba.** `APP_LOCALE=lt`, vertimų failai `lang/lt/` ir `lang/lt.json`, datos lietuviškai per Carbon.
- **Šriftas su lietuviškomis raidėmis.** Starter kit šriftas buvo be `latin-ext` rinkinio, todėl ą, č, ę ir kitos
  raidės būtų rodomos kitu šriftu.
- **Pest** vietoj PHPUnit klasių: trumpesni ir lengviau skaitomi testai.
- **Filament 5** admin panelė `/admin`, lietuviška.
- **Viešas išdėstymas ir pradžios puslapis** vietoj Laravel reklaminio puslapio.

### Išmoktos sąvokos

#### 1. Composer ir npm, lock failai

`composer.json` / `package.json` aprašo, kokių paketų reikia (pvz. `^13.0`). `composer.lock` / `package-lock.json`
užfiksuoja tikslias įdiegtas versijas, todėl visi kompiuteriai ir serveris gauna lygiai tą patį. Lock failai Git'e
būna, o `vendor/` ir `node_modules/` – ne, nes juos atkuria `composer install` / `npm install`.

#### 2. `.env` ir `config/`

`.env` – kiekvieno kompiuterio slapti ir aplinkai būdingi nustatymai (Git'e jo nėra). `.env.example` – šablonas
visiems. `config/*.php` skaito `.env` per `env()`, o kodas skaito konfigūraciją per `config('app.locale')`.
`env()` naudojam **tik** config failuose: po `php artisan config:cache` jis kitur grąžina `null`.
→ https://laravel.com/docs/13.x/configuration

#### 3. Service provider

Klasė, kurioje paleidžiant aplikaciją registruojami servisai ir nustatymai (WordPress analogas – įskiepio `init`
hook'as). Sąrašas – `bootstrap/providers.php`. Filament panelė irgi yra provider'is: `AdminPanelProvider`.
→ https://laravel.com/docs/13.x/providers

#### 4. Inertia

Maršrutai ir controller'iai lieka Laravel'yje, o vietoj Blade šablono grąžinamas Vue puslapis su duomenimis:
`Inertia::render('public/Home', ['kategorijos' => ...])`. Atskiro API rašyti nereikia. `Route::inertia('/', 'public/Home')`
– trumpinys, kai duomenų nėra. Duomenys, reikalingi visiems puslapiams (pvz. prisijungęs vartotojas, svetainės
pavadinimas), dedami `HandleInertiaRequests::share()`. Išdėstymas (`PublicLayout`, `AppLayout`) parenkamas
`resources/js/app.ts` faile pagal puslapio pavadinimą. → https://inertiajs.com

#### 5. Wayfinder

Iš Laravel maršrutų sugeneruoja TypeScript funkcijas: `import { login } from '@/routes'`, o `<Link :href="login()">`.
Pervadinus maršrutą, klaidą parodo kompiliatorius, o ne naršyklė. Sugeneruoti failai Git'e nesaugomi.

#### 6. Vite

Dev'e (`composer run dev`) Vite serveris akimirksniu atnaujina pakeistus Vue ir CSS failus naršyklėje (HMR).
`npm run build` sukompiliuoja viską į `public/build/`. Blade direktyva `@vite(...)` įdeda teisingus failus.

#### 7. Lokalizacija

- PHP vertimai: `lang/lt/validation.php` → `__('validation.required')`. `:attribute` – vietos rezervavimas, kurį
  Laravel pakeičia lauko pavadinimu iš `attributes` masyvo („el. paštas", ne „email").
- JSON vertimai: `lang/lt.json`, kur raktas yra angliškas tekstas: `__('Verify Email Address')`.
- `APP_FALLBACK_LOCALE=en`: jei vertimo nėra, rodomas angliškas tekstas, o ne techninis raktas.
- Carbon datas verčia automatiškai pagal aplikacijos kalbą: „prieš 5 minutes", „spalio 3".
  → https://laravel.com/docs/13.x/localization

#### 8. Pest testai

`test('aprašymas', function () { ... })`, tikrinimai per `expect($x)->toBe(...)` arba HTTP tikrinimai
(`$this->get('/')->assertOk()`). `assertInertia()` patikrina, kurį Vue puslapį ir kokius duomenis grąžino
serveris. `tests/Pest.php` nustato, kad visi Feature testai naudoja `RefreshDatabase` (švari DB kiekvienam testui).
→ https://pestphp.com/docs · https://laravel.com/docs/13.x/testing

#### 9. Filament panelė

`AdminPanelProvider` aprašo panelę: adresą (`/admin`), prisijungimą, spalvas, kur ieškoti resursų. Resursai
(sąrašai ir formos modeliams) bus nuo Etapo 2. Prieigą valdys `User::canAccessPanel()` (Etapas 3). Kol jo nėra,
Filament įleidžia tik lokalioje aplinkoje. → https://filamentphp.com/docs

#### 10. Kodo kokybės įrankiai

- **Pint** – sutvarko PHP kodo stilių (tarpai, kabutės, importai).
- **PHPStan (Larastan)** – statinė analizė: randa klaidas nepaleidus kodo. Pavyzdys: rado tuščią metodą, kuris
  turėjo kažką grąžinti.
- **Vite+ `check`** – JS/Vue lint ir formatavimas (taip pat ir Markdown dokumentų).
- **vue-tsc** – TypeScript tipų patikra Vue failuose.
- **CI (GitHub Actions)** – visa tai automatiškai paleidžiama `main` šakos push'ams ir pull request'ams.

### Naudingos komandos

| Komanda                               | Ką daro                                                              |
| ------------------------------------- | -------------------------------------------------------------------- |
| `composer setup`                      | pirmas paleidimas: priklausomybės, `.env`, raktas, migracijos, build |
| `composer run dev`                    | serveris + eilės + Vite + logai vienoje komandoje                    |
| `composer test`                       | Pint + PHPStan + testai                                              |
| `npm run check` / `npm run check:fix` | frontend lint ir formatavimas / su taisymu                           |
| `npm run types:check`                 | TypeScript tipų patikra                                              |
| `php artisan route:list`              | visi maršrutai (adresas, pavadinimas, controller'is)                 |
| `php artisan about`                   | Laravel versija, aplinka, cache, driver'iai                          |
| `php artisan config:show app`         | galutinės konfigūracijos reikšmės                                    |
| `php artisan lang:publish`            | paskelbia Laravel tekstų failus redagavimui                          |
| `./vendor/bin/pest --filter=lietuvių` | paleidžia tik testus, kurių pavadinime yra žodis                     |
| `php artisan wayfinder:generate`      | sugeneruoja Wayfinder maršrutų funkcijas (Vite tai daro ir pats)     |

### Dažnos klaidos

- **„Vite manifest not found"** – nepaleistas `npm run build` arba `composer run dev`.
- **Pakeitei `.env`, bet niekas nepasikeitė** – buvo konfigūracijos cache: `php artisan config:clear`.
- **`env()` kode už config failų ribų** – po `config:cache` grąžina `null`.
- **Šriftas be `latin-ext`** – lietuviškos raidės rodomos kitu šriftu ir puslapis atrodo „sulūžęs".
- **Generatorius paliko nereikalingo kodo** – po automatinių įrankių visada paleisk testus ir PHPStan.
- **Filament produkcijoje grąžina 403** – nėra `canAccessPanel()` (bus Etape 3).
- **Pervadintas maršrutas, o Vue jo „nemato"** – Wayfinder failai pergeneruojami paleidus Vite (`npm run dev` / `build`).

---

## Etapas 2 – Duomenų bazė: migracijos, modeliai, seed'ai

### Ką darėm ir kodėl

- **16 PHP enum'ų** (`app/Enums`) visoms rolėms ir statusams, kiekvienas su lietuvišku `label()`.
- **22 migracijos** pagal `docs/DB_SCHEMA.md`: geografija → katalogas → teikėjai → užklausos → žinutės →
  atsiliepimai → monetizacija → skundai → pranešimai. Žiedinis FK (`service_requests.accepted_offer_id` ↔ `offers`)
  pridedamas atskira migracija, kai abi lentelės jau yra.
- **17 Eloquent modelių** su ryšiais, cast'ais ir accessor'iais, plius **factories su būsenomis** testams.
- **Žinyniniai duomenys** (`database/data`): 10 apskričių, 60 savivaldybių su vietininkais, 251 kategorija
  (12 / 49 / 190), 4 kreditų paketai, 3 prenumeratų planai. Seeder'iai idempotentiški.
- **Dideli demo duomenys** (`DemoDataSeeder` + `database/seeders/Demo/`): ≈ 1,9 mln. eilučių, nuoseklių su verslo
  taisyklėmis, su `SEED_SCALE` daugikliu. Pilnas seed'as: SQLite ~50 s, MySQL ~2,5 min.
- **Vientisumo testas** – 10 patikrinimų, ar seed'ai laikosi taisyklių (praeina SQLite ir MySQL).
- **Filament**: prieiga tik administratoriams (`canAccessPanel`), kategorijų ir savivaldybių valdymas.

### Išmoktos sąvokos

#### 1. Migracijos: FK, ON DELETE ir indeksai

```php
$table->foreignId('client_id')->constrained('users')->restrictOnDelete();
$table->index(['category_id', 'status', 'published_at']);
```

`constrained()` sukuria FK, o `restrictOnDelete()` / `cascadeOnDelete()` / `nullOnDelete()` nusako, kas nutinka
ištrynus tėvą (`DB_SCHEMA.md` 2.12). MySQL FK stulpeliui indeksą sukuria pats, o SQLite – ne, todėl indeksus,
pagal kuriuos ieškom, rašom aiškiai. Sudėtinio indekso stulpelių tvarka svarbi: pirmiausia tie, pagal kuriuos
filtruojam lygybe, paskui – rikiavimo stulpelis. → https://laravel.com/docs/13.x/migrations#foreign-key-constraints

#### 2. Laravel 13 modelių atributai

Vietoj savybių (`protected $fillable = [...]`) Laravel 13 leidžia rašyti PHP atributus virš klasės:

```php
#[Fillable(['first_name', 'last_name', 'email'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
```

Abu būdai veikia vienodai. `role` sąmoningai nėra `Fillable`: rolę keičiam tik kode (`forceFill`), kad jos
nebūtų galima „atsiųsti" per formą (mass assignment apsauga). → https://laravel.com/docs/13.x/eloquent#mass-assignment

#### 3. Cast'ai ir enum'ai

`casts()` metode `'status' => ServiceRequestStatus::class` – iš DB gaunam ne eilutę, o enum'ą, todėl
`$request->status === ServiceRequestStatus::Open`, o klaidingos reikšmės neįmanoma įrašyti. DB lieka paprastas
`string` stulpelis (`DB_SCHEMA.md` 2.6). → https://laravel.com/docs/13.x/eloquent-mutators#enum-casting

#### 4. Ryšiai ir grąžinami tipai

`belongsTo`, `hasMany`, `hasOne`, `belongsToMany` (su pivot ir `withPivot()`), `hasManyThrough`
(`User → ProviderProfile → Offer`), polimorfiniai `morphTo` / `morphMany` (skundai, pranešimai). Grąžinamą tipą
rašom aiškiai (`: BelongsTo`), o PHPDoc'e – generikus (`BelongsTo<City, $this>`), kad PHPStan žinotų, koks modelis
grįš. → https://laravel.com/docs/13.x/eloquent-relationships

#### 5. Morph map

Polimorfiniuose stulpeliuose (`reportable_type`) saugom trumpą vardą `review`, o ne `App\Models\Review`.
`Relation::enforceMorphMap([...])` `AppServiceProvider` faile: pervadinus klasę DB duomenys nesulūžta, o pamiršus
modelį įrašyti į sąrašą gaunam klaidą. → https://laravel.com/docs/13.x/eloquent-relationships#custom-polymorphic-types

#### 6. `preventLazyLoading` ir N+1

`Model::preventLazyLoading(! production)`: jei kode parašai `$category->parent->name`, nors ryšys neužkrautas,
dev'e ir testuose gauni išimtį, o ne 100 papildomų SQL užklausų. Sprendimas – `->with('parent')` (eager loading).
Dėl to ir `Category` „saving" hook'e tėvo lygį skaitom užklausa, o ne per `$category->parent`.
→ https://laravel.com/docs/13.x/eloquent-relationships#preventing-lazy-loading

#### 7. Accessor'iai ir scope'ai

`name()` grąžina `Attribute::get(fn () => ...)` – „virtualus" stulpelis `$user->name`, kurio DB nėra.
`#[Scope] protected function active(Builder $query)` leidžia rašyti `Category::active()->get()`.
→ https://laravel.com/docs/13.x/eloquent-mutators#accessors-and-mutators · https://laravel.com/docs/13.x/eloquent#local-scopes

#### 8. Factories ir būsenos

`ServiceRequest::factory()->completed()->create()` – būsena (`state`) pakeičia kelis laukus, o `afterCreating`
gali sukurti susijusius įrašus (priimtą pasiūlymą ir `accepted_offer_id`). Factories naudojam testuose.
→ https://laravel.com/docs/13.x/eloquent-factories#factory-states

#### 9. Seeder'iai: idempotentiški ir masiniai

- Žinyniniai seeder'iai naudoja `updateOrCreate()` pagal slug ar el. paštą: paleidus antrą kartą nieko
  nedubliuoja (kaip WordPress `dbDelta()`).
- `use WithoutModelEvents;` – seed'o metu nevykdomi model events (observeriai, pranešimai).
- Dideli duomenys – **masinis įterpimas** `DB::table('offers')->insert($rows)` po 1 000 eilučių vienoje
  transakcijoje, su **savais ID** (1…N), kad ryšius galėtume sudėlioti atmintyje be papildomų `SELECT`.
- **PHP generatoriai** (`yield`): eilutės kuriamos po vieną, todėl 300 000 eilučių vienu metu atmintyje nėra.
- **Deterministinis atsitiktinumas**: `mt_srand(2026)` + `fake()->seed(2026)` – tas pats rezultatas kiekvieną
  kartą. `random_int()` ir `Str::uuid()` „užsėti" negalima, todėl seed'e jų nenaudojam.
  → https://laravel.com/docs/13.x/seeding · https://laravel.com/docs/13.x/queries#insert-statements

#### 10. Konfigūracija seed'ams

`config/seeding.php` skaito `SEED_SCALE`, `SEED_DEMO` ir kt. iš `.env`. Kodas kviečia `config('seeding.scale')`,
o ne `env()` (žr. Etapo 1 sąvoką apie `.env`). Testuose šias reikšmes perrašo `phpunit.xml`.

#### 11. Denormalizuoti skaitliukai ir `UPDATE … (SELECT …)`

`reviews_count`, `rating_avg`, `offers_count` saugomi lentelėse greičiui. Seed'o pabaigoje jie perskaičiuojami
vienu sakiniu kiekvienai lentelei:

```sql
UPDATE service_requests SET offers_count = (
    SELECT COUNT(*) FROM offers WHERE offers.service_request_id = service_requests.id AND offers.status <> 'withdrawn'
)
```

Tai **koreliuota subužklausa**: vidinė užklausa vykdoma kiekvienai išorinės lentelės eilutei. Veikia ir MySQL, ir SQLite.

#### 12. Model events (`saving`)

```php
protected static function booted(): void
{
    static::saving(function (Category $category) { $category->depth = ...; });
}
```

Kodas vykdomas prieš kiekvieną įrašymą, kad ir iš kur jis būtų (Filament forma, tinker, kodas). WordPress analogas –
`save_post` hook'as. → https://laravel.com/docs/13.x/eloquent#events

#### 13. Filament resource

Resource – vieno modelio admin CRUD: **forma** (`Schema` su laukais `TextInput`, `Select`, `Toggle`, sekcijomis),
**lentelė** (`Table` su stulpeliais, filtrais, veiksmais) ir **puslapiai** (List / Create / Edit). „Simple" resource
(`--simple`) – vienas puslapis su modaliniais langais, tinka mažiems žinynams (savivaldybėms).
`php artisan make:filament-resource Category --generate` sugeneruoja pradinį kodą pagal DB stulpelius, o mes jį
pritaikom: lietuviški užrašai, validacija (`unique(ignoreRecord: true)`), `relationship()` su sąlyga,
`modifyQueryUsing(fn ($q) => $q->with('parent'))` prieš N+1. → https://filamentphp.com/docs/5.x/resources/overview

#### 14. `canAccessPanel()`

`User implements FilamentUser` ir metodas `canAccessPanel(Panel $panel): bool` nusprendžia, kas įleidžiamas į
`/admin`: tik `admin` rolė su patvirtintu el. paštu ir be blokavimo. Be šio metodo Filament ne lokalioje aplinkoje
neįleistų nieko. → https://filamentphp.com/docs/5.x/users/overview

#### 15. Testai: datasets ir Livewire

- **Dataset** – tas pats testas su keliais duomenų rinkiniais: `test(...)->with(['klientas' => fn () => ..., ...])`.
  → https://pestphp.com/docs/datasets
- Filament puslapiai yra Livewire komponentai, todėl testuojami per `Livewire::test(CreateCategory::class)
->fillForm([...])->call('create')->assertHasNoFormErrors()`.

### Naudingos komandos

| Komanda                                            | Ką daro                                            |
| -------------------------------------------------- | -------------------------------------------------- |
| `php artisan migrate:fresh --seed`                 | DB iš naujo su visais seed'ais                     |
| `SEED_SCALE=0.05 php artisan migrate:fresh --seed` | mažas greitas seed'as (≈ 95 000 eilučių, ~4 s)     |
| `SEED_DEMO=false php artisan migrate:fresh --seed` | tik žinyniniai duomenys                            |
| `php artisan db:seed --class=CategorySeeder`       | vienas seeder'is                                   |
| `php artisan db:show --counts`                     | lentelės ir jų eilučių skaičiai                    |
| `php artisan db:table offers`                      | lentelės stulpeliai, indeksai, FK                  |
| `php artisan model:show ServiceRequest`            | modelio stulpeliai, ryšiai, cast'ai                |
| `php artisan make:filament-resource City --simple` | Filament resource                                  |
| `php artisan tinker`                               | konsolė: `ServiceRequest::with('offers')->first()` |

### Dažnos klaidos

- **`LazyLoadingViolationException`** – ryšys naudojamas neužkrautas. Pridėk `->with('ryšys')`.
- **FK klaida įterpiant** – vaikas įterpiamas anksčiau nei tėvas, arba žiedinis FK. Tvarka ir atskiras `UPDATE`.
- **`Safety level may not be changed inside a transaction`** – SQLite `PRAGMA` negalima keisti transakcijos viduje
  (testuose `RefreshDatabase` viską vykdo transakcijoje).
- **Tylus mass assignment ignoravimas** – laukas ne `Fillable`, todėl `create()` jo neįrašo. Rolę nustatom per `forceFill()`.
- **`env()` seeder'yje** – po `config:cache` grąžins `null`; skaityk per `config()`.
- **Per mažas `SEED_SCALE`** – teikėjų per mažai, kad pasiekti tikslinį pasiūlymų kiekį (seeder'is įspėja).
- **Filament užrašai lietuviškai „Sukurti kategorija"** – Filament įstato vardininką, todėl kai kur rašom savus užrašus („Nauja kategorija").

---

## Etapas 3 – Autentifikacija, rolės, teikėjo profilis

> Etapas atliktas lygiagrečiai su Etapais 4 ir 5 (atskiri agentai, atskiros git šakos, sujungta į vieną).
> Liko vėlesniems etapams: demo nuotraukos seed'e (`SEED_MEDIA`) ir nuotraukų peržiūra Filament'e.

### Ką darėm ir kodėl

- **Registracija su role.** Formoje – dvi kortelės: „Ieškau paslaugų" (klientas) ir „Teikiu paslaugas"
  (teikėjas). `/register?role=provider` teikėjo rolę pažymi iš anksto (mygtukui „Tapti teikėju").
  Administratoriaus rolės pasirinkti neįmanoma: validacija leidžia tik dvi reikšmes, o `role` nėra `Fillable`.
- **Kur nukreipti po registracijos.** Teikėjas keliauja į profilio vedlį, klientas – į „Mano paskyra". Jei
  el. paštas nepatvirtintas, pirma rodomas patvirtinimo puslapis, o paspaudus nuorodą laiške žmogus grįžta ten,
  kur ėjo (vedlį).
- **Laiškai ir visi starter kit puslapiai – lietuviškai** (`lang/lt.json`, `lang/lt/passwords.php`, Vue puslapiai,
  net ekrano skaitytuvų tekstai shadcn-vue komponentuose).
- **`auth.user` – tik reikalingi laukai** (`App\Http\Resources\AuthUserResource`). Anksčiau į kiekvieną puslapį
  keliavo visas `User` modelis su visais stulpeliais.
- **Policies** – „ar TAI tavo profilis / darbas?". `role:provider` middleware tik grubiai atsijoja ne teikėjus.
- **Teikėjo profilio vedlys** (`/paskyra/profilis/...`): 4 žingsniai, kiekvienas – atskiras puslapis su savo
  Form Request ir Action klase, išsaugomas atskirai. Žingsnių juosta rodo, kas atlikta. Galima išeiti ir grįžti:
  `/paskyra/profilis` nukreipia į pirmą neatliktą privalomą žingsnį.
- **Profilio būsena:** profilis sukuriamas 1 žingsnyje kaip `pending` ir automatiškai tampa `active`, kai
  užpildyti privalomi žingsniai (duomenys + kategorija + zona). Kainos neprivalomos. Kodėl be administratoriaus
  patvirtinimo – `docs/DB_SCHEMA.md` → `provider_profiles` → „Būsenos".
- **Failai su `spatie/laravel-medialibrary`:** avataras (visoms rolėms, nustatymuose), teikėjo logotipas ir
  viršelis, atliktų darbų nuotraukos su miniatiūromis.
- **Atlikti darbai (portfolio):** sąrašas, kūrimas, redagavimas, trynimas, tvarka, iki 10 nuotraukų darbe.
- **„Mano paskyra" pagal rolę:** klientas mato mygtuką „Sukurti užklausą", teikėjas – profilio būseną, pilnumą
  procentais, kreditų balansą, administratorius – nuorodą į `/admin`.

Išbandyti: `php artisan migrate:fresh --seed`, `php artisan storage:link`, `composer run dev`. Prisijungimai:
`teikejas1@example.test`, `klientas1@example.test`, `admin1@example.test`, slaptažodis `password`. Naujas teikėjas –
užsiregistruok (patvirtinimo laiškas atsidurs `storage/logs/laravel.log`, nes `MAIL_MAILER=log`).

### Išmoktos sąvokos

#### 1. Fortify: kaip „įsikišti" į autentifikaciją

Fortify – autentifikacija be išvaizdos: maršrutai, controller'iai ir logika jau paruošti, o mes pateikiam
puslapius (`Fortify::registerView(...)`) ir veiksmus (`app/Actions/Fortify/CreateNewUser.php`). Norėdami pakeisti,
kur nukreipti po registracijos, **nekeičiam Fortify kodo**, o service container'yje jo klasę pakeičiam savo:

```php
// FortifyServiceProvider::register()
$this->app->singleton(RegisterResponseContract::class, RegisterResponse::class);
```

Kai Fortify paprašys `RegisterResponse` sąsajos, konteineris duos mūsų klasę. Tai **service container** ir
**sąsajų (interface)** galia – WordPress analogas būtų filtras, pvz. `login_redirect`.
→ https://laravel.com/docs/13.x/fortify#customizing-redirects · https://laravel.com/docs/13.x/container#binding-interfaces-to-implementations

#### 2. Mass assignment ir `forceFill()`

`User::create($request->all())` įrašytų viską, ką atsiuntė naršyklė – ir `role=admin`. Todėl `role` nėra
`#[Fillable]`, o nustatoma aiškiai: `$user->forceFill(['role' => UserRole::from($input['role'])])`. Dar vienas
sluoksnis – validacija `Rule::enum(UserRole::class)->only([UserRole::Client, UserRole::Provider])`.
Testas „administratoriaus rolės įšvirkšti negalima" tai užtikrina. → https://laravel.com/docs/13.x/eloquent#mass-assignment

#### 3. El. pašto patvirtinimas

`User implements MustVerifyEmail` + Fortify `emailVerification()` funkcija: po registracijos išsiunčiamas laiškas
su **pasirašyta nuoroda** (signed URL – nuorodoje yra parašas, todėl jos negalima suklastoti, ir galiojimo laikas).
`verified` middleware nepatvirtintą vartotoją nukreipia į `/email/verify` ir **įsimena, kur jis ėjo**
(`redirect()->guest()`), o patvirtinus `redirect()->intended()` grąžina ten. WordPress to „iš dėžės" neturi.
→ https://laravel.com/docs/13.x/verification · https://laravel.com/docs/13.x/urls#signed-urls

#### 4. Vertimai: `lang/lt.json` ir `lang/lt/*.php`

Laravel laiškai tekstus ima per `Lang::get('Reset Password')` – anglišką sakinį kaip raktą. Jį verčia
`lang/lt.json`. Trumpi raktai (`passwords.sent`) – `lang/lt/passwords.php`. Vue puslapiuose tekstus rašom tiesiai
lietuviškai (svetainė vienos kalbos). → https://laravel.com/docs/13.x/localization#using-translation-strings-as-keys

#### 5. Middleware

Middleware – „sargas" prieš controller'į. Turim du savus:

- `role:provider` (`EnsureUserHasRole`) – su **parametru** po dvitaškio; ne ta rolė → 403.
- `EnsureProviderProfileExists` – jei profilio dar nėra, vedlio 2–4 žingsniai nukreipia į 1 žingsnį.
  Maršrute galima nurodyti klasės vardą, alias'o registruoti nebūtina.

WordPress analogas – patikra `template_redirect` hook'e puslapio pradžioje. → https://laravel.com/docs/13.x/middleware

#### 6. Gates ir Policies

Policy – klasė su metodais „ar šis vartotojas gali X su šiuo įrašu?":

```php
public function update(User $user, PortfolioItem $portfolioItem): bool
{
    return $user->providerProfile?->id === $portfolioItem->provider_profile_id;
}
```

- Laravel ją randa pats pagal pavadinimą (`PortfolioItem` → `PortfolioItemPolicy`).
- `?User` – metodą kviečia ir neprisijungusiam (pvz. `view` – aktyvų profilį mato visi).
- Patikra: maršrute `->can('update', 'portfolioItem')`, controller'yje `Gate::authorize('update', $profile)`,
  Blade/kode `$user->can(...)`. Alternatyva – Form Request `authorize()` metodas; rinkomės maršrutus, nes taip
  visos teisės matosi viename faile (`routes/account.php`).

WordPress analogas – `current_user_can('edit_post', $id)` su `map_meta_cap` (teisė priklauso nuo konkretaus įrašo).
→ https://laravel.com/docs/13.x/authorization#creating-policies

#### 7. Form Request

Kiekvienam žingsniui – savo klasė (`app/Http/Requests/Account`): `rules()`, `messages()` (savi pranešimai),
`attributes()` (lietuviški laukų pavadinimai), `prepareForValidation()` – įvesties sutvarkymas prieš validaciją
(„8 612 34567" → „+37061234567", „www.x.lt" → „https://www.x.lt"). Naudingos taisyklės:
`Rule::excludeIf()` („visa Lietuva" – savivaldybių sąrašo nereikia), `required_with:prices.*.price_from`
(su `*` – tos pačios eilutės laukas), `Rule::exists('category_provider_profile', 'category_id')->where(...)`
(kategorija – tik iš savo), `distinct`. → https://laravel.com/docs/13.x/validation#form-request-validation

#### 8. Action klasės ir automatinis įterpimas (dependency injection)

Verslo logika – `app/Actions` (`SaveProviderDetails`, `SyncProviderCategories`, `ActivateCompletedProfile`…).
Controller'is jų nekuria pats – užtenka nurodyti tipą parametre, ir konteineris paduoda objektą (kartu su jo
priklausomybėmis): `public function update(UpdateProviderDetailsRequest $request, SaveProviderDetails $save)`.
Keli susiję įrašai (vartotojo telefonas + profilis, pivot + būsena) keičiami `DB::transaction()` viduje.
→ https://laravel.com/docs/13.x/container#automatic-injection

#### 9. API Resource ir Inertia bendri props

`AuthUserResource::make($user)->resolve($request)` – masyvas tik su reikalingais laukais. Inertia props matomi
naršyklėje (puslapio HTML'e), todėl siųsti visą modelį – ir duomenų nutekėjimo rizika, ir didesnis atsakymas.
Bendri props `HandleInertiaRequests::share()` rašomi kaip closure (`fn () => ...`) – skaičiuojami tik kai reikia.
→ https://laravel.com/docs/13.x/eloquent-resources · https://inertiajs.com/shared-data

#### 10. Failų įkėlimas ir saugi validacija

```php
File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(5 * 1024)
    ->dimensions(Rule::dimensions()->minWidth(200)->maxWidth(8000))
```

- Tipas tikrinamas pagal **turinį** (MIME), ne pagal plėtinį: pervadintas `virusas.php` → `foto.jpg` nepraeis.
- **SVG draudžiamas** – jame gali būti `<script>`.
- **Didžiausias plotis** saugo serverį: 30 000 px paveikslėlio sumažinimas suvalgytų visą atmintį.
- PHP pats atmeta failus, didesnius už `upload_max_filesize` (numatytai 2 MB), o visą užklausą – jei didesnė už
  `post_max_size`. Produkcijoje juos reikia padidinti (`docs/DB_SCHEMA.md` → `media`).

WordPress analogas – `wp_handle_upload()` su `upload_mimes` filtru.
→ https://laravel.com/docs/13.x/validation#validating-files · https://laravel.com/docs/13.x/filesystem

#### 11. Diskai ir `storage:link`

`public` diskas – `storage/app/public`. Kad naršyklė pasiektų failus, `php artisan storage:link` sukuria nuorodą
`public/storage → storage/app/public`. Diską vėliau galima pakeisti į S3 nekeičiant kodo.
→ https://laravel.com/docs/13.x/filesystem#the-public-disk

#### 12. spatie/laravel-medialibrary

Modelis `implements HasMedia` + `use InteractsWithMedia`, o `registerMediaCollections()` aprašo kolekcijas:

```php
$this->addMediaCollection('avatar')
    ->singleFile()                                   // naujas failas pakeičia seną
    ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
    ->registerMediaConversions(function (): void {
        $this->addMediaConversion('thumb')->nonQueued()->fit(Fit::Crop, 128, 128);
    });

$user->addMediaFromRequest('image')->toMediaCollection('avatar');
$user->getFirstMediaUrl('avatar', 'thumb');
```

- Visi failai – vienoje `media` lentelėje; `model_type` saugomas trumpu vardu (`user`) dėl morph map.
- **Miniatiūros (conversions):** `nonQueued()` – iškart (avataras, logotipas: rezultatą reikia matyti tuoj pat),
  numatytai – eilėje (portfolio: 10 nuotraukų įkėlimas neturi laukti). Kol miniatiūros nėra, rodom originalą
  (`hasGeneratedConversion()`).
- Ištrynus modelį, ištrinami ir jo failai.

WordPress analogas – „attachment" įrašai + `add_image_size()`. → https://spatie.be/docs/laravel-medialibrary

#### 13. Route model binding ir scoped bindings

`/paskyra/darbai/{portfolioItem}` – Laravel pats suranda `PortfolioItem` pagal ID (nerado → 404).
`->scopeBindings()` maršrute `darbai/{portfolioItem}/nuotraukos/{media}` antrą parametrą ieško **tik tarp pirmojo
ryšio** (`$portfolioItem->media()`): svetimos nuotraukos ID gautų 404, net jei darbas savas.
→ https://laravel.com/docs/13.x/routing#implicit-model-binding-scoping

#### 14. Failai per Inertia ir method spoofing

Su failu Inertia siunčia `multipart/form-data` (`forceFormData: true`). PHP tokio formato nemoka išskaidyti
PUT užklausoms, todėl redaguojant siunčiam POST su lauku `_method=put` – Laravel jį traktuoja kaip PUT.
`useForm` (reaktyvi formos būsena) tinka sudėtingoms formoms (medis su varnelėmis, failų peržiūra), `<Form>`
komponentas – paprastoms. → https://inertiajs.com/file-uploads · https://inertiajs.com/forms ·
https://laravel.com/docs/13.x/routing#form-method-spoofing

#### 15. Išvestinė būsena vietoj saugomos

Ar vedlio žingsnis atliktas, **nesaugom** atskirame stulpelyje (`wizard_step = 3`), o išvedam iš duomenų:
yra kategorijų → žingsnis atliktas (`App\Enums\ProviderWizardStep::isDone()`). Taip progresas niekada neišsiskiria
su tikrove (pvz. administratorius pakeitė kategorijas). Tas pats principas kaip `reviews.is_verified` nebuvimas
(`DB_SCHEMA.md` → `reviews`).

#### 16. Pinigai be float

Kaina įvedama „15,50", saugoma `1550` centų. Konvertuojam eilutėmis (`explode('.')`), ne `(float) * 100`:
`0.29 * 100` PHP'e duoda `28.999999999999996`. → `docs/DB_SCHEMA.md` 2.7

#### 17. Eager loading niuansai

- `$profile->categories()->with('parent.parent')` – tėvai ir seneliai dviem užklausomis visiems iš karto.
- `preventLazyLoading` išimtį meta tik modeliams, užkrautiems **kartu su kitais** (kolekcijoje). Vienas modelis
  (pvz. prisijungęs vartotojas) ryšį gali užsikrauti „tingiai" – tai ne N+1. Todėl `$request->user()->providerProfile`
  veikia, o cikle per kategorijas `$category->parent` – meta klaidą.
- Net kai `parent_id` yra `NULL`, prieiga prie neužkrauto `->parent` laikoma pažeidimu – tikrinam `parent_id`.
  → https://laravel.com/docs/13.x/eloquent-relationships#preventing-lazy-loading

#### 18. Testai

- `Storage::fake('public')` – failai rašomi į laikiną diską; `UploadedFile::fake()->image('a.jpg', 400, 400)`
  sukuria tikrą paveikslėlį (reikia GD). **Bet** `fake()` MIME tipą spėja iš plėtinio – turinio patikrai
  testuose naudojam tikrą laikiną failą (`new UploadedFile($path, 'foto.jpg', test: true)`).
- `Notification::fake()` + `Notification::assertSentTo(...)` – laiškas nesiunčiamas, bet tikrinam jo turinį.
- `assertInertia(fn (Assert $page) => $page->component('...')->where('auth.user.role', 'provider'))`.
- `Gate::forUser($user)->allows('update', $item)` – Policy testas be HTTP.
- Dataset su closure (`'klientas' => fn () => User::factory()->create()`) – modelis kuriamas tik paleidus testą.
  → https://laravel.com/docs/13.x/http-tests#testing-file-uploads · https://pestphp.com/docs/datasets

#### 19. PHPStan ir `casts()`

Larastan neskaito `casts()` metodo turinio, todėl `$profile->status` laikytų `string`. Sprendimas – PHPDoc virš
modelio: `@property ProviderStatus $status`. (Alternatyva – `parseModelCastsMethod: true` `phpstan.neon` faile.)

### Naudingos komandos

| Komanda                                                             | Ką daro                                                 |
| ------------------------------------------------------------------- | ------------------------------------------------------- |
| `composer require spatie/laravel-medialibrary`                      | paketo įdiegimas (cloud: `--prefer-install=source`)     |
| `php artisan vendor:publish --tag=medialibrary-migrations`          | `media` lentelės migracija į `database/migrations`      |
| `php artisan storage:link`                                          | `public/storage` nuoroda – kad veiktų failų URL         |
| `php artisan queue:work`                                            | eilės darbuotojas (portfolio miniatiūros)               |
| `php artisan media-library:regenerate`                              | perdaryti miniatiūras (pakeitus jų dydžius)             |
| `php artisan media-library:clean`                                   | išvalyti nebenaudojamas miniatiūras                     |
| `php artisan make:policy PortfolioItemPolicy --model=PortfolioItem` | nauja Policy                                            |
| `php artisan make:request Account/PortfolioItemRequest`             | naujas Form Request                                     |
| `php artisan make:middleware EnsureProviderProfileExists`           | naujas middleware                                       |
| `php artisan make:resource AuthUserResource`                        | naujas API Resource                                     |
| `php artisan route:list --path=paskyra`                             | paskyros maršrutai su middleware ir vardais             |
| `php artisan wayfinder:generate --with-form`                        | TypeScript maršrutų funkcijos (daro ir `npm run build`) |
| `php artisan test --filter=ProviderWizard`                          | tik vedlio testai                                       |

### Dažnos klaidos

- **Rolę galima „atsiųsti" per formą** – jei `role` būtų `Fillable` arba validacija leistų bet kokią reikšmę.
- **Wayfinder importas tuo pačiu vardu kaip prop** (`import { wizard }` ir prop `wizard`) – šablone laimi importas,
  TypeScript skundžiasi. Pervadink: `import { wizard as wizardRoute }`.
- **PUT su failais** – failai „dingsta". Naudok POST + `_method=put`.
- **„Nuotraukos įkelti nepavyko" 3 MB failui, nors riba 5 MB** – failą atmetė pats PHP (`upload_max_filesize` = 2 MB); per didelė visa
  užklausa (`post_max_size`) → 413 klaida (`PostTooLargeException`).
- **Nuotraukų URL grąžina 404** – nepaleista `php artisan storage:link` arba `APP_URL` neatitinka adreso naršyklėje
  (medialibrary URL – absoliutūs).
- **Portfolio miniatiūros neatsiranda** – nepaleistas eilės darbuotojas (`composer run dev` jį paleidžia).
- **`MAX(sort_order)` su ryšio `orderBy`** – MySQL griežtame režime klaida. `->reorder()` nuima rikiavimą.
- **`LazyLoadingViolationException` cikle** – `$category->parent` neužkrautas; pridėk `with('parent.parent')`.
- **Testuose 500 „Unable to locate file in Vite manifest"** – naujas Vue puslapis, bet nepaleistas `npm run build`.
- **PHPStan: `ImageDriver::nonQueued()` nerastas** – medialibrary `fit()` grąžina kito tipo objektą, todėl
  `->nonQueued()` rašom prieš `->fit()`.

---

## Etapas 4 – Katalogas ir paieška (viešoji dalis)

> Logotipai, viršeliai ir portfolio nuotraukos prijungti sujungus su Etapu 3 (medialibrary). `sitemap.xml`,
> schema.org ir `EXPLAIN` su pilnu MySQL seed'u – Etape 8.

### Ką darėm ir kodėl

- **Puslapiai** (`routes/catalog.php`, controller'iai `app/Http/Controllers/Catalog`, Vue `resources/js/pages/public`):

    | Adresas                             | Puslapis                                                    |
    | ----------------------------------- | ----------------------------------------------------------- |
    | `/`                                 | pradžia: kategorijos, paieška, populiarūs miestai, geriausi |
    | `/paslaugos`                        | visas kategorijų medis                                      |
    | `/paslaugos/{kategorija}`           | bet kurio lygio kategorija + jos teikėjai                   |
    | `/paslaugos/{kategorija}/{miestas}` | SEO puslapis „Plytelių klijavimas Vilniuje"                 |
    | `/meistrai?miestas=…&patikrinti=1…` | visi teikėjai su filtrais                                   |
    | `/meistrai/{slug}`                  | viešas teikėjo profilis                                     |
    | `/paieska?q=…&miestas=…`            | paieška tekstu                                              |

- **Atrankos taisyklės – modelio scope'uose** (`ProviderProfile` → blokas „Katalogas ir paieška"):
  `active()` (tik aktyvūs), `inCategories($ids)` (kategorija, jos tėvai ir vaikai), `servingCity($id)` (miestas
  zonose arba „visa Lietuva"), `verified()`, `withMinRating()`, `matchingText()`, `sortedBy()`.
  Tas pačias taisykles naudos ir Etapas 5 (kam pranešti apie naują užklausą).
- **Užklausa vienoje vietoje** – `App\Services\Catalog\ProviderListQuery`: sudeda scope'us, pasirenka tik kortelei
  reikalingus stulpelius, eager loading'u užkrauna miestus ir kategorijas, puslapiuoja po 20.
- **Cache** – `CatalogCache` (kategorijų medis ir geografija). Medis tik ~250 kategorijų, todėl tėvus ir vaikus
  randam PHP'e (`CategoryTree`), o ne SQL užklausomis. Pakeitus kategoriją ar miestą per Filament, cache išvalo
  `CatalogCacheObserver`.
- **Filtrai** – `CatalogFilterRequest` (Form Request): validuoja URL parametrus, bet neteisingų neatmeta su klaida,
  o tiesiog ignoruoja, ir grąžina tipizuotą `ProviderFilters` objektą.
- **Į Vue – tik vieši laukai** per API Resources (`app/Http/Resources/Catalog`). Testai tikrina, kad atsakyme nėra
  el. pašto, telefono, įmonės kodų ir kreditų.
- **SEO** – `CatalogSeo` sugeneruoja `title`, `description`, `canonical`, `robots`; administratoriaus įrašyti
  `meta_title` / `meta_description` turi pirmenybę. Žymos išvedamos jau serverio HTML (`app.blade.php`), o Vue
  (`SeoHead.vue`) jas perima.
- **Paieška tekstu** – MySQL FULLTEXT (boolean režimas), SQLite – `LIKE`. Lietuviški žodžiai sutrumpinami iki
  šaknies (`SearchTerms`), kad „plyteles" rastų „Plytelių klijavimas".

### Svarbiausi sprendimai ir alternatyvos

| Klausimas                                       | Pasirinkta                                                               | Alternatyva ir kodėl ne                                                                                                                        |
| ----------------------------------------------- | ------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| Kaip rasti „kategorijos ir jos medžio" teikėjus | `id IN (SELECT provider_profile_id FROM pivot WHERE category_id IN (…))` | `JOIN` su pivot – teikėjas su keliomis tinkamomis kategorijomis pasikartotų (reikėtų `DISTINCT`); `whereHas` – papildomas JOIN su `categories` |
| Kur skaičiuoti medžio ryšius                    | PHP'e iš cache (`CategoryTree::relatedIds()`)                            | rekursinis SQL (CTE) – sudėtingiau, o medis mažas ir retai keičiasi                                                                            |
| Ką saugoti cache                                | paprastus masyvus                                                        | Eloquent modelius – `config/cache.php` → `serializable_classes = false` neleidžia jų atkurti (`__PHP_Incomplete_Class`)                        |
| Kaip išvalyti cache                             | fiksuoti raktai + `Cache::forget()` observer'yje                         | tag'ai (`Cache::tags()`) – `database` ir `file` store'ai jų nepalaiko; versijos raktas – dar viena judanti dalis                               |
| Neteisingi URL parametrai                       | ignoruojami (`failedValidation()` nemeta išimties)                       | įprastas Form Request nukreiptų atgal su klaida – kataloge (senos nuorodos, robotai) tai blogai                                                |
| Miestas kategorijos puslapyje                   | URL dalis `/paslaugos/{kategorija}/{miestas}`                            | `?miestas=` – SEO požiūriu silpniau; todėl `?miestas=` nukreipiamas į gražų adresą                                                             |
| SEO žymos be JavaScript                         | `app.blade.php` išveda jas iš `seo` prop'o                               | Inertia SSR (Node procesas serveryje) – geriausia, bet tai diegimo klausimas (Etapas 8)                                                        |
| Kaina „nuo" kortelėje                           | tik kategorijos puslapyje (`CategoryProviderCardResource`)               | visur – „nuo 3 € / m²" ir „nuo 40 € / val." nepalyginamos, kaina klaidintų                                                                     |

### Sprendimas: Laravel Scout + Meilisearch

**Dabar – MySQL FULLTEXT** (`whereFullText`), be naujų paketų ir serverių.

| Scout + Meilisearch privalumai                                | Trūkumai                                                                      |
| ------------------------------------------------------------- | ----------------------------------------------------------------------------- |
| klaidų tolerancija („plytaliu" randa „plytelių")              | dar vienas serveris (diegimas, atnaujinimai, atmintis, atsarginės kopijos)    |
| žodžio pradžios paieška ir aktualumo rikiavimas „iš dėžės"    | duomenis reikia sinchronizuoti (eilių job'ai; indeksas gali atsilikti)        |
| greita paieška per milijonus įrašų, facetai (filtrų skaičiai) | filtrai pagal zonas ir kategorijų medį – dubliuojama logika indekse           |
| paieška „kol rašai" (instant search)                          | lietuvių kalbos šaknų nežino (kaip ir MySQL) – kompensuoja klaidų tolerancija |

**Kada pereitume:** (1) vartotojai skundžiasi, kad paieška neranda (rašybos klaidos, galūnės), (2) reikia paieškos
„kol rašai" ar facetų, (3) FULLTEXT užklausos tampa lėtos (> ~100 ms su pilnu seed'u). Perėjimas nebrangus: paieškos
logika viename scope'e (`matchingText`), o Scout pridedamas `Searchable` trait'u ir `toSearchableArray()` metodu.
Pastaba: Scout prideda statinį `search()` metodą, todėl mūsų scope sąmoningai vadinasi `matchingText`.
→ https://laravel.com/docs/13.x/scout

**Patikrinta su MySQL 8.0** (`SEED_SCALE=0.05`): FULLTEXT kelias veikia („plytelių klijavimas" Vilniuje – 4
teikėjai, „santechnikas" – 3). `EXPLAIN` sąrašo užklausai: MySQL pivot subužklausą paverčia semi-join (LooseScan).
Su mažais duomenimis jis renkasi pirminį raktą, o ne `(category_id, provider_profile_id)` indeksą – Etape 8
patikrinsim su pilnu seed'u.

### Išmoktos sąvokos

#### 1. Route model binding pagal slug

```php
Route::get('paslaugos/{category:slug}', [CategoryController::class, 'show']);

public function show(CatalogFilterRequest $request, Category $category) // Laravel jau surado pagal slug arba grąžino 404
```

`{category:slug}` – ieškoti ne pagal `id`, o pagal `slug` stulpelį. Du modeliai viename URL
(`{category:slug}/{city:slug}`) Laravel pagal nutylėjimą laiko tėvu ir vaiku ir miesto ieškotų per
`$category->cities()` – todėl maršrutui `->withoutScopedBindings()`. Binding'as randa ir išjungtą kategoriją ar
neaktyvų profilį, todėl controller'yje dar tikrinam (`abort_unless(..., 404)`) – 404, o ne 403, kad neišduotume,
jog toks įrašas yra. WordPress analogas – rewrite taisyklė + `get_page_by_path()`.
→ https://laravel.com/docs/13.x/routing#route-model-binding

#### 2. Eager loading ir N+1

N+1 – kai sąraše kiekvienam iš 20 teikėjų atskirai klausiam jo miesto (1 + 20 užklausų). Eager loading užkrauna
ryšį visiems iš karto viena užklausa:

```php
ProviderProfile::query()->with([
    'city:id,name,slug',                                   // tik reikalingi stulpeliai
    'categories' => fn (Relation $query) => $query->whereIn('categories.id', $ids), // ryšys su sąlyga
]);
$providerProfile->load([...]);                             // jau gautam modeliui
```

`Model::preventLazyLoading()` dev'e meta klaidą, jei ryšys neužkrautas, o testai (`QueryCountTest`) tikrina, kad
užklausų skaičius su 2 ir su 17 teikėjų – tas pats. → https://laravel.com/docs/13.x/eloquent-relationships#eager-loading

#### 3. Puslapiavimas

```php
$query->paginate(20, pageName: 'puslapis')->withQueryString();
```

`paginate()` grąžina 20 įrašų ir bendrą skaičių (`COUNT(*)`). `pageName` – lietuviškas parametras
(`?puslapis=2`), `withQueryString()` – puslapių nuorodose išlieka filtrai. Paginator'ių perdavus per API Resource
kolekciją, Vue gauna `{ data, links, meta }`. Svarbu: rikiavimas turi būti **stabilus** – paskutinis rikiavimas
pagal `id`, kitaip vienodo reitingo teikėjai gali „šokinėti" tarp puslapių. WordPress analogas – `paginate_links()`.
→ https://laravel.com/docs/13.x/pagination

#### 4. Query scopes su parametrais ir `when()`

```php
#[Scope]
protected function servingCity(Builder $query, int $cityId): void
{
    $query->where(fn (Builder $inner) => $inner          // skliaustai aplink OR!
        ->where('serves_whole_country', true)
        ->orWhereIn('id', fn ($pivot) => $pivot->select('provider_profile_id')->from('city_provider_profile')->where('city_id', $cityId)));
}

ProviderProfile::query()->active()->when($filters->city, fn ($q, $city) => $q->servingCity($city->id));
```

Scope – pakartotinai naudojama užklausos dalis su prasmingu vardu. `when()` prideda sąlygą tik tada, kai reikšmė
yra (vietoj `if`). Be skliaustų `status = 'active' AND whole_country OR id IN (…)` rastų ir neaktyvius.
WordPress analogas – `WP_Query` argumentai, tik tipizuoti ir sujungiami.
→ https://laravel.com/docs/13.x/eloquent#local-scopes · https://laravel.com/docs/13.x/queries#conditional-clauses

#### 5. API Resources

Klasė, kuri nusprendžia, kokie modelio laukai keliauja į Vue: `ProviderCardResource::collection($paginator)`.
Viską, ko nėra `toArray()`, lankytojas nemato – net jei modelyje yra (el. paštas, kreditai). Vienam įrašui
naudojam `->resolve()`, kad gautume masyvą be `data` apvalkalo. Paveldėjimas: `CategoryProviderCardResource`
prideda kainą. WordPress analogas – REST API `prepare_item_for_response()`.
→ https://laravel.com/docs/13.x/eloquent-resources

#### 6. Form Request ir `failedValidation()`

Form Request laiko taisykles atskirai nuo controller'io. Įprastai nepraėjus validacijai jis nukreipia atgal su
klaidomis; kataloge perrašėm `failedValidation()` – įsimenam neteisingus parametrus ir juos ignoruojam
(`?rikiuoti=bet-kas` → numatytasis rikiavimas). `Rule::in(…)` miestų sąrašą ima iš cache, o ne `exists:` (be
papildomos DB užklausos). → https://laravel.com/docs/13.x/validation#form-request-validation

#### 7. Cache, observer'iai ir event discovery

```php
Cache::rememberForever('catalog:categories:v1', fn () => CategoryTree::loadRows());
Cache::forget('catalog:categories:v1');
```

- `rememberForever` – duomenys be galiojimo laiko; teisingumą užtikrina išvalymas, kai duomenys keičiasi.
- **Observer** (`#[ObservedBy([CatalogCacheObserver::class])]` ant `Category`, `City`, `Region`) – kodas po
  kiekvieno `saved` / `deleted`, kad ir iš kur keistų (Filament, tinker). `ShouldHandleEventsAfterCommit` – tik po
  transakcijos patvirtinimo. WordPress analogas – `save_post` hook'as, kuris išvalo transient'ą.
- **Seed'ai vykdomi be model events** (`WithoutModelEvents`), todėl po `db:seed` cache išvalo
  `FlushCatalogCacheAfterSeeding` listener'is – Laravel jį randa pats pagal `handle(CommandFinished $event)`
  parametro tipą (event discovery).
- `#[Scoped]` – vienas `CatalogCache` objektas per užklausą: cache skaitomas ir medis sudedamas tik kartą.
- **Store ir raktai:** dev'e `database`, produkcijoje Redis. `database` ir `file` store'ai tag'ų nepalaiko, todėl
  raktai fiksuoti, o `v1` leidžia pakeitus duomenų formą tiesiog pakelti versiją. WordPress analogas –
  `set_transient()` / `get_transient()`.
  → https://laravel.com/docs/13.x/cache · https://laravel.com/docs/13.x/eloquent#observers ·
  https://laravel.com/docs/13.x/events#event-discovery

#### 8. Paieška tekstu: `whereFullText` ir LIKE

```php
if ($query->getModel()->getConnection()->getDriverName() === 'mysql') {
    $query->whereFullText(['display_name', 'headline', 'description'], '+plytel* +klijavim*', ['mode' => 'boolean']);
} else {
    $query->where(fn ($q) => $q->orWhereLike('display_name', '%plytel%')->orWhereLike(...)); // SQLite
}
```

FULLTEXT indeksas yra tik MySQL (migracija), todėl SQLite (dev, testai) naudoja `LIKE`. `whereFullText`
stulpeliai turi sutapti su indekso stulpeliais. Boolean režime `+` – žodis privalomas, `*` – bet kokia pabaiga.
Vartotojo tekstą išvalom (`SearchTerms`): paliekam tik raides ir skaitmenis, kad `+ - " * %` negalėtų pakeisti
užklausos prasmės. → https://laravel.com/docs/13.x/queries#full-text-where-clauses

#### 9. SEO: title, description, canonical, robots

- `<Head>` (Inertia) Vue komponente keičia `<title>` ir `<meta>`; `head-key` neleidžia žymoms dubliuotis.
- Be SSR pirmame HTML žymų nebūtų – todėl `app.blade.php` jas išveda iš `seo` prop'o su tais pačiais
  `data-inertia` raktais, ir Vue jas perima.
- **Canonical** – pagrindinis adresas be filtrų (`?patikrinti=1` – to paties puslapio variantas), bet su
  `?puslapis=N`. **noindex** – paieškos rezultatams ir tuštiems „paslauga mieste" puslapiams („plonas" turinys).
- „Plytelių klijavimas **Vilniuje**" – iš `cities.name_locative`. `meta_title` / `meta_description` – kaip Yoast
  laukai WordPress'e: įrašyti pirmenybę turi, tušti – sugeneruojami.
  → https://inertiajs.com/title-and-meta · https://developers.google.com/search/docs/crawling-indexing/canonicalization

#### 10. Inertia: filtrai, dalinis perkrovimas

- `router.get(url, {}, { preserveState: true, preserveScroll: true, replace: true })` – pakeitus filtrą puslapis
  neperkraunamas, slinkties vieta išlieka, o „Atgal" grąžina į ankstesnį puslapį, ne per kiekvieną filtrą.
- `<Link :only="['reviews']" preserve-scroll>` – atsiliepimų puslapiavime iš serverio imamas tik `reviews` prop'as.
- Wayfinder: `categoryCity({ category: 'plyteliu-klijavimas', city: 'vilnius' })`, `show.url(slug, { query })`.
  → https://inertiajs.com/manual-visits · https://inertiajs.com/partial-reloads

#### 11. Larastan ir `casts()` metodas

Be `parseModelCastsMethod: true` (`phpstan.neon`) Larastan nemato `casts()` metode aprašytų cast'ų ir mano, kad
`$profile->status` yra `string`, o ne `ProviderStatus`. Įjungus – tipai teisingi visame projekte.

#### 12. Lietuviškas formatavimas

- PHP: `Collator('lt_LT')` rikiuoja pagal lietuvių abėcėlę (Š po S, Ž po Z; paprastas `sort()` jas nukeltų į galą).
- Vue (`resources/js/lib/format.ts`): `Intl.NumberFormat('lt-LT', { style: 'currency', currency: 'EUR' })` →
  „15,50 €" (pinigai props'uose – centais), `Intl.PluralRules('lt')` → „1 atsiliepimas / 2 atsiliepimai /
  10 atsiliepimų", datos Lietuvos laiku (`timeZone: 'Europe/Vilnius'`).

### Naudingos komandos

| Komanda                                                              | Ką daro                                                |
| -------------------------------------------------------------------- | ------------------------------------------------------ |
| `php artisan route:list --path=paslaugos`                            | maršrutai su parametrais ir controller'iais            |
| `php artisan cache:forget catalog:categories:v1`                     | išvalo vieną cache raktą (`cache:clear` – visą)        |
| `php artisan event:list`                                             | įvykiai ir listener'iai (matosi ir rasti automatiškai) |
| `php artisan wayfinder:generate --with-form`                         | pergeneruoja Vue maršrutų funkcijas be Vite            |
| `php artisan tinker` → `DB::enableQueryLog(); …; DB::getQueryLog()`  | kokias SQL užklausas vykdo kodas                       |
| `php artisan test tests/Feature/Catalog`                             | katalogo testai                                        |
| `DB_CONNECTION=mysql DB_DATABASE=… php artisan migrate:fresh --seed` | ta pati DB MySQL'e (FULLTEXT kelias)                   |
| MySQL: `EXPLAIN SELECT …`                                            | kurį indeksą naudoja užklausa                          |

### Dažnos klaidos

- **`Call to undefined method Category::cities()`** – du `{modelis:slug}` parametrai viename URL be
  `->withoutScopedBindings()`.
- **Cache grąžina `__PHP_Incomplete_Class`** – į cache įdėtas modelis ar kitas objektas; saugok masyvus.
- **Pakeitei kategoriją, o svetainė rodo seną** – cache neišvalytas (nėra observer'io arba keista
  `Model::query()->update()`, kuris model events nekviečia; seed'ai – be model events).
- **`OR` be skliaustų** – `where(...)->orWhere(...)` „išsprūsta" iš kitų sąlygų; grupuok `where(fn ($q) => …)`.
- **Antrame puslapyje dingo filtrai** – pamirštas `withQueryString()`.
- **Tas pats teikėjas dviejuose puslapiuose** – rikiavimas be paskutinio `id`.
- **FULLTEXT neranda trumpų žodžių** – InnoDB neindeksuoja trumpesnių nei 3 simbolių (`innodb_ft_min_token_size`).
- **SQLite `LIKE` skiria „Š" ir „š"** – didžiųjų ir mažųjų raidžių nepaiso tik lotyniškoms raidėms (tik dev).
- **Vienas API Resource grąžina `{ data: {…} }`** – Inertia prop'ui naudok `->resolve()`.
- **Vartotojo nuoroda `:href`** – `javascript:` adresas būtų XSS; svetainę leidžiam tik su `http(s)`.
- **Užklausų skaičius teste „šokinėja"** – factory sukurtas miestas išvalo geografijos cache; skaičiuok po
  „apšildymo" užklausos.

---

## Etapas 5 – Užklausos ir pasiūlymai (platformos šerdis)

> Užklausos nuotraukos, kvietimas palikti atsiliepimą, teikėjo prašymas pažymėti darbą atliktu, 60 d. priminimas
> ir rate limiting perkelti į Etapą 6; kreditų pirkimas – Etapas 7.

### Ką darėm ir kodėl

- **Būsenų mašina enum'uose** – `ServiceRequestStatus` ir `OfferStatus` gavo `allowedTransitions()`,
  `canTransitionTo()`, `isFinal()`. Kiekvienas perėjimas – atskira **Action** klasė (`app/Actions/ServiceRequests`,
  `app/Actions/Offers`), vykdoma `DB::transaction()` viduje. Lentelė „perėjimas → Action → Policy → pranešimai" –
  `docs/STATES.md` 4 sk.
- **Kreditų ledger** – `App\Services\Credits\CreditLedger` (`debit`, `credit`, `refund`). Vienintelė vieta, kuri keičia
  `credits_balance`; ją naudos ir Etapas 7 (pirkimai, prenumeratos, admin koregavimai).
- **Atitikimas** – `App\Services\Matching\ProviderMatcher`: viena taisyklė „kuris teikėjas tinka kuriai užklausai",
  naudojama pranešimams, teikėjo srautui, Policy ir pasiūlymo siuntimui.
- **Daugiažingsnė užklausos forma** (`/uzklausos/nauja`): paslauga (paieška kategorijų medyje) → aprašymas →
  vieta ir laikas → biudžetas ir peržiūra. Kiekvienas žingsnis tikrinamas serveryje per **Precognition**.
- **Automatinis moderavimas** – `App\Services\Moderation\AutoModerator`: paskelbiama iškart, jei el. paštas patvirtintas
  ir tekste nėra nuorodų, el. pašto adresų ar telefono numerių. Kitaip – `pending`, ir užklausą tvirtina administratorius
  Filament'e (`/admin/uzklausos`), kur matomos tos pačios priežastys. Patvirtinus el. paštą, laukiančios užklausos
  patikrinamos dar kartą (listener'is `PublishPendingRequestsAfterVerification`).
- **Pranešimai** – 7 Notification klasės (mail + database), siunčiamos eilėje, pagal `users.notification_settings`
  (struktūra – `DB_SCHEMA.md` → users). Paskelbus užklausą job'as `NotifyMatchingProviders` praneša visiems tinkamiems
  teikėjams, skaitydamas juos dalimis po 500.
- **Puslapiai**: „Mano užklausos", užklausos puslapis klientui ir teikėjui (adresas ir kontaktai – tik išrinktam
  teikėjui), pasiūlymo puslapis (atidarius nustatomas `viewed_at`), teikėjo srautas su filtrais, „Mano pasiūlymai",
  varpelis antraštėje, `/pranesimai`, nustatymai → „Pranešimai".
- **Scheduler** – `php artisan service-requests:expire` kas valandą uždaro pasibaigusias užklausas ir grąžina kreditus
  tik už neatidarytus pasiūlymus.
- **Nauja migracija** `add_cancellation_reason_to_service_requests_table` – atmetimo / atšaukimo priežastis.
- **Testai**: 150 naujų (iš viso 266): visas srautas per HTTP, visos `STATES.md` 3 sk. grąžinimo taisyklės, Policies,
  Filament veiksmai, varpelis, laiškų tekstai, Scheduler.

### Išmoktos sąvokos

#### 1. Daugiažingsnė forma ir Precognition

Visi formos laukai laikomi **vienoje** `useForm()` būsenoje, o žingsniai tik rodo jų dalį (`v-show`). Kad nereikėtų
validacijos taisyklių rašyti dukart (PHP ir JavaScript), „Toliau" mygtukas siunčia **Precognition** užklausą: tą patį
`POST /uzklausos` su antraštėmis `Precognition: true` ir `Precognition-Validate-Only: title,description`. Laravel
paleidžia `StoreServiceRequestRequest` taisykles tik tiems laukams ir grąžina 422 su klaidomis arba 204 – controller'is
nevykdomas, niekas neišsaugoma.

```ts
const form = useForm(store(), { category_id: null, title: '', … });
form.validate({ only: ['title', 'description'], onSuccess: () => step.value++ });
```

Maršrutui reikia middleware `HandlePrecognitiveRequests`. Alternatyvos: validuoti tik paskutiniame žingsnyje
(vartotojas klaidas pamato per vėlai) arba kiekvieną žingsnį saugoti DB kaip juodraštį (sudėtingiau, reikia „pusiau
sukurtų" užklausų valymo). → https://laravel.com/docs/13.x/precognition · https://inertiajs.com/forms#precognition

#### 2. Form Request: ne tik taisyklės

- `authorize()` gali grąžinti `Gate::inspect(...)` – atsisakius vartotojas mato priežastį („Šiai užklausai pasiūlymą jau
  išsiuntėte"), o ne bendrą „403".
- `attributes()` ir `messages()` – lietuviški laukų pavadinimai ir pranešimai iš `lang/lt/service_requests.php`.
- `Rule::when($this->filled('budget_min'), 'gte:budget_min')` – sąlyginė taisyklė (be jos `gte` lygintų su `null`).
- Metodas `toServiceRequestAttributes()` paverčia formos duomenis modelio atributais: **eurai → centai**
  (`(int) round($price * 100)`, nes `12.3 * 100 = 1229.999…`).
  → https://laravel.com/docs/13.x/validation#form-request-validation

#### 3. Action klasės

Viena verslo operacija = viena klasė su vienu viešu metodu `handle()`. Controller'is lieka 5–10 eilučių:
`Gate::authorize()` → `$action->handle()` → flash pranešimas → nukreipimas. Tą pačią operaciją kviečia ir controller'is,
ir Filament veiksmas, ir Scheduler komanda, ir testai. Priklausomybės (`CreditLedger`, `ProviderMatcher`) gaunamos per
konstruktorių – Laravel **service container** jas sukuria pats. Tai ne Laravel „funkcija", o susitarimas (kaip
WordPress'e logiką iškelti iš šablono į klasę). → https://laravel.com/docs/13.x/container

#### 4. Būsenų mašina enum'e

```php
public function allowedTransitions(): array
{
    return match ($this) {
        self::Pending => [self::Open, self::Cancelled],
        self::Open => [self::InProgress, self::Cancelled, self::Expired],
        …
    };
}
```

Kiekviena Action, prieš keisdama būseną, klausia `canTransitionTo()`. Jei negalima – `InvalidStateTransitionException`,
kurios `render()` metodas vartotoją grąžina atgal su klaidos pranešimu (Laravel pats kviečia `render()`).
Alternatyva – `spatie/laravel-model-states` (`STATES.md` pradžia). → https://laravel.com/docs/13.x/errors#renderable-exceptions

#### 5. Policies

`ServiceRequestPolicy` (`view`, `create`, `cancel`, `complete`, `publish`, `viewAny`) ir `OfferPolicy` (`create`, `view`,
`withdraw`, `accept`, `decline`). Laravel jas randa pagal pavadinimą. Naudojimas:

- controller'yje `Gate::authorize('accept', $offer)` (atsisakius – 403);
- Vue puslapiui siunčiam `can: { accept: $user->can('accept', $offer) }`, kad mygtukas būtų rodomas tik kai galima;
- **Filament** pats naudoja tą pačią Policy (`viewAny` – ar rodyti meniu punktą, `->authorize('publish')` veiksmams).

Policy tikrina ir būseną (kad UI būtų teisingas), bet galutinai ją dar kartą patikrina Action, užrakinusi eilutę.
`Response::deny('priežastis')` leidžia atsisakymą paaiškinti. → https://laravel.com/docs/13.x/authorization#creating-policies

#### 6. DB transakcijos ir pesimistinis užraktas (`lockForUpdate`)

**Transakcija** – „viskas arba nieko": pasiūlymas, kreditų nurašymas ir `offers_count + 1` įvyksta kartu arba
neįvyksta visai. → https://laravel.com/docs/13.x/database#database-transactions

**Kodėl vien transakcijos neužtenka.** Teikėjas turi 1 kreditą ir vienu metu (du skirtukai) siunčia du pasiūlymus.
Be užrakto MySQL'e:

| Laikas | Užklausa A                     | Užklausa B                     |
| ------ | ------------------------------ | ------------------------------ |
| 1      | `SELECT credits_balance` → 1   |                                |
| 2      |                                | `SELECT credits_balance` → 1   |
| 3      | 1 ≥ 1, nurašo → `UPDATE … = 0` |                                |
| 4      |                                | 1 ≥ 1, nurašo → `UPDATE … = 0` |

Abu pasiūlymai išsiųsti, nurašytas tik vienas kreditas (arba, jei rašytume `credits_balance - 1`, balansas taptų −1).
Su `lockForUpdate()` (`SELECT … FOR UPDATE`) B 2-ame žingsnyje **laukia**, kol A baigs transakciją, ir tada perskaito jau
0 – „Nepakanka kreditų". Užraktas laikomas tik iki `COMMIT`, todėl transakcijos turi būti trumpos (pranešimus siunčiam
po jų). → https://laravel.com/docs/13.x/queries#pessimistic-locking

**Užraktų tvarka.** Visos operacijos rakina ta pačia tvarka: užklausa → pasiūlymai → teikėjai (didėjančia ID tvarka).
Jei viena rakintų A → B, o kita B → A, abi lauktų viena kitos amžinai (**deadlock**); MySQL tai aptinka ir vieną
transakciją atšaukia, bet geriau to išvengti.

**SQLite (dev ir testai).** `lockForUpdate()` SQLite'e nieko nedaro – SQLite rašymus ir taip vykdo po vieną (visa DB
užrakinama rašymo metu). Todėl testuose tikrinam tai, ką galima: du nuoseklūs siuntimai su pasenusiu modeliu, kai
kreditų užtenka vienam – antras atmetamas, balansas ne neigiamas, ledger suma = balansas. Tikrą lygiagretumą reikėtų
tikrinti MySQL'e dviem procesais (Etapas 8).

**Papildomi saugikliai:** `UNIQUE(service_request_id, provider_profile_id)` – net jei kas nors praslystų, DB neleis
antro pasiūlymo; `credits_balance` – `unsigned`, MySQL neleis neigiamo.

#### 7. Ledger ir idempotentiškas grąžinimas

`CreditLedger::refund($offer)` neieško „ar jau grąžinta" atskiru stulpeliu – jis susumuoja visus to šaltinio ledger
įrašus (−2 nurašymas + 2 grąžinimas = 0). Jei suma 0 – grąžinti nebėra ką. Todėl kvietimas du kartus (pvz. pakartotas
job'as) negrąžins dvigubai. **Idempotentiškumas** = operaciją galima saugiai pakartoti. Etape 7 tas pats principas
saugos nuo dvigubo Paysera callback'o.

#### 8. Jobs ir eilės

`NotifyMatchingProviders implements ShouldQueue` – darbas atidedamas į eilę, klientas nelaukia. Svarbu:

- `Queueable` trait'e yra `SerializesModels`: į eilę įrašomas tik modelio ID, vykdant modelis paimamas iš DB
  (todėl job'as tikrina, ar užklausa vis dar `open`);
- `dispatch(...)->afterCommit()` – į eilę patenka tik transakcijai pavykus;
- `chunkById(500, …)` – teikėjai skaitomi dalimis, ne visi iš karto;
- `$tries = 3` – nepavykus bandoma dar kartą.

Dev'e eilė – `database` (lentelė `jobs`), vykdo `php artisan queue:work` (paleidžia `composer run dev`). Testuose
`QUEUE_CONNECTION=sync` – job'ai vykdomi iškart. WordPress analogas – `wp_schedule_single_event()`, tik patikimesnis.
→ https://laravel.com/docs/13.x/queues

#### 9. Notifications: vienas pranešimas – keli kanalai

```php
class NewOffer extends BaseNotification   // ShouldQueue
{
    public function via(object $notifiable): array { /* ['mail', 'database'] pagal nustatymus */ }
    public function toMail(object $notifiable): MailMessage { … }
    public function toArray(object $notifiable): array { return ['offer_id' => …, 'message' => '…']; }
}
$client->notify(new NewOffer($offer));
```

- `database` kanalas įrašo `toArray()` į lentelę `notifications` – iš jos varpelis (`$user->unreadNotifications()`,
  `->markAsRead()`).
- `via()` skaito `users.notification_settings` (`App\Support\NotificationSettings`); nepatvirtintu el. paštu laiškų
  nesiunčiam.
- Eilėje modelis atkuriamas be ryšių – `toMail()` juos užkrauna `loadMissing()` (ne lazy loading).
- Kur veda paspaudimas, skaičiuojama iš `type` + `data` (`NotificationTarget`), todėl veikia ir seed'ų pranešimai.
  → https://laravel.com/docs/13.x/notifications

#### 10. Events ir listeners (≈ WordPress hooks)

Patvirtinus el. paštą Laravel paskelbia įvykį `Illuminate\Auth\Events\Verified`. Mūsų listener'is
`PublishPendingRequestsAfterVerification` į jį reaguoja: laukiančias užklausas patikrina dar kartą ir paskelbia.
Listener'ių registruoti nereikia – Laravel randa juos `app/Listeners` pagal `handle()` parametro tipą (kaip
`add_action('user_verified', …)`). → https://laravel.com/docs/13.x/events

#### 11. Scheduler (≈ `wp_cron`, tik patikimesnis)

```php
// routes/console.php
Schedule::command('service-requests:expire')->hourly()->withoutOverlapping()->onOneServer();
```

Serveryje vienas cron įrašas kas minutę paleidžia `php artisan schedule:run`, o Laravel sprendžia, kas vykdoma dabar.
Skirtingai nei `wp_cron`, nepriklauso nuo lankytojų. `withoutOverlapping()` – nepradėti, jei ankstesnis dar dirba;
`onOneServer()` – keliuose serveriuose vykdyti tik viename. Komandos klasė – su PHP atributais
`#[Signature('service-requests:expire')]`. → https://laravel.com/docs/13.x/scheduling

#### 12. Route model binding pagal slug ir `scopeBindings()`

`Route::get('uzklausos/{serviceRequest:slug}', …)` – Laravel pats suranda užklausą pagal `slug` arba grąžina 404.
`/uzklausos/{serviceRequest:slug}/pasiulymai/{offer}` su `->scopeBindings()` – pasiūlymas turi priklausyti tai
užklausai, kitaip 404 (negalima „pasižiūrėti" svetimo pasiūlymo pakeitus ID). Wayfinder'is Vue pusėje sugeneruoja
`show({ slug })`. → https://laravel.com/docs/13.x/routing#implicit-model-binding-scoping

#### 13. API Resources – tik reikalingi laukai

`ServiceRequestResource::make($request)->withPrivateDetails($isChosen)->resolve()` – adresas į Vue patenka tik klientui
ir išrinktam teikėjui (`$this->when(...)` lauką visai praleidžia). Sąraše pasiūlymo žinutė – tik ištrauka, nes visa
žinutė = „atidarė" (`viewed_at`), nuo to priklauso kreditų grąžinimas. Puslapiuotas sąrašas
(`::collection($paginator)`) Vue pusėje turi `data`, `links`, `meta`. → https://laravel.com/docs/13.x/eloquent-resources

#### 14. Inertia: bendri props, `useHttp`, flash

- `HandleInertiaRequests::share()` – `notifications.unread_count` kiekviename puslapyje. Reikšmė – **closure**: ji
  vykdoma tik kai prop'o reikia (dalinis perkrovimas su `only` jos neskaičiuoja). → https://inertiajs.com/shared-data
- Varpelio sąrašas užkraunamas tik jį atidarius – `useHttp().get(latest.url())` (Inertia 3 JSON užklausa, puslapis
  nepersikrauna). → https://inertiajs.com/http-requests
- `Inertia::flash('toast', [...])` – vienkartinis pranešimas po nukreipimo (rodomas kaip toast).

#### 15. Filament: veiksmai, skirtukai, infolist

- `Action::make('approve')->requiresConfirmation()->visible(...)->authorize('publish')->action(...)` – mygtukas su
  patvirtinimo langu; `->schema([Textarea::make('reason')->required()])` – veiksmas su forma (atmetimo priežastis).
- `ListRecords::getTabs()` – skirtukai „Laukia patvirtinimo / Atviros / Visos"; `getNavigationBadge()` – skaitliukas meniu.
- `ViewRecord` + `Infolist` (`TextEntry`, `IconEntry`, `Section`) – peržiūros puslapis be redagavimo.
- Enum'as su `HasLabel` ir `HasColor` – `TextColumn::make('status')->badge()` pats parodo lietuvišką pavadinimą ir spalvą.
  → https://filamentphp.com/docs/5.x/actions/overview

#### 16. Testai

- `Notification::fake()` + `Notification::assertSentTo($user, NewOffer::class, fn ($n) => …)` – pranešimai
  nesiunčiami, tik įsimenami. `Queue::fake()` + `Queue::assertPushed(NotifyMatchingProviders::class)`.
- `assertInertia(fn (Assert $page) => $page->component('offers/Show')->where('can.accept', true)->missing('serviceRequest.address'))`.
- `assertInertiaFlash('toast.message', '…')` – flash pranešimas.
- Filament: `Livewire::test(ViewServiceRequest::class, [...])->callAction('approve')`, lentelės veiksmui –
  `TestAction::make('reject')->table($record)`.
- `$this->freezeSecond()` – laikas „sustabdomas", kad būtų galima lyginti `published_at` su `now()`.
- Pagalbininkai `Tests\Support\Marketplace::eligibleProvider()` – tinkamas teikėjas užklausai.

#### 17. Larastan ir modelių cast'ai

Larastan pagal nutylėjimą žiūri tik į `casts()` metodo grąžinamą tipą (`array`), todėl `$request->status` laikė
`string`. `phpstan.neon` → `parseModelCastsMethod: true` – Larastan perskaito `casts()` turinį ir žino, kad tai enum'as.

### Naudingos komandos

| Komanda                                                    | Ką daro                                                   |
| ---------------------------------------------------------- | --------------------------------------------------------- |
| `php artisan queue:work`                                   | vykdo eilės job'us (pranešimai, laiškai)                  |
| `php artisan queue:work --stop-when-empty`                 | įvykdo, kas eilėje, ir baigia                             |
| `php artisan queue:failed` / `queue:retry all`             | nepavykę job'ai ir jų kartojimas                          |
| `php artisan service-requests:expire`                      | rankiniu būdu uždaro pasibaigusias užklausas              |
| `php artisan schedule:list`                                | suplanuotos užduotys ir kada jos vyks                     |
| `php artisan schedule:work`                                | dev'e vykdo Scheduler'į kas minutę (vietoj cron)          |
| `php artisan route:list --path=uzklausos`                  | Etapo 5 maršrutai                                         |
| `php artisan make:notification NewOffer`                   | nauja Notification klasė                                  |
| `php artisan make:policy OfferPolicy --model=Offer`        | nauja Policy                                              |
| `php artisan make:job NotifyMatchingProviders`             | naujas job'as                                             |
| `php artisan make:filament-resource ServiceRequest --view` | Filament resource su peržiūros puslapiu                   |
| `php artisan wayfinder:generate`                           | perkurti Vue maršrutų funkcijas (daro ir `npm run build`) |

### Dažnos klaidos

- **Pranešimas siunčiamas transakcijos viduje.** Jei transakcija vėliau nepavyks, žmogus gaus žinią apie neįvykusį
  dalyką. Pranešimus siunčiam po `DB::transaction()`, job'ams – `->afterCommit()`.
- **`lockForUpdate()` be transakcijos** – užraktas atleidžiamas iškart po `SELECT`, t. y. nieko nesaugo.
- **`chunk()`, kai cikle keičiama ta pati sąlyga** (`status = open` → `expired`) – kitas „puslapis" (`OFFSET`)
  praleidžia dalį eilučių. Naudok `chunkById()`: jis tęsia nuo paskutinio ID (`WHERE id > ?`).
- **Būsena tikrinama tik Policy'je.** Tarp puslapio atidarymo ir paspaudimo ji gali pasikeisti – Action turi tikrinti
  dar kartą, užrakinusi eilutę.
- **`loadMissing('ryšys:id,name')` pranešime** – modelio ryšys lieka su dalimi stulpelių, ir kitas kodas (pvz.
  `$offer->providerProfile->user`) gauna `null`. Bendrai naudojamiems modeliams stulpelių neribok.
- **`gte:budget_min`, kai `budget_min` tuščias** – Laravel lygina su `null` ir atmeta. Naudok `Rule::when(...)`.
- **Precognition testas be JSON** – `post()` gauna 302 nukreipimą; naudok `postJson()`, tada 422 / 204.
- **Puslapio komponento nėra Vite manifeste** – po naujo `.vue` puslapio sukūrimo testams ir naršyklei reikia
  `npm run build` (arba `npm run dev`).
- **Notification `type` pervadinimas** – DB saugomas klasės vardas (`App\Notifications\NewOffer`); pervadinus klasę
  seni pranešimai „pasimestų". Tipą galima užfiksuoti metodu `databaseType()`.
