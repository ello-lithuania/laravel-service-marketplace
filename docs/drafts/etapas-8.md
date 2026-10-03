## Etapas 8 – Administravimas, kokybė, paleidimas

> Juodraštis (lygiagretus darbas su Etapais 6 ir 7). Sujungus – perkelti į `docs/LEARNING.md` ir ištrinti.

**ROADMAP punktų būsena:**

- [x] Filament dashboard (statistika), moderavimo įrankiai, vartotojų blokavimas
- [x] Našumas: `EXPLAIN` pagrindinėms užklausoms su pilnu seed'u (MySQL), indeksų korekcijos, cache → `docs/PERFORMANCE.md`
- [x] Saugumas: autorizacijos auditas, failų įkėlimo validacija, saugos antraštės (rate limiting – Etapas 6)
- [x] BDAR: duomenų eksportas, paskyros anonimizavimas
- [x] SEO: `sitemap.xml`, struktūriniai duomenys (schema.org)
- [x] Logai, klaidų stebėsena, atsarginės kopijos → `docs/DEPLOYMENT.md` (Sentry – aprašytas, neįdiegtas)
- [x] Diegimas: serveris, eilių supervisor, cron (scheduler), CI/CD → `docs/DEPLOYMENT.md` (CI papildytas MySQL darbu, CD – eskizas)
- [x] Galutinė testų peržiūra (SQLite ir MySQL)
- [ ] `docs/LEARNING.md`: Etapas 8 + santrauka vartotojui – šis juodraštis, sujungs vedantysis

### Ką darėm ir kodėl

- **Admin skydelis** (`/admin`): suvestinė (vartotojai pagal rolę, registracijos, užklausos pagal būseną, pasiūlymai,
  konversija, moderavimo eilė, pajamos iš `payments`), diagrama „užklausos ir pasiūlymai per dieną" ir dvi darbo
  eilės (laukiančios užklausos, nepatikrinti teikėjai). Skaičiai – `App\Services\Admin\DashboardStats`, cache 5–10 min.
- **Vartotojų blokavimas** (`/admin/vartotojai`): „Užblokuoti" su priežastimi → `BanUser`: paskyra negali prisijungti,
  jau prisijungęs atjungiamas, teikėjo profilis tampa `suspended`, pasirinktinai atšaukiami pasiūlymai ir užklausos.
  „Atblokuoti" gali atkurti profilį.
- **Teikėjų moderavimas** (`/admin/teikejai`): peržiūra su paslaugomis, zonomis ir nuotraukomis; „Patikrintas",
  „Paslėpti", „Užblokuoti profilį", „Aktyvuoti" (`ChangeProviderStatus` leidžia tik administratoriaus perėjimus).
- **Našumas:** pilnas seed'as MySQL, kiekvieno pagrindinio puslapio SQL ir `EXPLAIN ANALYZE`. Trys indeksų korekcijos
  (viena migracija), puslapiavimo COUNT cache. Kategorijos puslapis 185 → 21 ms, pradžia 55 → 15 ms (`docs/PERFORMANCE.md`).
- **Saugumas:** saugos antraštės su CSP (`SecurityHeaders`), testas, kuris pereina **visus** maršrutus ir tikrina
  `auth` middleware (jis rado Filament eksporto maršrutus be `auth` – svečiui jie grąžindavo 500), failų validacijos
  peržiūra, Filament avataras be išorinio serviso.
- **BDAR:** Nustatymai → Privatumas: duomenų archyvas (ZIP su JSON ir nuotraukomis, kuriamas eilėje, saugomas 7 d.) ir
  paskyros ištrynimas = anonimizavimas (`AnonymizeUser`); taisyklės – `docs/DB_SCHEMA.md` 2.14.
- **SEO:** `sitemap.xml` (indeksas + dalys po 10 000 adresų), generuojamas `robots.txt`, schema.org JSON-LD
  (WebSite + SearchAction, BreadcrumbList, ProfessionalService su įvertinimu).
