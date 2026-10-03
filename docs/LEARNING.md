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
