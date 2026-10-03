# Etapas 4 – Katalogas ir paieška (juodraštis `docs/LEARNING.md` skyriui)

> Juodraštis: sujungus į `docs/LEARNING.md`, šis failas ištrinamas (`docs/drafts/README.md`).

## Būsena (ROADMAP Etapas 4)

| ROADMAP punktas                                                       | Būsena     | Pastaba                                                                            |
| --------------------------------------------------------------------- | ---------- | ---------------------------------------------------------------------------------- |
| Pradžios puslapis: kategorijos, paieška, populiarūs miestai           | atlikta    | + geriausiai įvertinti teikėjai, CTA „Sukurti užklausą" → `/uzklausos/nauja`       |
| Kategorijų puslapiai (3 lygiai), „duonos trupiniai", SEO meta         | atlikta    | + visų paslaugų puslapis `/paslaugos`                                              |
| Teikėjų sąrašas: filtrai, rikiavimas, puslapiavimas                   | atlikta    | kategorijų puslapiuose ir `/meistrai`                                              |
| Viešas teikėjo profilis: aprašymas, kainos, portfolio, atsiliepimai   | atlikta    | logotipas ir portfolio nuotraukos – Etape 3 (vieta paruošta: `logo_url`, `images`) |
| Paieška tekstu (MySQL FULLTEXT) ir sprendimas dėl Scout + Meilisearch | atlikta    | sprendimas – žemiau („Scout + Meilisearch")                                        |
| Kategorijų medžio ir savivaldybių cache                               | atlikta    | invalidacija per observer'į                                                        |
| SEO puslapiai „{Paslauga} {mieste}"                                   | atlikta    | `/paslaugos/{kategorija}/{miestas}`                                                |
| Testai                                                                | atlikta    | 72 nauji testai (iš viso 200)                                                      |
| `docs/LEARNING.md`: Etapas 4                                          | juodraštis | šis failas                                                                         |

**Neatlikta sąmoningai (kiti etapai):** nuotraukos (Etapas 3, medialibrary), `sitemap.xml` ir schema.org
struktūriniai duomenys (Etapas 8), Inertia SSR (Etapas 8), `EXPLAIN` su pilnu MySQL seed'u ir indeksų korekcijos
(Etapas 8). Naujų migracijų ir indeksų šiame etape nėra.

---

## Etapas 4 – Katalogas ir paieška (viešoji dalis)

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

### Pastabos kitiems etapams

- **Etapas 3:** `ProviderCardResource::logo_url`, `ProviderProfileResource::logo_url` ir
  `PortfolioItemResource::images` paruošti medialibrary duomenims. Profilio vedlyje bazinį miestą verta visada
  įtraukti į zonas – miesto filtras žiūri tik į zonas (arba „visa Lietuva").
- **Etapas 5:** atitikimui naudoti `CatalogCache::categories()->ancestors()` ir scope'us `inCategories()`,
  `servingCity()`, `active()`.
- **Etapas 8:** `EXPLAIN` sąrašo užklausoms su pilnu seed'u (galimi indeksai `(status, reviews_count)`,
  `(status, completed_jobs_count)`), `sitemap.xml` su „paslauga mieste" puslapiais, schema.org
  (`LocalBusiness`, `AggregateRating`), Inertia SSR.