- **Priežiūra:** `/up` tikrina DB ir cache, Slack logams – atskiras lygis, `docs/DEPLOYMENT.md` – viskas apie serverį.
- **CI:** antras GitHub Actions darbas tą patį testų rinkinį leidžia su MySQL 8.4.

### Svarbiausi sprendimai ir alternatyvos

| Klausimas                             | Pasirinkta                                               | Alternatyva ir kodėl ne                                                                                                 |
| ------------------------------------- | -------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| Kaip neįleisti užblokuoto             | `Fortify::authenticateUsing` + middleware „web" grupėje  | tik middleware – žmogus prisijungtų ir iškart būtų išmestas be aiškios žinutės; tik login patikra – likę sesijos veiktų |
| Ar užblokuotą teikėją slėpti kataloge | profilis → `suspended` (katalogas jau rodo tik `active`) | `whereHas('user', banned_at IS NULL)` kiekvienoje katalogo užklausoje – papildomas JOIN 18 000 eilučių                  |
| Kategorijos COUNT lėtas               | cache 5 min.                                             | dar vienas indeksas (pablogino kitą užklausą), derived table (sąrašas lėtesnis), MySQL užuominos (tik MySQL, trapu)     |
| Admin skydelio skaičiai               | cache 5–10 min.                                          | indeksai `created_at` stulpeliams – lėtintų pasiūlymų įrašymą dėl kelių administratorių                                 |
| CSP Filament'ui                       | `'unsafe-eval'` ir `'unsafe-inline'` tik `/admin`        | nonce visur – Alpine.js (Filament pagrindas) vertina išraiškas per `new Function()`, be `unsafe-eval` neveikia          |
| Paskyros ištrynimas                   | anonimizavimas + soft delete                             | tikras DELETE – FK `restrict` neleistų, o kaskada sugriautų kitų istoriją, atsiliepimus ir buhalteriją                  |
| BDAR archyvas                         | eilėje, nuoroda – į nustatymus (reikia prisijungti)      | sinchroninis atsisiuntimas – aktyvaus teikėjo duomenims gali neužtekti HTTP laiko; tiesioginė nuoroda laiške – nutekėtų |
| `robots.txt`                          | maršrutas                                                | statinis failas – negali turėti `APP_URL` ir skirtis staging / produkcijoje                                             |
| JSON-LD ir Inertia                    | tas pats tekstas Blade'e ir `SeoHead.vue` su `head-key`  | tik Blade – Inertia head manager po hidratacijos tokią žymą ištrintų (ji pažymėta `data-inertia`)                       |
| `spatie/laravel-backup`               | neįdiegtas (mysqldump + restic aprašyti)                 | verta, kai serveris valdomas rankiniu būdu ir norisi kopijų iš Laravel su pranešimais                                   |

### Išmoktos sąvokos

#### 1. Filament widgets (valdikliai)

Valdiklis – Livewire komponentas skydelyje. `app/Filament/Widgets` klases Filament randa pats (`discoverWidgets`),
tvarką nurodo `$sort`, plotį – `$columnSpan`.

```php
class PlatformStatsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null; // numatyta – atnaujinti kas 5 s

    protected function getStats(): array
    {
        return [Stat::make('Vartotojai', '80 003')->description('Klientai 60 000 · …')->chart([3, 5, 2, 8])];
    }
}
```

- `ChartWidget` – Chart.js diagrama (`getType(): 'line'`, `getData()`, `getFilters()` – laikotarpio pasirinkimas).
- `TableWidget` – Filament lentelė skydelyje (`->query()`, `->recordUrl()` – paspaudus atidaro įrašą).
- Valdikliai kraunami **tingiai** (lazy): puslapis rodomas iškart, o kiekvienas valdiklis ateina atskira Livewire
  užklausa. Testuose: `assertSeeLivewire(Widget::class)` puslapiui ir `Livewire::test(Widget::class)` turiniui.
