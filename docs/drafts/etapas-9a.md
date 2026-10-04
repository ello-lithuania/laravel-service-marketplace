## Etapas 9a – Demo paveikslėliai ir ilgi pokalbiai (juodraštis)

> Lygiagreti Etapo 9 dalis (kitos dalys – mokėjimų grąžinimai ir prenumeratų privalumai – kituose juodraščiuose).
> Sujungiant: šis skyrius → `docs/LEARNING.md` (Etapas 9), ROADMAP punktai pažymimi pagal sąrašą žemiau.

**Būsena:**

- [x] Demo nuotraukos seed'e (`SEED_MEDIA=true`): abstraktūs logotipai, viršeliai ir portfolio (`docs/SEEDING.md` 7 sk.)
- [x] Ilgi pokalbiai: senesnių žinučių įkėlimas vietoj 100 žinučių ribos
- [x] Testai: `tests/Feature/Seeding/SeedMediaTest.php`, `tests/Feature/Messages/LongConversationTest.php`
      (SQLite ir MySQL 8.0 – praeina)

### Ką darėm ir kodėl

- **Demo paveikslėliai.** Iki šiol demo teikėjai neturėjo nei logotipų, nei darbų nuotraukų, todėl katalogas ir profiliai
  atrodė tušti, o medialibrary miniatiūrų, URL ir diskų veikimo su dideliu kiekiu nematėm. Dabar
  `SEED_MEDIA=true php artisan migrate:fresh --seed` sukuria ≈ 500 logotipų, ≈ 200 viršelių ir ≈ 1 000 portfolio darbų ×
  1–3 nuotraukos (× `SEED_SCALE`, tik aktyviems teikėjams).
    - **Jokių tikrų nuotraukų** (`docs/SEEDING.md` 6 sk.): paveikslėlius seed'o metu nupiešia `ImagePainter` su PHP GD –
      sulieti spalvų ratai, permatomos figūros, „bangos" ir inicialai. Kiekviena paslaugų sritis turi savo paletę
      (statyba – oranžinė, santechnika – mėlyna, sodas – žalia…), todėl darbų nuotraukos dera prie kategorijos.
    - **Greitis:** piešiamas tik nedidelis bendras rinkinys (≤ 72 failai portfolio ir viršeliams, logotipai – pagal
      inicialus), o prisegama su `preservingOriginal()` – tas pats failas kopijuojamas daug kartų.
    - **Miniatiūros seed'o metu daromos iškart** (laikinai `media-library.queue_connection_name = sync`), net jei
      `.env` – `QUEUE_CONNECTION=database`. Kitaip seed'as įdėtų ≈ 2 000 darbų į eilę, ir portfolio miniatiūros atsirastų
      tik veikiant `queue:work`. Kaina – laikas: `SEED_SCALE=0.05` – 10–12 s vietoj ≈ 3 s, `SEED_SCALE=1` – ≈ 5 min.
      Todėl pagal nutylėjimą `SEED_MEDIA=false`.
    - **Atkartojamumas:** ta pati `SEED_FAKER_SEED` – baitas į baitą tie patys failai (patikrinta `md5sum`).
    - **Populiaresni – dažniau su logotipu:** teikėjai renkami svertiniu atsitiktiniu būdu pagal atsiliepimų skaičių,
      todėl katalogo pirmame puslapyje paveikslėlių matyti, o toliau – mažiau, kaip tikrovėje.
    - Failai: `database/seeders/Demo/ImagePainter.php`, `database/seeders/Demo/MediaGenerator.php` (paskutinis
      `DemoDataSeeder` žingsnis), `config/seeding.php` (`media`), `.env.example` (`SEED_MEDIA=false`).
