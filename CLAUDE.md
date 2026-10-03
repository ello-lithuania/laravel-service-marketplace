# CLAUDE.md

Instrukcijos Claude Code (ir žmonėms) **kiekvienai sesijai**.
Kiekvienos sesijos pradžioje perskaityk šį failą ir `ROADMAP.md`.

---

## 1. Projektas

Didelė paslaugų platforma lietuvių kalba. Funkcionalumas kaip paslaugos.lt, bet su savo pavadinimu ir
dizainu (pavadinimas dar nepasirinktas – kode visada naudojam `config('app.name')`).

Pagrindinis srautas:

1. **Klientas** sukuria **užklausą** (darbo aprašymas, kategorija, miestas, biudžetas, terminas).
2. Tinkami **teikėjai** (pagal kategoriją ir aptarnavimo zoną) gauna pranešimą ir siunčia **pasiūlymus**.
   Pasiūlymas kainuoja **kreditus** (perkami paketais arba gaunami su **prenumerata**).
3. Klientas susirašinėja **žinutėmis**, išsirenka pasiūlymą, po darbo palieka **atsiliepimą**.
4. **Administratorius** per Filament panelę moderuoja užklausas, atsiliepimus, **skundus**, mato mokėjimus.

## 2. Vartotojas ir mokymosi tikslas

Vartotojas moka PHP ir WordPress, Laravel – tik pagrindus.
**Tikslas – ne tik sukurti projektą, bet ir pačiam išmokti Laravel.** Todėl visada galioja mokymosi režimas.

## 3. MOKYMOSI REŽIMAS (galioja visoms sesijoms)

> Atnaujinta vartotojo prašymu: be „PADARYK PATS" užduočių ir be pasitikrinimo klausimų.
> Claude viską kuria pats, o vartotojas mokosi iš paaiškinimų.

1. Prieš kiekvieną žingsnį trumpai paaiškink KĄ darom ir KODĖL, kokią Laravel sąvoką naudojam
   (migracija, Eloquent ryšys, policy, job ir t.t.) ir duok nuorodą į oficialią dokumentaciją.
2. Dirbk mažais žingsniais. Kai yra keli būdai, parodyk alternatyvas ir paaiškink, kodėl rinkomės šitą.
3. Viską kurk pats. Vartotojo neįtrauk į užduotis, klausimus ar atsakinėjimą. Etapo pabaigoje papasakok,
   kas ir kaip padaryta ir kodėl.
4. Vesk `docs/LEARNING.md`: kiekvieno etapo išmoktos sąvokos, naudingos artisan komandos, dažnos klaidos.
5. Commit'ai maži ir aiškiai aprašyti, kad vartotojas galėtų sekti istoriją.
6. Jei vartotojas pats paprašo peržiūrėti jo kodą, peržiūrėk jį ir paaiškink klaidas.

### Kaip tai taikyti praktiškai

- Dokumentacijos nuorodos – į **Laravel 12.x**: `https://laravel.com/docs/12.x/...`.
  Kitiems įrankiams – oficialios svetainės (žr. 10 sk.).