- WordPress analogas – `wp_add_dashboard_widget()`.
  → https://filamentphp.com/docs/5.x/widgets/overview

#### 2. Filament resursas be kūrimo ir redagavimo, veiksmai su forma

- `getPages()` tik su `index` ir `view` – nėra „Sukurti" ir „Redaguoti" (vartotojus kuria registracija, profilius – vedlys).
- `Action::make('ban')->schema([Textarea::make('reason')->required()])->authorize('ban')->action(fn (User $record, array $data) => …)` –
  mygtukas su forma modaliniame lange; `authorize()` kviečia Policy ir neturint teisės mygtuko nerodo.
- Peržiūrai ryšiai užkraunami `getRecordRouteBindingEloquentQuery()->with(...)->withCount(...)`, sąrašui –
  `getEloquentQuery()->with(...)` (kitaip `preventLazyLoading` meta klaidą).
- Infolist: `TextEntry::make('categories.name')->badge()` (ryšio reikšmės kaip ženkleliai), `ImageEntry`,
  `RepeatableEntry` (pvz. atlikti darbai su nuotraukomis).
  → https://filamentphp.com/docs/5.x/resources/overview · https://filamentphp.com/docs/5.x/actions/overview

#### 3. Fortify: savas prisijungimo tikrinimas

```php
Fortify::authenticateUsing(new AuthenticateUser); // grąžina User arba null
```

Callback'as gauna užklausą ir grąžina vartotoją (prisijungti) arba `null` (įprasta klaida). Užblokavimas tikrinamas
**po** slaptažodžio – kitaip žinantis tik el. paštą sužinotų, kad paskyra užblokuota.
→ https://laravel.com/docs/13.x/fortify#customizing-user-authentication

#### 4. Middleware: grupė ar globalus

- `$middleware->web(append: [...])` – tik „web" grupės maršrutams (Inertia puslapiai). Filament turi **savo**
  middleware sąrašą (`AdminPanelProvider`), todėl ten „web" grupės middleware neveikia.
- `$middleware->append(SecurityHeaders::class)` – globaliai: visiems atsakymams, ir Filament, ir klaidų puslapiams.
- `Router::gatherRouteMiddleware($route)` išskleidžia grupes ir alias'us į klases – taip testas mato, kas tikrai bus vykdoma.
  → https://laravel.com/docs/13.x/middleware

#### 5. EXPLAIN ir indeksai

- `EXPLAIN` – planas (kurį indeksą naudos, kiek eilučių **mano** perskaitysiąs), `EXPLAIN ANALYZE` – užklausą įvykdo
  ir parodo **tikrus** laikus ir eilučių skaičius kiekviename žingsnyje. WordPress analogas – Query Monitor įskiepis.
- Plano žodynas: `Index lookup` / `range scan` – gerai; `Table scan` – visa lentelė; `Sort` (filesort) – rūšiavimas
  atmintyje; `Covering index` – atsakymas vien iš indekso, lentelės skaityti nereikia; `Nested loop semijoin` /
  `Materialize with deduplication` – du būdai vykdyti `IN (SELECT …)`.
- **Stulpelių tvarka sudėtiniame indekse:** pirma lygybės sąlygos (`status`, `deleted_at IS NULL`), po jų – rikiavimo
  stulpeliai (`rating_avg`, `reviews_count`). InnoDB gale pats prideda pirminį raktą, todėl `ORDER BY …, id DESC`
  irgi „nemokamas". Pridėjus stulpelį **po** rikiavimo stulpelių, `id` nebebūtų iškart po jų ir filesort grįžtų.
- Mažuose duomenyse planai kitokie nei dideliuose: optimizatorius renkasi pagal statistiką ir net pagal tai, kiek
  indekso šiuo metu atmintyje. Todėl matuojam su realiu kiekiu (`SEED_SCALE=1`) ir `ANALYZE TABLE` po pakeitimų.
