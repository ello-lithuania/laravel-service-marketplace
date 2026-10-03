# Našumas: EXPLAIN, indeksai ir cache (Etapas 8)

> **Būsena:** išmatuota 2026-10-03 su pilnu seed'u. Indeksų pakeitimai – migracija
> `2026_10_03_800001_tune_indexes_after_explain` (aprašyta `docs/DB_SCHEMA.md`), cache – `ProviderListQuery`,
> `DashboardStats`, `SitemapBuilder`. Sąvokos (EXPLAIN, sudėtinis indeksas, covering index, filesort) paaiškintos
> `docs/drafts/etapas-8.md` ir `docs/LEARNING.md` (Etapas 0 → indeksai).

## Turinys

1. [Kaip matuota](#1-kaip-matuota)
2. [Suvestinė: prieš ir po](#2-suvestinė-prieš-ir-po)
3. [Radiniai ir sprendimai](#3-radiniai-ir-sprendimai)
4. [Užklausos, kurios jau buvo geros](#4-užklausos-kurios-jau-buvo-geros)
5. [Admin panelė (Filament)](#5-admin-panelė-filament)
6. [Cache apžvalga](#6-cache-apžvalga)
7. [N+1 apžvalga](#7-n1-apžvalga)
8. [Kas liko ir kada grįžti](#8-kas-liko-ir-kada-grįžti)
9. [Kaip pakartoti matavimus](#9-kaip-pakartoti-matavimus)

---

## 1. Kaip matuota

- **Duomenys:** `SEED_SCALE=1` – 80 003 vartotojai, 20 000 teikėjų (18 333 aktyvūs), 100 000 užklausų, 300 000
  pasiūlymų, 231 000 pranešimų, iš viso ≈ 1,9 mln. eilučių (`docs/SEEDING.md`).
- **DB:** MySQL 8.0.46 (lokalus serveris; produkcijoje planuojama 8.4 LTS – optimizatorius tas pats), InnoDB, numatytieji
  nustatymai. Po indeksų keitimo – `ANALYZE TABLE` (atnaujina statistiką, pagal kurią optimizatorius renkasi planą).
- **Puslapiai:** scenarijus paleidžia kiekvieną puslapį per Laravel HTTP kernel'į (`APP_ENV=production`,
  `APP_DEBUG=false`, Inertia XHR užklausa) ir per `DB::listen()` surenka visas SQL užklausas su laikais. Kiekvienas
  puslapis paleidžiamas du kartus: **šaltas** (pirmas – tuščias aplikacijos cache) ir **šiltas** (antras).
- **Užklausos:** `EXPLAIN ANALYZE` – ne tik planas, bet ir tikras vykdymo laikas bei eilučių skaičius kiekviename
  žingsnyje. Laikai – milisekundės, viename kompiuteryje, todėl svarbūs santykiai, o ne absoliutūs skaičiai.

## 2. Suvestinė: prieš ir po

Puslapio laikas (visas PHP + SQL) ir SQL suma, šiltas cache. „Po (šaltas)" – kai skaičių cache dar tuščias.

| Puslapis                                          | Prieš: viso / SQL | Po: viso / SQL | Po (šaltas) | Kas padėjo                     |
| ------------------------------------------------- | ----------------: | -------------: | ----------: | ------------------------------ |
| `/` pradžia                                       |     55 ms / 46 ms |   15 ms / 4 ms |       79 ms | katalogo indeksas              |
| `/paslaugos/statyba-ir-remontas` (1 lygis)        |   185 ms / 165 ms |   21 ms / 6 ms |      102 ms | indeksas + COUNT cache         |
| `/paslaugos/plyteliu-klijavimas` (3 lygis)        |     23 ms / 13 ms |   18 ms / 8 ms |       24 ms | (jau buvo gerai)               |
| `/paslaugos/plyteliu-klijavimas/vilnius`          |     31 ms / 15 ms |  20 ms / 10 ms |       27 ms | (jau buvo gerai)               |
| `/paslaugos/statyba-ir-remontas/kaunas`           |   188 ms / 174 ms |   21 ms / 7 ms |       81 ms | indeksas + COUNT cache         |
| `/meistrai`                                       |     85 ms / 72 ms |   15 ms / 3 ms |       22 ms | katalogo indeksas              |
| `/meistrai?miestas=vilnius&patikrinti=1`          |    120 ms / 89 ms |   23 ms / 5 ms |       64 ms | indeksas + COUNT cache         |
| `/meistrai?puslapis=50`                           |     91 ms / 78 ms |   19 ms / 7 ms |       19 ms | katalogo indeksas              |
| `/meistrai/{slug}` (668 atsiliepimai)             |     21 ms / 12 ms |   17 ms / 8 ms |       24 ms | (jau buvo gerai)               |
| `/paieska?q=plyteliu+klijavimas` (FULLTEXT)       |     24 ms / 11 ms |   20 ms / 8 ms |       22 ms | (jau buvo gerai)               |
| `/teikejas/uzklausos` (aktyviausias, visa LT)     |     52 ms / 33 ms |  33 ms / 21 ms |       44 ms | varpelio indeksas              |
| `/mano-pasiulymai` (2 906 pasiūlymai)             |     36 ms / 27 ms |   11 ms / 4 ms |       13 ms | varpelio indeksas              |
| `/pranesimai` (1 162 pranešimai)                  |      12 ms / 8 ms |  16 ms / 11 ms |       26 ms | (svyruoja nuo buferio būsenos) |
| Valandinė pasibaigusių užklausų patikra (1 dalis) |            127 ms |        0,04 ms |           – | `(status, expires_at)`         |

## 3. Radiniai ir sprendimai

### 3.1 Katalogo rikiavimas – filesort per 18 000 eilučių

**Užklausa** (pradžia, `/meistrai`, kategorijų sąrašai; `ProviderListQuery` + `ProviderProfile::sortedBy`):

```sql
SELECT … FROM provider_profiles
WHERE status = 'active' AND deleted_at IS NULL
ORDER BY rating_avg DESC, reviews_count DESC, id DESC
LIMIT 20;
```

**Prieš** – indeksas `(status, rating_avg)`:

```
-> Limit: 20 row(s)  (actual time=45.8..45.8 rows=20)
    -> Sort: rating_avg DESC, reviews_count DESC, id DESC   ← filesort
        -> Filter: (deleted_at is null)  (actual rows=18333)
            -> Index lookup using provider_profiles_status_rating_avg_index (status='active')  (actual time=0.147..39.4 rows=18333)
```

Dvi problemos: (1) tarp `rating_avg` ir `id` rikiuojama dar pagal `reviews_count`, kurio indekse nėra, todėl MySQL
turi perskaityti **visus** 18 333 aktyvius ir juos surūšiuoti; (2) `deleted_at IS NULL` tikrinti reikia skaityti pačią
lentelės eilutę (indekse to stulpelio nėra).

**Po** – indeksas `(status, deleted_at, rating_avg, reviews_count)`:

```
-> Limit: 20 row(s)  (actual time=0.152..0.161 rows=20)
    -> Index lookup using provider_profiles_catalog_index (status='active', deleted_at=NULL) (reverse)  (actual rows=20)
```

`status` ir `deleted_at` – lygybės sąlygos (`IS NULL` indekse veikia kaip lygybė), toliau – rikiavimo stulpeliai, o
InnoDB kiekvieno antrinio indekso gale prideda pirminį raktą (`id`). Todėl indeksas jau surikiuotas lygiai taip, kaip
prašo `ORDER BY`, ir MySQL perskaito tik 20 eilučių (atbuline tvarka – `reverse`). **45 ms → 0,15 ms.**
Tas pats indeksas `COUNT(*)` puslapiavimui skaičiuoja vien iš indekso (_covering_): 28 ms → 6 ms.

_Kodėl ne `(status, rating_avg, reviews_count)`:_ be `deleted_at` kiekvienai eilutei vis tiek reikėtų skaityti lentelę.
_Kodėl ne `serves_whole_country`, `verified_at` gale:_ tada po `reviews_count` eitų jie, o ne `id`, ir rikiavimas
pagal `id DESC` vėl reikalautų filesort (patikrinta).

### 3.2 Kategorijos COUNT ir pivot semi-join (Etapo 4 pastaba)

Etape 4 pastebėta: `id IN (SELECT provider_profile_id FROM category_provider_profile WHERE category_id IN (…))`
mažuose duomenyse naudojo pivot pirminį raktą, o ne `(category_id, provider_profile_id)`. Su pilnu seed'u:

**Prieš** (1 lygio kategorija – 39 kategorijos medyje, 21 347 pivot eilutės):

```
-> Aggregate: count(0)  (actual time=133..133)
    -> Nested loop semijoin  (actual rows=3735)
        -> Index lookup using status_rating_avg_index (status='active')  (actual time=0.18..40.2 rows=18333)
        -> Covering index lookup on category_provider_profile using PRIMARY (provider_profile_id=…)  (loops=18333)
```

Optimizatorius eina nuo teikėjų ir kiekvienam (18 333 kartus) tikrina pivot pirminį raktą. Su nauju indeksu tas pats
COUNT kartais vykdomas kitu planu:

```
-> Nested loop inner join  (actual time=9.21..23.2)
    -> Covering index lookup using catalog_index (status='active', deleted_at=NULL)  (rows=18333)
    -> Single-row index lookup on <subquery2> using <auto_distinct_key>
        -> Materialize with deduplication  (rows=4088)
            -> Covering index range scan using category_provider_profile_category_id_provider_profile_id_index  (rows=21347)
```

Tai yra Etape 4 tikėtasis planas: pivot indeksas `(category_id, provider_profile_id)` perskaitomas intervalais, 4 088
unikalūs teikėjai sudedami į laikiną lentelę – **23 ms**. Bet kurį planą optimizatorius pasirinks, priklauso nuo
kainos įverčių, o jie keičiasi nuo to, kiek indekso puslapių šiuo metu yra atmintyje (buffer pool): po pakartotinio
`ANALYZE TABLE` vėl matėm „nested loop semijoin" – **84 ms**. Sąrašo užklausai (su `LIMIT 20`) abu planai greiti
(1–2 ms), nes nuo teikėjų einama indekso tvarka ir sustojama po 20 tinkamų.

Ką bandėm (EXPLAIN ANALYZE, tas pats MySQL):

| Variantas                                                            | 1 lygio COUNT | 1 lygis + miestas COUNT | Sąrašas | Kodėl atmesta / priimta                                    |
| -------------------------------------------------------------------- | ------------: | ----------------------: | ------: | ---------------------------------------------------------- |
| tik katalogo indeksas (dabar)                                        |      23–84 ms |                  ~65 ms |  1–2 ms | priimta + COUNT cache                                      |
| + indeksas `(status, deleted_at, serves_whole_country, verified_at)` |     81–105 ms |                   34 ms |    1 ms | 1 lygio COUNT pablogėjo (optimizatorius vėl pakeitė planą) |
| `JOIN (SELECT DISTINCT provider_profile_id …)` vietoj `IN`           |         21 ms |                   25 ms |   22 ms | sąrašas 10–20 kartų lėtesnis, kodo pakeitimas didesnis     |
| optimizatoriaus užuomina `/*+ SEMIJOIN(MATERIALIZATION) */`          |             – |                       – |       – | tik MySQL, sunku įterpti per query builder, „trapu"        |

**Sprendimas:** puslapiavimo `COUNT` laikomas cache 5 min. (`ProviderListQuery::cachedTotal`, raktas – SQL su
parametrais). Tikslus skaičius čia nebūtinas, o kategorijų ir „paslauga mieste" puslapius (15 000 derinių) nuolat lanko
robotai. Paieškai tekstu cache nenaudojamas (derinių begalė). Šiltas cache: 1 lygio kategorija 185 ms → 21 ms.

### 3.3 Varpelis – neperskaitytų skaičius kiekviename puslapyje

`HandleInertiaRequests` kiekvienam prisijungusiam vartotojui skaičiuoja
`notifications WHERE notifiable_type='user' AND notifiable_id=? AND read_at IS NULL`. Su `morphs()` indeksu
`(notifiable_type, notifiable_id)` aktyviausiam teikėjui (1 162 pranešimai) reikėjo perskaityti visas jo eilutes:

```
Prieš: -> Filter: (read_at is null) -> Index lookup using notifications_notifiable_type_notifiable_id_index (rows=1162)   4,2 ms
Po:    -> Covering index lookup using notifications_notifiable_read_at_index (…, read_at=NULL) (rows=392)                 0,2 ms
```

Senas indeksas – naujojo pradžia, todėl ištrintas (sąrašas „visi mano pranešimai" naudoja tą pačią pradžią).
Kodėl verta, nors 4 ms atrodo mažai: tai **kiekvieno** puslapio kaina kiekvienam vartotojui.

### 3.4 Valandinė pasibaigusių užklausų patikra

`ExpireServiceRequests`: `WHERE status='open' AND expires_at <= now() ORDER BY id LIMIT 200` (`chunkById`).
Be tinkamo indekso MySQL dėl `ORDER BY id LIMIT` rinkosi skenuoti visą lentelę pirminio rakto tvarka:

```
Prieš: -> Index scan on service_requests using PRIMARY (actual time=0.063..118 rows=100000)              127 ms
Po:    -> Index range scan using (status, expires_at) over (status='open' AND expires_at <= …) (rows=0)  0,04 ms
```

DB_SCHEMA 8 sk. #16 tai numatė („jei EXPLAIN parodys, kad to maža, pridėsim `(status, expires_at)`"). Lentelė auga
kasmet, todėl skenavimas su laiku tik lėtėtų.

## 4. Užklausos, kurios jau buvo geros

| Užklausa                                                     | Planas (santrauka)                                                                                     |  Laikas |
| ------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------ | ------: |
| Teikėjo srautas, „visa Lietuva" (DB_SCHEMA 8.1)              | range scan `(category_id, status, published_at)` per 6 kategorijas + `NOT EXISTS` per `offers UNIQUE`  | 6–14 ms |
| Teikėjo srautas su zonomis                                   | tas pats indeksas, miesto sąlyga filtruoja 130 → 45 eilučių, filesort tik 45 eilučių                   |  0,7 ms |
| Paieška `MATCH … AGAINST` (FULLTEXT, boolean)                | FULLTEXT indeksas, COUNT + sąrašas                                                                     |  3–6 ms |
| 3 lygio kategorija + miestas COUNT                           | range scan pivot `(category_id, provider_profile_id)` (1 197 eil.) + PK lookup                         | 6–10 ms |
| Viešas profilis (13 užklausų)                                | visi ryšiai – po vieną `IN (…)` užklausą, atsiliepimai – `(provider_profile_id, status, published_at)` | 7–12 ms |
| „Mano užklausos" / „Mano pasiūlymai"                         | `(client_id, created_at)` / `(provider_profile_id, created_at)`                                        |  < 1 ms |
| Naujos užklausos pranešimai (`NotifyMatchingProviders`)      | pivot range scan + PK, `chunkById(500)`                                                                |   12 ms |
| Kitos rikiavimo parinktys (`atsiliepimai`, `atlikti darbai`) | filesort per aktyvius (indekso nėra) – žr. 8 sk.                                                       |   40 ms |

## 5. Admin panelė (Filament)

Administratorių – vienetai, todėl čia priimtinos ir kelių šimtų milisekundžių užklausos, jei jos nevyksta kiekvienam
lankytojui. Vis tiek:

| Vieta                                           | Laikas (šaltas) | Sprendimas                                                                       |
| ----------------------------------------------- | --------------: | -------------------------------------------------------------------------------- |
| Skydelio suvestinė (`DashboardStats::summary`)  |         ~800 ms | cache 5 min.; valdikliai kraunami „tingiai" (lazy) – puslapis rodomas iškart     |
| Diagrama (užklausos ir pasiūlymai per 30 d.)    |         ~160 ms | cache 10 min. (pasiūlymų per dieną – pilnas 300 000 eil. skenavimas)             |
| Vartotojų sąrašas, rikiuotas pagal `created_at` |         ~106 ms | priimtina; `(role, created_at)` padeda skirtukams pagal rolę                     |
| Užklausų skirtukas „Visos" (`created_at DESC`)  |         ~170 ms | priimtina; numatytasis skirtukas – „Laukia patvirtinimo" (6 ms)                  |
| Teikėjų skirtukas „Nepatikrinti"                |          ~50 ms | priimtina                                                                        |
| Meniu ženklelis „Nepatikrinti teikėjai"         |          ~35 ms | cache 5 min. (meniu piešiamas kiekviename admin puslapyje), išvalomas po veiksmų |
| sitemap.xml (pilnas rinkinys)                   |  0,5 s indeksas | cache 6 val.; „paslauga mieste" poros – 2 užklausos vietoj 15 000 COUNT          |

Skydelio skaičiams **sąmoningai nekuriam indeksų** (pvz. `offers(created_at)`): kiekvienas indeksas lėtina įrašymą
(pasiūlymai – dažniausias įrašas platformoje), o šias užklausas mato keli žmonės kas kelias minutes. Jei skydelį
reikės greitinti – pirmiausia periodinis cache „apšildymas" Scheduler'iu, o ne indeksai.

## 6. Cache apžvalga

| Kas                                     | Raktas / vieta                           |     Galioja | Išvalymas                                      |
| --------------------------------------- | ---------------------------------------- | ----------: | ---------------------------------------------- |
| Kategorijų medis, geografija (Etapas 4) | `catalog:categories:v1`, `…geography:v1` | be pabaigos | `CatalogCacheObserver` po pakeitimų Filament'e |
| Katalogo COUNT puslapiavimui            | `catalog:count:v1:{sha1(SQL)}`           |      5 min. | laikas (skaičius gali vėluoti iki 5 min.)      |
| Admin skydelio skaičiai, diagrama       | `admin:dashboard:*`                      | 5 / 10 min. | laikas                                         |
| Meniu ženklelis „Nepatikrinti"          | `admin:nav:unverified-providers`         |      5 min. | laikas + po „Patikrintas", būsenos veiksmų     |
| sitemap.xml failai, „paslauga mieste"   | `seo:sitemap:*`, `seo:category-city-…`   |      6 val. | laikas                                         |

Svarstyta, bet **nedaryta**:

- **Pradžios puslapio „geriausi teikėjai"** – po indekso užklausa trunka 0,15 ms, cache būtų tik dar viena judanti dalis.
- **Populiarūs miestai** – jau iš geografijos cache (Etapas 4).
- **Viso puslapio (HTML) cache** – Inertia puslapiuose yra prisijungusio vartotojo duomenų (varpelis, meniu), todėl
  reikėtų atskirti svečių ir prisijungusių atsakymus; kol kas nereikia.

Produkcijoje cache – **Redis** (`CACHE_STORE=redis`): `database` cache kiekvienam skaitymui daro SQL užklausą ir
konkuruoja su pačia DB. Visi saugomi duomenys – skaičiai, masyvai ar XML tekstas (ne objektai:
`config/cache.php → serializable_classes = false`).

## 7. N+1 apžvalga

- `Model::preventLazyLoading(! app()->isProduction())` – dev'e ir testuose ryšio užkrovimas cikle meta išimtį, todėl
  kiekvienas N+1 iškart „nulaužia" testą. Visas testų rinkinys (SQLite ir MySQL) praeina.
- Profiliavimo scenarijus rodo pastovų užklausų skaičių visuose puslapiuose (4–14), nepriklausomai nuo duomenų kiekio
  (pvz. profilis su 668 atsiliepimais – 13 užklausų).
- Nauji Filament resursai: sąrašams ryšiai užkraunami `getEloquentQuery()->with(...)`, peržiūrai –
  `getRecordRouteBindingEloquentQuery()->with(...)->withCount(...)`.
- JSON-LD (`StructuredData::provider`) naudoja tik jau užkrautus ryšius (`relationLoaded`) – papildomų užklausų nėra.
- BDAR eksportas skaito lenteles tiesiogiai (query builder), po vieną užklausą lentelei.

## 8. Kas liko ir kada grįžti

| Situacija                                                      | Ką daryti                                                                                                  |
| -------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------- |
| Rikiavimas „daugiausia atsiliepimų" / „atlikti darbai" (40 ms) | jei taps populiarus – indeksas `(status, deleted_at, reviews_count, rating_avg)` (lygiai kaip 3.1)         |
| Teikėjų 100 000+ (COUNT šaltas cache > 200 ms)                 | denormalizuotas skaitliukas lentelėje `category_city_counts` (atnaujinamas job'u) arba Scout + Meilisearch |
| Paieška reikalauja klaidų tolerancijos                         | Laravel Scout + Meilisearch (sprendimas – LEARNING.md, Etapas 4)                                           |
| DB tampa siaura vieta                                          | skaitymo replika (`'read' => [...]` config/database.php), Redis sesijoms ir eilėms                         |
| PHP laikas > SQL laikas                                        | OPcache (privaloma produkcijoje), `config:cache`/`route:cache`, vėliau Laravel Octane                      |
| Pranešimų lentelė > 5 mln. eilučių                             | senų perskaitytų pranešimų valymas (`model:prune`), indeksas su `created_at` sąrašui                       |

## 9. Kaip pakartoti matavimus

```bash
# 1. Pilnas seed'as MySQL (~2,5 min.)
mysql -uroot -e "CREATE DATABASE paslaugos_perf CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
DB_CONNECTION=mysql DB_DATABASE=paslaugos_perf SEED_SCALE=1 php artisan migrate:fresh --seed --force

# 2. Statistika ir užklausos planas
mysql paslaugos_perf -e "ANALYZE TABLE provider_profiles, service_requests, notifications"
mysql paslaugos_perf -e "EXPLAIN ANALYZE SELECT … \G"

# 3. Kokias užklausas vykdo puslapis (tinker)
DB_CONNECTION=mysql DB_DATABASE=paslaugos_perf php artisan tinker
>>> DB::enableQueryLog(); app(App\Services\Catalog\ProviderListQuery::class)->paginate(new App\Services\Catalog\ProviderFilters); DB::getQueryLog();
```

`EXPLAIN ANALYZE` užklausą **tikrai įvykdo** – produkcijoje jį leisti tik `SELECT` užklausoms ir ne piko metu.
Planų skaitymas: žr. `docs/drafts/etapas-8.md` → „EXPLAIN ir indeksai".
