# Mokymosi užrašai

Šis failas pildomas **kiekvieno etapo pabaigoje**: išmoktos sąvokos, naudingos artisan komandos, dažnos klaidos,
„PADARYK PATS" užduotys ir pasitikrinimo klausimai.

Kaip naudoti:
- Prieš pradėdamas naują etapą, perskaityk ankstesnio etapo skiltį.
- Savo atsakymus ir pastabas rašyk skiltyje „Mano atsakymai ir pastabos". Tai tavo vieta: Claude ją
  tik skaito ir komentuoja, kai paprašai.
- Nuorodos veda į oficialią dokumentaciją (Laravel 12.x).

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
→ https://laravel.com/docs/12.x/migrations

#### 2. Eloquent ryšiai
Ryšys – modelio metodas, kuris aprašo, kaip lentelės susijusios. Tada rašom `$request->offers`, o ne SQL JOIN.

| Ryšys | Mūsų pavyzdys | Kur yra FK | Dokumentacija |
|---|---|---|---|
| `hasOne` / `belongsTo` (1:1) | `User` → `providerProfile` | `provider_profiles.user_id` | [one-to-one](https://laravel.com/docs/12.x/eloquent-relationships#one-to-one) |
| `hasMany` (1:N) | `ProviderProfile` → `offers` | `offers.provider_profile_id` | [one-to-many](https://laravel.com/docs/12.x/eloquent-relationships#one-to-many) |
| `belongsTo` (N:1, atvirkštinis) | `ServiceRequest` → `category` | `service_requests.category_id` | [inverse](https://laravel.com/docs/12.x/eloquent-relationships#one-to-many-inverse) |
| `belongsToMany` (N:M per pivot) | `ProviderProfile` ↔ `categories` (+ `price_from_cents`) | `category_provider_profile` | [many-to-many](https://laravel.com/docs/12.x/eloquent-relationships#many-to-many) |
| `hasManyThrough` | `User` → `offers` per `ProviderProfile` | – | [has-many-through](https://laravel.com/docs/12.x/eloquent-relationships#has-many-through) |
| `morphTo` / `morphMany` (polimorfinis) | `Complaint` → `reportable` (atsiliepimas, žinutė, profilis…) | `complaints.reportable_type` + `reportable_id` | [polymorphic](https://laravel.com/docs/12.x/eloquent-relationships#polymorphic-relationships) |
| Ryšys su savimi | `Category` → `parent` / `children` | `categories.parent_id` | `belongsTo`/`hasMany` į tą patį modelį |

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
→ https://laravel.com/docs/12.x/migrations#indexes

#### 4. Normalizacija ir denormalizacija
Normalizuota schema kiekvieną faktą saugo vienoje vietoje. Denormalizacija – sąmoningas dubliavimas dėl greičio
(`rating_avg`, `reviews_count`, `offers_count`, `credits_balance`). WordPress daro tą patį su
`wp_posts.comment_count`. Kaina: dublį reikia patikimai atnaujinti (observer, job, transakcija) ir tikrinti testu.

#### 5. Soft deletes
`deleted_at` vietoj tikro ištrynimo (WordPress analogas – „Šiukšliadėžė"). Eloquent automatiškai slepia tokius
įrašus, o `withTrashed()` juos parodo. → https://laravel.com/docs/12.x/eloquent#soft-deleting

#### 6. PHP enum ir cast'as statusams
`enum OfferStatus: string { case Pending = 'pending'; … }` + modelyje `casts()` → `'status' => OfferStatus::class`.
DB saugo paprastą eilutę, o kode turim tipą su metodais (`label()`, `isFinal()`). MySQL `ENUM` nenaudojam,
nes jį keisti sunku. → https://laravel.com/docs/12.x/eloquent-mutators#enum-casting

#### 7. Pinigai centais
`float` dvejetainėje sistemoje negali tiksliai saugoti 0,1, todėl kaupiasi apvalinimo klaidos. Sveikieji centai
visada tikslūs. Stulpelių pavadinimai baigiasi `_cents`, kad niekas nesupainiotų su eurais.

#### 8. Ledger, transakcijos ir užraktai
Kreditų pokyčiai įrašomi kaip nekeičiamos eilutės, o balansas – tik jų suma (cache). Kad du lygiagretūs
veiksmai nenurašytų kreditų dvigubai: `DB::transaction()` + `lockForUpdate()` teikėjo eilutei.
→ https://laravel.com/docs/12.x/database#database-transactions ·
https://laravel.com/docs/12.x/queries#pessimistic-locking

#### 9. Idempotencija
Operacija idempotentiška, jei pakartota kelis kartus duoda tą patį rezultatą kaip įvykdyta vieną kartą.
Mokėjimų tiekėjai callback'ą gali atsiųsti pakartotinai, todėl `UNIQUE(gateway, gateway_reference)` ir
statuso patikra garantuoja, kad kreditai bus užskaityti tik kartą.

#### 10. Polimorfiniai ryšiai ir morph map
Vienas ryšys gali rodyti į skirtingus modelius: skundas – į atsiliepimą, žinutę ar profilį. DB saugo dvi
reikšmes: `*_type` (kuris modelis) ir `*_id`. Su `Relation::enforceMorphMap()` vietoj `App\Models\Review`
saugom trumpą `review`.
→ https://laravel.com/docs/12.x/eloquent-relationships#custom-polymorphic-types

#### 11. ON DELETE taisyklės
`cascade` – ištrinti vaikus kartu, `restrict` – neleisti trinti tėvo, kol yra vaikų, `set null` – palikti vaiką
be nuorodos. Laravel'yje: `->cascadeOnDelete()`, `->restrictOnDelete()`, `->nullOnDelete()`.
→ https://laravel.com/docs/12.x/migrations#foreign-key-constraints

#### 12. Seeder, Factory, Faker
Factory – vieno įrašo receptas (testams). Seeder – DB užpildymo scenarijus. Faker – netikrų duomenų generatorius
(`APP_FAKER_LOCALE=lt_LT`). Dideliam kiekiui naudojamas masinis įterpimas (`DB::table()->insert()` dalimis),
o ne `create()` po vieną (`docs/SEEDING.md` 7 sk.).
→ https://laravel.com/docs/12.x/seeding · https://laravel.com/docs/12.x/eloquent-factories

### Naudingos artisan komandos (naudosim nuo Etapo 1–2)

| Komanda | Ką daro |
|---|---|
| `php artisan make:model ServiceRequest -mfs` | modelis + migracija (`m`) + factory (`f`) + seeder (`s`) |
| `php artisan make:migration add_city_id_to_users_table` | nauja migracija esamai lentelei keisti |
| `php artisan make:enum Enums/OfferStatus` | PHP enum klasė |
| `php artisan migrate` | paleidžia naujas migracijas |
| `php artisan migrate:status` | kurios migracijos paleistos, kurios ne |
| `php artisan migrate:rollback` | atšaukia paskutinę migracijų grupę |
| `php artisan migrate:fresh --seed` | ištrina **visas** lenteles, sukuria iš naujo ir užpildo (tik dev!) |
| `php artisan db:seed --class=CitySeeder` | paleidžia vieną seeder'į |
| `php artisan make:notifications-table` | sukuria `notifications` lentelės migraciją |
| `php artisan model:show User` | modelio stulpeliai, ryšiai, cast'ai |
| `php artisan db:show --counts` | DB informacija ir visos lentelės su eilučių skaičiumi |
| `php artisan db:table service_requests` | vienos lentelės stulpeliai, indeksai, FK |
| `php artisan tinker` | interaktyvi konsolė, kurioje galima bandyti Eloquent |

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

### PADARYK PATS (Etapas 0)

Užduotys aprašytos `ROADMAP.md` (Etapas 0). Atlikęs parašyk „peržiūrėk mano užduotį #N".

### Pasitikrinimo klausimai (Etapas 0)

1. Ryšys `ServiceRequest` ↔ `Offer`: kuris modelis turi `hasMany`, kuris `belongsTo`, ir kurioje lentelėje fiziškai
   yra FK stulpelis? Atidžiai – šioje poroje FK yra ne vienas.
2. Turim indeksą `(category_id, status, published_at)`. Kurios užklausos galės jį naudoti ir kodėl?
   a) `WHERE category_id = 5`
   b) `WHERE status = 'open'`
   c) `WHERE category_id = 5 AND status = 'open' ORDER BY published_at DESC`
   d) `WHERE published_at > '2026-01-01'`
3. Kodėl `rating_avg` ir `reviews_count` saugom `provider_profiles` lentelėje, nors juos galima apskaičiuoti iš
   `reviews`? Kokia to kaina ir kaip ją valdysim?
4. Kodėl pinigus saugom sveikais centais, o ne `float`? Kaip DB bus saugoma 24,90 €?
5. `reviews.service_request_id` gali būti NULL, bet turi UNIQUE indeksą. Kodėl tai neprieštarauja vienas kitam ir
   ką verslo prasme reiškia NULL šiame stulpelyje?

### Mano atsakymai ir pastabos

_(čia rašyk savo atsakymus)_
