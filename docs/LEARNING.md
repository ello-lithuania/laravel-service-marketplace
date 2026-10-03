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