- Naują sąvoką pirmą kartą aiškink paprastais žodžiais. Kai tinka, palygink su WordPress
  (migracija ≈ `dbDelta()`, Eloquent ≈ `$wpdb`/`WP_Query`, events ≈ hooks, scheduler ≈ `wp_cron`,
  soft deletes ≈ „Šiukšliadėžė").
- Etapo santrauka vartotojui: kas sukurta (failai), kaip tai veikia ir kodėl padaryta būtent taip
  (su alternatyvomis). Rašyk paprastais žodžiais, be klausimų vartotojui.

## 4. Sesijos eiga

1. Perskaityk `CLAUDE.md` ir `ROADMAP.md`. Jei dirbi su DB, perskaityk ir `docs/DB_SCHEMA.md`, `docs/SEEDING.md`.
2. `ROADMAP.md` rask **pirmą nepažymėtą etapą** ir tęsk nuo pirmo nepažymėto jo punkto.
3. Dirbk mažais žingsniais, kiekvieną loginį žingsnį – atskiru commit'u.
4. Sesijos pabaigoje pažymėk atliktus punktus `ROADMAP.md`, papildyk `docs/LEARNING.md`, commit'ink ir push'ink.
5. Etapo pabaigoje parašyk santrauką: kas padaryta, kaip ir kodėl.
   **Kitą etapą pradėk tik tada, kai vartotojas parašo tęsti.**

## 5. Stack

| Sritis | Technologija | Pastaba |
|---|---|---|
| Backend | Laravel 12 (PHP ≥ 8.2) | |
| Vieša dalis ir paskyros | Inertia.js 2 + Vue 3 (`<script setup>`, Composition API) | oficialus Laravel 12 Vue starter kit |
| Admin panelė | Filament (veikia ant Livewire 3) | `/admin` |
| CSS | Tailwind CSS 4 | |
| DB | MySQL 8.4 LTS (prod/staging), SQLite (dev ir testai) | kodas turi veikti abiejose |
| Failai | spatie/laravel-medialibrary | viena polimorfinė `media` lentelė |
| Testai | Pest | |
| Kodo stilius | Laravel Pint | |
| Eilės | `database` (dev) → Redis (prod) | |
| Laiškai | `log` / Mailpit (dev) | |
| Mokėjimai | Paysera (pirmas), Stripe (vėliau) | už savos `PaymentGateway` sąsajos |

**Pastaba dėl stack'o.** Vartotojas nurodė: „Laravel, Inertia, Livewire, Vue". Interpretacija tokia:
viešoji dalis ir vartotojų paskyros daromos su **Inertia + Vue**, o **Livewire** naudojamas tik per **Filament**
admin panelę (Filament pastatytas ant Livewire). Viešoje dalyje dviejų frontend technologijų nemaišom.
Tikslias paketų versijas ir starter kit sprendimus (TypeScript ar JS, maršrutai Vue pusėje) užfiksuosim Etape 1.

## 6. Konvencijos

### Kalba
- **Kodas – angliškai**: klasės, metodai, kintamieji, lentelės, stulpeliai, maršrutų vardai (`service-requests.show`).
- **UI – lietuviškai.** Svetainė vienos kalbos, todėl Vue komponentuose tekstus rašom tiesiai lietuviškai.
  Laravel pusės tekstai (validacija, laiškai, pranešimai) laikomi `lang/lt/*.php`.
- **URL – lietuviški, be diakritikų**: `/paslaugos/{kategorija}`, `/meistrai/{slug}`, `/uzklausos/{slug}`.
- **Komentarai kode – lietuviškai**, trumpi, paaiškina KODĖL, o ne KĄ.

### Duomenų bazė
- Tiesos šaltinis – `docs/DB_SCHEMA.md`. Keičiant schemą pirma atnaujinamas dokumentas, tik tada kodas.
- Lentelės vadinamos daugiskaita, snake_case. Pivot lentelė – abu modeliai vienaskaita, abėcėlės tvarka
  (`category_provider_profile`).
- FK vadinami `{modelis}_id` arba prasminiu vardu (`client_id`, `author_id`) ir kuriami su `constrained()`.
  ON DELETE taisyklės – `DB_SCHEMA.md` 2.12 sk.
- Statusai – `string` stulpelis + PHP backed enum (`app/Enums`), **ne** MySQL `ENUM`.
- Pinigai – **sveikais centais**, stulpeliai `*_cents`, valiuta EUR.
- `*_at` – data ir laikas (timestamp), `*_date` – tik data. DB saugom UTC, rodom `Europe/Vilnius`.
- Polimorfiniai tipai saugomi trumpais vardais per `Relation::enforceMorphMap()`.
- Kodas turi veikti ir MySQL, ir SQLite. Tik MySQL turimi dalykai (pvz. FULLTEXT) apgaubiami
  `DB::getDriverName()` patikra.

### Laravel kodas
- Controller'iai ploni. Validacija – Form Request (`app/Http/Requests`). Autorizacija – Policy (`app/Policies`).
  Verslo logika – Action klasės (`app/Actions`, vienas viešas metodas `handle()`).
- Ilgi ar sunkūs darbai – Jobs (eilėse). Pranešimai – Notifications (`mail` + `database` kanalai).
- Modeliuose naudojam `$fillable` (ne `$guarded = []`), cast'us per `casts()` metodą, ryšiams nurodom
  grąžinamą tipą (`: BelongsTo`).
- `Model::preventLazyLoading(! app()->isProduction())`, kad N+1 klaidos išlįstų jau dev'e.
- Į Inertia props siunčiam tik reikalingus laukus (API Resource arba `->only()`), niekada viso modelio.
- Pinigų ir kreditų operacijos vyksta tik `DB::transaction()` viduje.

### Testai
- Pest. Kiekvienam vartotojo srautui – Feature testas. Testų DB – SQLite `:memory:` + `RefreshDatabase`.
- Prieš kiekvieną commit'ą: `php artisan test` ir `./vendor/bin/pint`.

### Git
- Maži commit'ai: vienas loginis pakeitimas = vienas commit'as.
- Formatas: `tipas(sritis): aprašymas lietuviškai`. Tipai: `feat`, `fix`, `docs`, `test`, `refactor`, `chore`, `style`.
  Pavyzdys: `feat(pasiulymai): kreditų nurašymas siunčiant pasiūlymą`.
- Etapo pabaigoje daromas atskiras commit'as su `ROADMAP.md` pažymėjimais.

## 7. Struktūra (planuojama; galutinė – po Etapo 1)

```
app/
  Actions/            verslo logika (SendOffer, AcceptOffer, PurchaseCredits…)
  Enums/              rolės ir statusai (UserRole, OfferStatus…)
  Filament/           admin panelės resursai
  Http/
    Controllers/      ploni controller'iai (Inertia::render)
    Middleware/
    Requests/         Form Request validacija
  Jobs/               eilių darbai (NotifyMatchingProviders…)
  Models/
  Notifications/
  Observers/
  Policies/
  Services/Payments/  mokėjimų sąsaja + Paysera / Stripe
database/
  data/               žinyniniai duomenys (apskritys, savivaldybės, kategorijos, tekstų bankai)
  factories/
  migrations/
  seeders/
docs/                 DB_SCHEMA.md, SEEDING.md, LEARNING.md
lang/lt/              Laravel tekstai lietuviškai
resources/js/
  pages/              Inertia puslapiai
  components/
  layouts/
routes/web.php
tests/Feature, tests/Unit
```

## 8. Dokumentai

| Failas | Kam |
|---|---|
| `ROADMAP.md` | etapai 0–8 su checkbox'ais – kur esam |
| `docs/DB_SCHEMA.md` | DB schema, ryšiai, indeksai, sprendimai |
| `docs/SEEDING.md` | testinių duomenų (seed'ų) planas |
| `docs/LEARNING.md` | mokymosi užrašai: sąvokos, komandos, dažnos klaidos |
| `docs/STATES.md` | užklausos ir pasiūlymo būsenų perėjimai ir kreditų grąžinimo taisyklės |

## 9. Komandos (veiks nuo Etapo 1)

```bash
composer run dev                                  # serveris + eilės + Vite + logai
php artisan migrate:fresh --seed                  # DB iš naujo su testiniais duomenimis
SEED_SCALE=0.05 php artisan migrate:fresh --seed  # greitas mažas seed'as dev'ui
php artisan test                                  # testai (Pest)
./vendor/bin/pint                                 # kodo stilius
php artisan tinker                                # interaktyvi konsolė
```

## 10. Oficiali dokumentacija

- Laravel 12: https://laravel.com/docs/12.x
- Inertia: https://inertiajs.com
- Vue 3: https://vuejs.org/guide/introduction.html
- Tailwind CSS: https://tailwindcss.com/docs
- Filament: https://filamentphp.com/docs
- Pest: https://pestphp.com/docs
- Laravel Media Library: https://spatie.be/docs/laravel-medialibrary
- Faker: https://fakerphp.org