- **Ilgi pokalbiai.** Pokalbis rodė tik 100 naujausių žinučių (`MESSAGES_LIMIT`) – senesnių pamatyti nebuvo galima. Dabar:
    - serveris žinutes atiduoda **puslapiais po 50 nuo naujausių** su `Inertia::scroll()` ir `cursorPaginate()`;
    - `Show.vue` naudoja Inertia 3 komponentą **`<InfiniteScroll reverse>`**: atidarius rodoma pokalbio apačia, priartėjus
      prie viršaus automatiškai įkeliamas senesnis puslapis (atsarginis mygtukas „Rodyti senesnes žinutes"), skaitoma
      vieta neperšoka, URL nesikeičia; pasiekus pradžią rodoma „Pokalbio pradžia";
    - **polling'as** (kas 10 s) ir **žinutės siuntimas** perkrauna tik `messages` – naujausių puslapis **prijungiamas**
      (merge) prie jau įkeltų pagal `id`, todėl senesnės žinutės nedingsta ir nesidubliuoja;
    - perskaitytumas (`last_read_message_id`) – kaip anksčiau: bet kuri pokalbio užklausa pažymi perskaitytu iki
      naujausios žinutės, reikšmė tik didėja (senesnio puslapio įkėlimas jos nesumažina).
    - Failai: `app/Http/Controllers/Messages/ConversationController.php`, `resources/js/pages/messages/Show.vue`,
      `resources/js/types/messages.ts` (`ChatMessagePage`).

**Kaip išbandyti:**

```bash
SEED_MEDIA=true php artisan migrate:fresh --seed   # 10–12 s su SEED_SCALE=0.05
composer run dev
```

Paveikslėliai – `/meistrai` (rikiuoti „Daugiausia atsiliepimų") ir teikėjų profiliai. Ilgam pokalbiui demo duomenyse
žinučių per mažai (2–5), todėl `php artisan tinker` vienam pokalbiui pridėk, pvz., 130 žinučių
(`Message::factory()->count(130)->for($conversation)->create(['sender_id' => …])`) ir atidaryk `/zinutes/{id}`.

### Išmoktos sąvokos

#### 1. PHP GD: paveikslėlių piešimas kodu

GD – PHP plėtinys paveikslėliams kurti ir keisti (`php -m | grep gd`). WordPress jį (arba Imagick) naudoja miniatiūroms
per `WP_Image_Editor`.

```php
$image = imagecreatetruecolor(960, 720);                        // tuščia „drobė"
$color = imagecolorallocatealpha($image, 234, 88, 12, 60);      // RGB + permatomumas (0 – nepermatoma, 127 – visiškai)
imagefilledellipse($image, 480, 360, 300, 300, $color);         // figūros: ellipse, polygon, line, rectangle
imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);                  // filtrai: suliejimas, šviesumas…
$big = imagescale($image, 1920, 1440, IMG_BILINEAR_FIXED);      // dydžio keitimas su interpoliacija
imagettftext($image, 120, 0, $x, $y, $white, $fontPath, 'ŠŽ');  // tekstas TrueType šriftu (UTF-8)
imagejpeg($image, $path, 82);                                   // įrašymas: imagejpeg / imagepng / imagewebp
```

- **Greičio triukas:** PHP ciklas per kiekvieną tašką (`imagesetpixel` 700 000 kartų) trunka sekundes. Todėl fonas
  piešiamas 8 kartus mažesnis, suliejamas ir padidinamas `imagescale(..., IMG_BILINEAR_FIXED)` – sklandus perėjimas per
  kelias milisekundes. Visas paveikslėlis – ≈ 20–25 ms.
- **Šriftas su lietuviškomis raidėmis.** GD įtaisyti šriftai (`imagestring`) turi tik lotyniškas raides, todėl
  inicialams – `imagettftext()` su DejaVu Sans (jį jau atsiveža `dompdf`, naudojamas sąskaitų PDF). Centravimui
  `imagettfbbox()` grąžina teksto stačiakampį.
- **Atkartojamumas:** GD pats atsitiktinumo neturi – visos koordinatės ir spalvos iš `mt_rand()`, kuris „užsėjamas"
  `mt_srand($seed)`. Ta pati sėkla – tas pats failas.
  → https://www.php.net/manual/en/book.image.php · https://www.php.net/manual/en/function.imagettftext.php

#### 2. Medialibrary: failų prisegimas kodu ir miniatiūrų eilė

```php
$profile->addMedia($path)            // vietinis failas (įkeltam per formą – addMediaFromRequest('logo'))
    ->preservingOriginal()           // nekelti (move), o kopijuoti – originalas lieka kitiems įrašams
    ->setOrder(1)                    // order_column (kolekcijos tvarka)
    ->withAttributes(['uuid' => $uuid])
    ->toMediaCollection('logo');     // kolekcija iš registerMediaCollections()
```

- Be `preservingOriginal()` medialibrary šaltinį **perkelia** – antrą kartą to paties failo prisegti nebeišeitų.
- **Miniatiūros (conversions)** daromos iškart, jei konversija `->nonQueued()`, kitaip – eilės darbu
  `PerformConversionsJob` per `media-library.queue_connection_name` (numatyta – `QUEUE_CONNECTION`). Kol jis neįvykdytas,
  `$media->hasGeneratedConversion('thumb')` grąžina `false` (todėl `PortfolioItem::imageUrls()` tada rodo originalą).
- `config([...])` vykdymo metu pakeičia nustatymą tik šiam procesui – seed'as laikinai įjungia `sync` ir `finally` bloke
  grąžina seną reikšmę.
- **Modelių įvykiai:** medialibrary `uuid` ir `order_column` priskiria `creating` įvykyje (observer'is). `DatabaseSeeder`
  turi `WithoutModelEvents`, todėl seed'e šiuos laukus nurodom patys – kitaip `uuid` liktų `NULL`.
- **Morph map:** `media.model_type` saugo trumpą vardą (`provider_profile`, `portfolio_item`), nes
  `AppServiceProvider` kviečia `Relation::enforceMorphMap()` – tai patikrinta ir integracijos teste.
  → https://spatie.be/docs/laravel-medialibrary/v11/basic-usage/associating-files ·
  https://spatie.be/docs/laravel-medialibrary/v11/converting-images/defining-conversions ·
  https://laravel.com/docs/13.x/eloquent-relationships#custom-polymorphic-types

#### 3. Svertinis atsitiktinis rikiavimas (Efraimidis–Spirakis)

Reikėjo „atsitiktinai, bet populiaresni dažniau", be pasikartojimų. Kiekvienam teikėjui – raktas `ln(u) / svoris`
(`u` – atsitiktinis skaičius 0–1), tada rikiuojama mažėjančiai ir imama tiek pirmųjų, kiek reikia. Kuo didesnis svoris,
tuo raktas arčiau nulio, t. y. aukščiau. Vienas praėjimas ir vienas `arsort()` – greičiau nei kartoti „ištrauk ir
išmesk". Svoris `(1 + atsiliepimai)²`: su tiesiniu svoriu tarp 900 teikėjų top 4 retai gaudavo logotipą.

#### 4. Cursor puslapiavimas (`cursorPaginate`)

```php
$conversation->messages()->orderByDesc('id')->cursorPaginate(50);
// SQL: ... WHERE conversation_id = ? [AND id < ?] ORDER BY id DESC LIMIT 51
```

- **Puslapio numeris** (`paginate()`, `?page=2`) – `OFFSET 50`. Jei kol skaitai atėjo 3 naujos žinutės, „2 puslapis"
  pasislenka per 3 ir dalis žinučių pasikartoja. **Cursor** (`?cursor=eyJ…`) – užkoduotas „paskutinio matyto įrašo"
  raktas, todėl kitas puslapis visada prasideda tiksliai ten, kur baigėsi ankstesnis, o naujos žinutės jo nepaveikia.
- Be `OFFSET` – greita ir tolimiems puslapiams (indeksas `conversation_id` InnoDB'e savyje turi ir `id`).
- Apribojimai: rikiuoti reikia pagal unikalų stulpelį (čia `id`), nėra „iš viso puslapių" ir šuolio į N-tą puslapį – chat'ui
  to ir nereikia. `->through(fn ($m) => …)` pakeičia kiekvieną puslapio elementą (čia – į `MessageResource` masyvą).
- WordPress analogas – `WP_Query` su `'paged'` (offset); cursor primena „Senesni įrašai" pagal datą.
  → https://laravel.com/docs/13.x/pagination#cursor-pagination

#### 5. Inertia 3: `Inertia::scroll()` ir `<InfiniteScroll>`

```php
'messages' => Inertia::scroll(fn () => $this->messages($conversation))->matchOn('data.id'),
```

```vue
<InfiniteScroll
    data="messages"
    reverse
    only-next
    preserve-url
    class="flex flex-col gap-3"
>
    <template #next="{ loading, fetch, hasMore }">…</template>
    <article v-for="message in ordered" :key="message.id">…</article>
</InfiniteScroll>
```

- `Inertia::scroll()` – puslapiuotas prop'as: atsakyme be duomenų yra `scrollProps` (kito / ankstesnio puslapio raktas),
  o daliniame perkrovime duomenys **sujungiami** (`mergeProps: ['messages.data']`), ne pakeičiami.
- `<InfiniteScroll>` pats seka, kada viršutinis / apatinis „trigeris" pasirodo ekrane (IntersectionObserver), ir siunčia
  dalinį perkrovimą `only: ['messages']` su `?cursor=…` bei antrašte, į kurią pusę jungti.
- `reverse` – chat'o režimas: kitas (senesnis) puslapis įkeliamas **viršuje**, atidarius nuslenkama į apačią, įkėlus
  senesnes žinutes išlaikoma skaitoma vieta. `preserve-url` – adreso juostoje `?cursor=…` nerodomas.
- **Rodymo tvarka – mūsų darbas:** `data` masyve puslapiai sudėti ta tvarka, kuria atėjo (naujausi, senesni, polling'o
  naujienos gale), todėl `computed` surikiuoja pagal `id`.
- **`matchOn('data.id')`** – sujungiant, įrašai su tuo pačiu `id` pakeičiami vietoje, o ne pridedami antrą kartą.
  Būtent tai leidžia polling'ui (`usePoll(10_000, { only: ['messages', …] })`) kas 10 s parsisiųsti naujausių puslapį:
  jau rodomos žinutės atsinaujina (pvz. paslėpta), naujos pridedamos, senesnės lieka.
- **Siuntimas su `only`:** `form.post(url, { only: [...], preserveState: true })` – po nukreipimo (redirect) atgal į pokalbį
  Inertia daro dalinį perkrovimą (antraštės išlieka ir po redirect), todėl jau įkeltos senesnės žinutės nedingsta.
  → https://inertiajs.com/infinite-scroll · https://inertiajs.com/merging-props · https://inertiajs.com/polling ·
  https://inertiajs.com/partial-reloads

#### 6. Testuose – tikras Inertia dalinis perkrovimas

`assertInertia()` mato tik `props`. `scrollProps`, `mergeProps`, `matchPropsOn` patikrinti galima siunčiant tas pačias
antraštes kaip naršyklė ir skaitant JSON:

```php
$this->get(route('conversations.show', $conversation).'?cursor='.$cursor, [
    'X-Inertia' => 'true',
    'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')),
    'X-Inertia-Partial-Component' => 'messages/Show',
    'X-Inertia-Partial-Data' => 'messages',
])->json('scrollProps.messages.nextPage');
```

Be teisingos `X-Inertia-Version` serveris grąžina 409 (naujas frontend'o leidimas). N+1 patikra be papildomų įrankių:
`DB::enableQueryLog()` → užklausų skaičius su 2 ir su 50 žinučių turi sutapti.
→ https://laravel.com/docs/13.x/database#listening-for-query-events · https://inertiajs.com/testing

### Naudingos komandos

| Komanda                                                            | Ką daro                                                           |
| ------------------------------------------------------------------ | ----------------------------------------------------------------- |
| `SEED_MEDIA=true php artisan migrate:fresh --seed`                 | demo duomenys su paveikslėliais (10–12 s su `SEED_SCALE=0.05`)    |
| `php -m \| grep -i gd` · `php -r 'print_r(gd_info());'`            | ar įdiegtas GD ir ką jis moka (JPEG, PNG, WebP, FreeType)         |
| `php artisan media-library:regenerate`                             | iš naujo sugeneruoja miniatiūras (pvz. pakeitus konversijų dydį)  |
| `php artisan media-library:clean --dry-run`                        | parodo nebenaudojamas miniatiūras ir katalogus be `media` eilutės |
| `du -sh storage/app/public`                                        | kiek vietos užima viešo disko failai                              |
| `php artisan queue:work`                                           | įvykdo eilėje laukiančias miniatiūras (įkeliant per UI)           |
| `php artisan test tests/Feature/Messages/LongConversationTest.php` | ilgų pokalbių testai                                              |

### Dažnos klaidos

- **`addMedia()` be `preservingOriginal()`** – šaltinio failas perkeliamas, antras prisegimas meta „file does not exist".
- **Seed'as su `WithoutModelEvents` ir paketai, kurie remiasi modelių įvykiais** – medialibrary `uuid` liko `NULL`.
  Jei paketas ką nors priskiria `creating` metu, seed'e nurodyk pats.
- **`config()` pakeitimas be `finally`** – jei prisegimas nepavyktų, likęs procesas (ir testai) dirbtų su `sync` eile.
- **Puslapio numeris vietoj cursor besikeičiančiam sąrašui** – dubliuojasi arba pradingsta įrašai.
- **Merge be `matchOn`** – polling'as kas 10 s prijungtų tą patį naujausių puslapį dar kartą: žinutės dubliuotųsi.
- **Rodymo tvarka pagal masyvo tvarką** – po kelių sujungimų (senesni puslapiai + polling) naujos žinutės atsidurtų
  viduryje. Rikiuok pagal `id` (arba datą) `computed` savybėje.
- **`watch` žinučių kiekiui** „slinkti į apačią, kai atėjo nauja" suveikia ir įkėlus senesnes žinutes. Stebėk naujausios
  žinutės `id`.
- **`findMany()` ant to paties builder'io kelis kartus** – `whereKey()` sąlygos kaupiasi; kiekvienai daliai kurk naują
  užklausą (`Model::query()`).
- **Seni seed'o failai diske** – `migrate:fresh` išvalo DB, bet ne `storage/app/public`. `MediaGenerator` našlaičius
  išvalo pats (tik skaitmeninius `{id}/` katalogus ir tik kai `media` lentelė tuščia). Alternatyva –
  `php artisan media-library:clean`, bet ji ištrina **visus** disko katalogus, kurių nenurodo `media` eilutės, net ir ne
  medialibrary sukurtus – todėl seed'e jos nekviečiam.
- **Pamiršta `npm run build`** po Vue pakeitimų – `php artisan serve` rodo seną kodą (`Cannot read properties of
undefined`), nors testai praeina.