- Kiekvienas indeksas lėtina INSERT/UPDATE – indeksus dedam pagal **dažnas** užklausas, o retoms (admin) užtenka cache.
  → https://dev.mysql.com/doc/refman/8.4/en/explain.html · https://dev.mysql.com/doc/refman/8.4/en/multiple-column-indexes.html ·
  https://dev.mysql.com/doc/refman/8.4/en/order-by-optimization.html · https://dev.mysql.com/doc/refman/8.4/en/semijoins.html

#### 6. Cache: ką, kiek laiko, kaip raktas

```php
Cache::remember('catalog:count:v1:'.sha1($query->toBase()->toRawSql()), now()->addMinutes(5), fn () => $query->toBase()->getCountForPagination());
$query->paginate(20, total: $total); // Laravel nebeskaičiuoja COUNT
```

- Cache'inam tai, kas **brangu** ir gali būti **šiek tiek pasenę** (puslapiavimo skaičius, statistika, sitemap).
- Raktas turi apimti viską, nuo ko priklauso rezultatas (čia – SQL su parametrais), ir versiją (`v1`).
- Saugom skaičius, masyvus, tekstą – ne objektus (`serializable_classes = false`).
- Produkcijoje – Redis (WordPress analogas – „object cache" įskiepis su Redis).
  → https://laravel.com/docs/13.x/cache · https://laravel.com/docs/13.x/pagination

#### 7. Saugos antraštės ir CSP su Vite nonce

- **CSP** – naršyklei: „skriptus vykdyk tik iš mano domeno ir tik tuos, kurie turi šį vienkartinį `nonce`".
  Įterptas svetimas `<script>` (XSS) nonce neturi – nevykdomas.
- `Vite::useCspNonce()` middleware'e prieš piešiant puslapį → `@vite` pats prideda `nonce`; savo įterptam skriptui –
  `<script nonce="{{ Vite::cspNonce() }}">`.
- `<script type="application/json">` (Inertia puslapio duomenys) ir `application/ld+json` – duomenų blokai, ne skriptai:
  CSP jų neblokuoja.
- Kitos: `X-Frame-Options: DENY` / `frame-ancestors 'none'` (clickjacking), `X-Content-Type-Options: nosniff`,
  `Referrer-Policy`, `Permissions-Policy`, HSTS (tik produkcijoje – metus naršyklė jungsis tik HTTPS).
- Įjungiant produkcijoje – pirma `Content-Security-Policy-Report-Only` (tik praneša), paskui blokavimas.
  → https://laravel.com/docs/13.x/vite#content-security-policy-csp-nonce · https://developer.mozilla.org/docs/Web/HTTP/CSP

#### 8. Autorizacijos auditas testu

`Route::getRoutes()` – visi maršrutai; testas kiekvienam tikrina `auth` middleware arba sąmoningą įrašą „viešų"
sąraše su priežastimi. Pamirštas middleware naujame maršrute → testas raudonas. Antras testas – svečias kiekviename
neviešame GET puslapyje turi gauti nukreipimą, o ne turinį. Konkretaus įrašo teises (ar tai TAVO užklausa) vis tiek
tikrina Policies ir jų testai.

#### 9. BDAR: anonimizavimas, eksportas, saugojimas

- **Teisė susipažinti ir perkeliamumas** (15, 20 str.): visi žmogaus duomenys mašinai skaitomu formatu (JSON) + failai.
  WordPress analogas – Įrankiai → „Eksportuoti asmens duomenis".
- **Teisė būti pamirštam** (17 str.) ≠ ištrinti eilutes: pašalinam tai, kas identifikuoja žmogų, o verslo įrašus
  (užklausas, atsiliepimus, mokėjimus – juos 10 m. reikalauja apskaita) paliekam be vardo. WordPress – „Ištrinti
  asmens duomenis".
- Failus trinti **po** DB transakcijos: jei transakcija nepavyktų, ištrintų failų nebeatkursi.
- Job'as: `ShouldBeUnique` (vienas vienu metu kiekvienam vartotojui), `$deleteWhenMissingModels = true` (jei paskyra
  ištrinta, kol job'as laukė eilėje – tiesiog nevykdomas).
- Privatus diskas (`local` → `storage/app/private`) – failai nepasiekiami per URL; atsiunčia controller'is, kelią
  sudarydamas iš **prisijungusio** vartotojo ID.
  → https://laravel.com/docs/13.x/queues#unique-jobs · https://laravel.com/docs/13.x/filesystem#downloading-files

#### 10. sitemap.xml, robots.txt, JSON-LD

- **sitemap** – sąrašas puslapių, kuriuos norim indeksuoti (ne visų!): be paieškos rezultatų, be tuščių „paslauga
  mieste", be paskyrų. Didelis – skaidomas į dalis (iki 50 000 adresų), o `sitemap.xml` tampa indeksu.
- **robots.txt** – ką robotams lankyti draudžiama. Tai ne apsauga (privačius puslapius saugo prisijungimas), o
  „biudžeto" taupymas. Staging – `Disallow: /`.
- **JSON-LD** – schema.org duomenys `<script type="application/ld+json">`: Google rodo duonos trupinius, žvaigždutes,
  paieškos laukelį. Taisyklė: duomenyse – tik tai, kas matoma puslapyje. JSON į `<script>` dedamas su `JSON_HEX_TAG`,
  kad tekstas `</script>` nenutrauktų žymos.
- Inertia `<Head>` su `head-key` valdo žymas po kiekvieno puslapio perjungimo; `<script>` Vue šablone –
  `<component :is="'script'">`.
- WordPress analogai: `wp-sitemap.xml` (nuo 5.5), Yoast SEO schema.
  → https://developers.google.com/search/docs/crawling-indexing/sitemaps/overview ·
  https://developers.google.com/search/docs/appearance/structured-data/intro-structured-data · https://inertiajs.com/title-and-meta

#### 11. Diegimas: optimize, Supervisor, cron, queue:restart

- `php artisan optimize` = `config:cache` + `route:cache` + `view:cache` + `event:cache`. Po `config:cache` `env()`
  už `config/` ribų grąžina `null`.
- **Supervisor** laiko `queue:work` procesus gyvus; po deploy – `php artisan queue:restart` (kitaip jie vykdys seną kodą).
- **cron** kas minutę paleidžia `schedule:run`, o Laravel sprendžia, ką vykdyti (WordPress: `DISABLE_WP_CRON` + tikras cron).
- **OPcache** su `validate_timestamps=0` – po deploy būtina perkrauti PHP-FPM.
- **Zero-downtime** – releases katalogai + `current` symlink, migracijos „expand → contract".
- **/up** – `DiagnosingHealth` įvykis: listener'is, išmetantis išimtį, paverčia atsakymą 500.
  → https://laravel.com/docs/13.x/deployment · https://laravel.com/docs/13.x/queues#supervisor-configuration ·
  https://laravel.com/docs/13.x/scheduling#running-the-scheduler

#### 12. Logai produkcijoje

`stack` kanalas sujungia kelis: `daily` (failas per dieną, 14 d.) + `slack` (tik `critical`). Kiekvienas kanalas turi
savo `level`. Klaidų stebėsenai – Sentry ar Laravel Nightwatch (aprašyta `docs/DEPLOYMENT.md`).
→ https://laravel.com/docs/13.x/logging

#### 13. Carbon: kintamas ir nekintamas

`Carbon::createFromTimestamp(...)->addDays(7)` **pakeičia** patį objektą: `created_at` ir `expires_at` tapo vienodi.
`CarbonImmutable` grąžina naują objektą. Projektas visur naudoja `Date::use(CarbonImmutable::class)`, bet tiesiogiai
iškviestas `Illuminate\Support\Carbon` yra kintamas.
→ https://carbon.nesbot.com/docs/#api-immutable

### Naudingos komandos

| Komanda                                                                   | Ką daro                                           |
| ------------------------------------------------------------------------- | ------------------------------------------------- |
| `EXPLAIN ANALYZE SELECT …` (MySQL)                                        | planas su tikrais laikais (užklausą įvykdo!)      |
| `ANALYZE TABLE provider_profiles` (MySQL)                                 | atnaujina statistiką, pagal kurią renkamas planas |
| `SHOW INDEX FROM provider_profiles` (MySQL)                               | lentelės indeksai ir jų kardinalumas              |
| `php artisan route:list --except-vendor`                                  | tik projekto maršrutai                            |
| `php artisan make:filament-widget PlatformStatsOverview --stats-overview` | naujas Filament valdiklis                         |
| `php artisan about`                                                       | aplinka, cache būsena, tvarkyklės                 |
| `php artisan optimize` / `optimize:clear`                                 | produkcijos cache sukurti / išvalyti              |
| `php artisan filament:optimize`                                           | Filament komponentų ir ikonų cache                |
| `php artisan queue:restart`                                               | darbuotojai persikrauna su nauju kodu             |
| `php artisan privacy:prune-exports`                                       | ištrina senus BDAR archyvus                       |
| `php artisan schedule:list`                                               | suplanuotos užduotys                              |
| `curl -sI http://localhost:8000/ \| grep -i content-security`             | patikrinti saugos antraštes                       |
| `sudo supervisorctl status`                                               | eilių darbuotojų būsena serveryje                 |

### Dažnos klaidos

- **Įterptas skriptas be `nonce`** – įjungus CSP tamsaus režimo skriptas `app.blade.php` nustoja veikti (konsolėje
  „Refused to execute inline script").
- **Filament su griežtu CSP** – be `'unsafe-eval'` Alpine.js neveikia: neatsidaro modalai, nesiunčiamos formos.
- **`$middleware->web(append: …)` Filament'ui** – nesuveikia: panelė turi savo middleware sąrašą.
- **`public/robots.txt` šalia maršruto** – Nginx atiduoda statinį failą, maršrutas niekada nesuveikia.
- **Sudėtinio indekso stulpeliai ne ta tvarka** – rikiavimo stulpelis prieš lygybės sąlygą arba papildomas stulpelis
  po rikiavimo stulpelių – filesort lieka.
- **Matuoti su mažu seed'u** – 1 000 teikėjų viskas „greita", problemos išlenda tik su 20 000.
- **Tikrinti užblokavimą prieš slaptažodį** – atskleidžia, kad paskyra egzistuoja ir užblokuota.
- **Failus trinti DB transakcijos viduje** – transakcija atšaukiama, o failų nebėra.
- **`addDays()` kintamam Carbon** – pakeičia ir pradinę datą.
- **JSON `<script>` žymoje be `JSON_HEX_TAG`** – tekstas `</script>` nutraukia žymą (XSS).
- **Pamirštas `queue:restart` po deploy** – laiškai siunčiami pagal seną šabloną, job'ai kviečia nebesančius metodus.
- **`withCredits()` factory būsena kreditų teste** – balansas nustatomas be ledger eilutės, invariantas „balansas =
  ledger suma" neišsilaiko; testuose kreditus duoti per `CreditLedger::credit()`.
- **Filament valdiklio turinio ieškoti puslapio HTML** – valdikliai kraunami tingiai; tikrinti `assertSeeLivewire()`
  ir `Livewire::test()`.
- **`Event::assertListening()` be `Event::fake()`** – metodas egzistuoja tik netikram dispečeriui; tikram –
  `Event::hasListeners()`.
- **`selectRaw()` su sukonstruota eilute** – Larastan reikalauja `literal-string` (apsauga nuo SQL injekcijų);
  kintamas dalis perduoti parametrais (`?`).
