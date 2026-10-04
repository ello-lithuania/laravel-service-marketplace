### 10a – Nuotraukos: atsisiuntimo komanda, autorių puslapis, demo portfolio, Filament

> Juodraštis `docs/LEARNING.md` Etapo 10 skyriui (lygiagretus agentas; dizaino dalį daro kitas agentas).

**Būsena:**

- [x] Komanda `php artisan photos:download` – Pexels (su raktu) ir Openverse (be rakto), patikra, vietinė biblioteka
- [x] Puslapis „Nuotraukų autoriai" (`/nuotrauku-autoriai`, `photo-credits`, `noindex`)
- [x] Demo portfolio ir viršeliai – tikros nuotraukos iš rinkinio, kitaip – sugeneruoti paveikslėliai (`SEED_MEDIA=true`)
- [x] Seed'as po `migrate:fresh` nuotraukas prisega iš bibliotekos be interneto (`StockPhotoSeeder`)
- [x] Filament: kategorijos nuotrauka (forma + miniatiūra sąraše), „Svetainės nuotraukos" (oficialus medialibrary įskiepis)
- [x] Testai (`Http::fake`, `Storage::fake`, `Livewire::test`), patikrinta ir su MySQL 8.0
- [x] `docs/SEEDING.md` 6–9 sk., `docs/DB_SCHEMA.md` (media, `site_photos`)
- [ ] **Tikras atsisiuntimas** – savininkas paleidžia savo kompiuteryje (Claude konteineryje nuotraukų bankai užblokuoti,
      todėl viskas išbandyta tik su netikrais atsakymais). Komandos – žemiau, „Naudingos komandos".
- [ ] Nuoroda į `/nuotrauku-autoriai` poraštėje – daro dizaino agentas (`PublicLayout.vue`).

#### Ką darėm ir kodėl

- **Komanda, ne seed'as, siunčiasi iš interneto.** `php artisan photos:download` vieną kartą atsisiunčia nuotraukas į
  **vietinę biblioteką** `storage/app/stock-photos` (Git'e ignoruojama) su `credits.json` ir prisega jas per
  medialibrary. Seed'as internetu nesinaudoja: po `migrate:fresh --seed` nuotraukos prisegamos iš bibliotekos. Taip
  seed'as lieka greitas ir atkartojamas, o API limitai (Openverse be rakto – ~200 užklausų per parą) nesibaigia.
    - Kategorijoms (1 ir 2 lygis), svetainės vietoms (`SitePhotoKey`) ir **demo portfolio rinkiniui** (po 8 kiekvienai
      1 lygio sričiai, `--per-category`).
    - Vietai, kuri jau turi nuotrauką, nieko nedaro; jei nuotrauka yra bibliotekoje – prisega iš jos (be interneto);
      kitaip – ieško, atsisiunčia, patikrina, įrašo į biblioteką ir prisega.
    - `--force` – pakeisti atsisiųstas nuotraukas **kitomis** (sena lieka „naudota", todėl parenkama kita).
      Administratoriaus įkeltų (be `stock_id`) komanda niekada nekeičia.
    - Viena klaida (nėra ryšio, blogas failas) komandos nesustabdo – bandomas kitas rezultatas, kita frazė, kitas šaltinis.
      Išėjimo kodas 1 – tik jei nepavyko niekas.
- **Šaltiniai.** Pexels – geriausios kokybės nemokamos nuotraukos, bet reikia (nemokamo) rakto. Openverse – WordPress
  fondo atvirų licencijų paieška (Flickr, Wikimedia Commons…), rakto nereikia, bet kokybė įvairesnė ir limitai mažesni.
  `--source=auto`: Pexels, o jei jis nieko tinkamo neranda ar išnaudoja limitą – Openverse.
    - **Kodėl ne Unsplash:** jo API taisyklės reikalauja rodyti nuotraukas iš jų serverio (hotlinking) ir pranešti apie
      kiekvieną atsisiuntimą – vietinei bibliotekai ir medialibrary miniatiūroms netinka.
    - **Kodėl ne tiesiai Wikimedia ar Flickr API:** Openverse juos jau sujungia ir grąžina licenciją vienodu formatu.
- **Licencijos ir autoriai.** Openverse imam tik licencijas, leidžiančias komercinį naudojimą **ir** keitimą (miniatiūra
  nuotrauką apkerpa – tai keitimas): CC0, Public Domain Mark, CC BY, CC BY-SA. CC BY ir CC BY-SA **reikalauja** nurodyti
  autorių, Pexels – ne, bet autorių saugom visada (`custom_properties.credit`) ir rodom puslapyje „Nuotraukų autoriai".
- **Žmonės nuotraukose.** Demo portfolio – tik daiktų ir atlikto darbo frazės („renovated bathroom tiles", „landscaped
  garden") ir praleidžiami rezultatai, kurių aprašyme yra „man", „woman", „people"… Iš Openverse žmonių vengiama visur:
  CC licencija leidžia naudoti nuotrauką, bet **neapima nuotraukoje esančio žmogaus sutikimo** (asmens teisės į atvaizdą).
  Euristika neidealia, todėl atsisiuntus verta peržiūrėti `storage/app/stock-photos/portfolio`.
- **Saugumas.** Failu iš interneto aklai nepasitikim: dydis ≤ 10 MB (pagal `Content-Length` ir skaitant srautą), turinys
  – tikras JPEG/PNG/WEBP (`getimagesizefromstring` + `finfo`, ne plėtinys ar antraštė), mažiausi matmenys, tik
  `http(s)` ir ne vidinio tinklo adresai (SSRF), autoriaus nuorodos – tik `http(s)` (kitaip `javascript:` nuoroda
  taptų XSS).
- **Demo portfolio.** `MediaGenerator` (Etapas 9a) sričiai su rinkiniu ima tikras nuotraukas (su autoriumi), kitoms –
  kaip anksčiau sugeneruotus paveikslėlius. Logotipai – visada sugeneruoti, avatarų – niekada. Be bibliotekos planas ir
  atsitiktinių skaičių seka nepasikeitė.
- **Seed'o greitis.** Kiekviena kategorijos nuotrauka – dvi miniatiūros iškart (≈ 0,15 s), todėl seed'as visada prisega
  12 sričių ir 4 svetainės nuotraukas (+2,5 s), o 49 antro lygio kategorijas – tik su `SEED_MEDIA=true` (dar +8 s).
  Kategorijų nuotraukos bibliotekoje sumažinamos iki 1600 px: miniatiūros ~40 % greitesnės (matuota).
- **Filament.** Oficialus įskiepis `filament/spatie-laravel-media-library-plugin`: kategorijos formoje – nuotraukos
  įkėlimas, sąraše – miniatiūra, naujas „simple" resource „Svetainės nuotraukos" (po eilutę kiekvienai vietai,
  redagavimas modaliniame lange, rodomas atsisiųstos nuotraukos autorius).
- **Rasta ir pataisyta pakeliui:** CSP blokavo FilePond (Filament failų įkėlimo) peržiūros Web Worker'į iš `blob:` –
  peržiūra buvo tuščia. Admin panelei pridėtas `worker-src 'self' blob:` (pastebėta tik tikrinant naršyklėje).

**Failai:** `app/Console/Commands/DownloadPhotos.php`, `app/Services/Photos/*` (šaltiniai `Providers/`, `PhotoDownloader`,
`PhotoFetcher`, `StockPhotoLibrary`, `PhotoCredit`…), `app/Actions/Photos/AttachLibraryPhoto.php`,
`database/data/photo_queries.php`, `config/photos.php`, `database/seeders/StockPhotoSeeder.php`,
`database/seeders/Support/OrphanedMediaFiles.php`, `database/seeders/Demo/MediaGenerator.php`,
`app/Http/Controllers/Site/PhotoCreditsController.php`, `app/Services/Site/PhotoCredits.php`, `routes/site.php`,
`resources/js/pages/public/PhotoCredits.vue`, `resources/js/components/site/PhotoCreditCard.vue`,
`app/Filament/Resources/SitePhotos/*`, `app/Filament/Support/StockPhotoFields.php`, kategorijos forma ir lentelė.

#### Išmoktos sąvokos

##### 1. Artisan komanda su parinktimis ir gražia išvestimi

```php
#[Signature('photos:download
    {--source=auto : Šaltinis: auto, pexels arba openverse}
    {--only=categories,site,portfolio : Ką atsisiųsti (kableliais)}
    {--force : Pakeisti jau atsisiųstas nuotraukas kitomis}
    {--per-category=8 : Kiek demo portfolio nuotraukų sričiai}')]
#[Description('Atsisiunčia nemokamas nuotraukas …')]
class DownloadPhotos extends Command
{
    public function handle(PhotoSources $sources, StockPhotoLibrary $library): int  // priklausomybės – iš konteinerio
    {
        $only = explode(',', (string) $this->option('only'));
        $this->components->info('Nuotraukų šaltiniai: Pexels → Openverse');
        $this->components->twoColumnDetail('Statyba ir remontas', '<fg=green>Pexels · Jonas</>');  // „label ..... detalė"
        $this->components->warn('…');  $this->components->error('…');

        return self::FAILURE;  // išėjimo kodas 1 – skriptai ir CI supranta, kad nepavyko
    }
}
```

- `{--force}` – jungiklis (true/false), `{--source=auto}` – parinktis su numatyta reikšme, `: tekstas` – aprašymas
  `php artisan help photos:download` išvestyje. Laravel 13 – atributai `#[Signature]`, `#[Description]` vietoj savybių.
- `$this->components` – Laravel konsolės komponentai (tokie pat kaip `migrate` išvestyje). `-v` – daugiau detalių
  (`$this->output->isVerbose()`).
- **Testuose:** `$this->artisan('photos:download', ['--only' => 'site'])->expectsOutputToContain('…')->assertSuccessful()`.
  → https://laravel.com/docs/13.x/artisan#writing-commands · https://laravel.com/docs/13.x/artisan#defining-input-expectations ·
  https://laravel.com/docs/13.x/console-tests

##### 2. HTTP klientas (`Http`) ir jo testavimas (`Http::fake`)

```php
$response = Http::withHeaders(['Authorization' => $key])  // Pexels raktas – antraštėje
    ->acceptJson()->connectTimeout(10)->timeout(30)
    ->get('https://api.pexels.com/v1/search', ['query' => 'landscaped garden', 'orientation' => 'landscape']);

$response->status();            // 200, 429 (per daug užklausų), 401 (blogas raktas)
$response->json('photos');      // JSON kelias su tašku: json('src.large2x')
$response->header('Retry-After');

// Dideliam failui – srautu: Guzzle neskaito viso atsakymo į atmintį, skaitom dalimis ir nutraukiam po 10 MB
$body = Http::withUserAgent($ua)->withOptions(['stream' => true])->get($url)->toPsrResponse()->getBody();
```

- Nėra ryšio, baigėsi laikas, SSL klaida – `Illuminate\Http\Client\ConnectionException`. HTTP 4xx/5xx išimties nemeta
  (nebent `->throw()`), todėl tikrinam `status()` / `failed()`.
- WordPress analogas – `wp_remote_get()` / `wp_remote_retrieve_body()`.

```php
Http::preventStrayRequests();   // jokia užklausa neišeis į tikrą internetą – be „fake" testas lūžta
Http::fake([
    'images.pexels.com/photos/1/*' => Http::failedConnection(),   // konkretesni šablonai – pirmiau
    'api.pexels.com/*' => Http::response(['photos' => [...]]),
    'images.pexels.com/*' => Http::response($jpegBytes, 200, ['Content-Type' => 'image/jpeg']),
    'api.openverse.org/*' => Http::sequence()->push([], 429, ['Retry-After' => '7'])->push([...]),
]);
Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'raktas') && $r['query'] === 'landscaped garden');
Http::assertNothingSent();
```

- Atsakymai turi būti **tokio pat formato kaip tikri** (`tests/Support/StockPhotos.php`), o paveikslėlis – tikras
  (GD sugeneruotas JPEG), nes komanda tikrina turinį.
- `Sleep::for(3100)->milliseconds()` vietoj `usleep()`: testuose `Sleep::fake()` pauzes tik užfiksuoja
  (`Sleep::assertSlept(...)`), todėl testai nelaukia.
  → https://laravel.com/docs/13.x/http-client · https://laravel.com/docs/13.x/http-client#testing ·
  https://laravel.com/docs/13.x/helpers#sleep

##### 3. Licencijos ir autorių nurodymas

| Licencija                 | Komerciškai | Keisti (apkirpti) | Autorių nurodyti               | Ar imam |
| ------------------------- | ----------- | ----------------- | ------------------------------ | ------- |
| Pexels License            | taip        | taip              | nebūtina (bet mandagu)         | taip    |
| CC0, Public Domain Mark   | taip        | taip              | ne                             | taip    |
| CC BY                     | taip        | taip              | **taip**                       | taip    |
| CC BY-SA                  | taip        | taip              | **taip** (+ ta pati licencija) | taip    |
| CC BY-NC, CC BY-ND ir kt. | NC – ne     | ND – ne           | taip                           | **ne**  |

- Autorystės nurodymas (CC rekomendacija „TASL"): **T**itle (pavadinimas), **A**uthor, **S**ource (kur paskelbta),
  **L**icense (su nuoroda). Todėl `credit` turi `title`, `author(_url)`, `source(_url)`, `license(_url)`.
- Licencija leidžia naudoti **nuotrauką**, bet ne nuotraukoje esančio žmogaus atvaizdą reklamai – tam reikia jo
  sutikimo (model release). Todėl demo portfolio – daiktai, o iš Openverse žmonių vengiama.
  → https://www.pexels.com/license/ · https://creativecommons.org/licenses/ ·
  https://wiki.creativecommons.org/wiki/Recommended_practices_for_attribution · https://docs.openverse.org/api/

##### 4. Failas iš interneto: ką tikrinti

```php
$info = getimagesizefromstring($bytes);        // [plotis, aukštis, IMAGETYPE_*] arba false
$mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);   // tikras tipas pagal turinį
```

- **Netikėk URL plėtiniu ir `Content-Type`** – serveris gali grąžinti HTML klaidos puslapį su `image/jpeg` antrašte.
- **SVG neimam** – jame gali būti JavaScript. GIF, TIFF – netinka nuotraukoms.
- **Dydis** – pirma `Content-Length` (nesiunčiam 50 MB failo), paskui skaitant (antraštė gali meluoti ar jos nebūti).
- **SSRF** – jei API grąžintų `http://127.0.0.1/…` ar `http://192.168.1.1/…`, serveris pats kreiptųsi į vidinį tinklą.
  Leidžiam tik `http(s)` ir viešus adresus (`filter_var(..., FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)`).
  → https://www.php.net/manual/en/function.getimagesizefromstring.php · https://owasp.org/www-community/attacks/Server_Side_Request_Forgery

##### 5. Medialibrary: failas iš kodo, custom properties

```php
$category->addMedia($libraryPath)
    ->preservingOriginal()                                   // kopijuoti, ne perkelti – originalas lieka bibliotekoje
    ->usingFileName('statyba-ir-remontas.jpg')
    ->withCustomProperties(['credit' => [...], 'stock_id' => 'pexels:2014422'])
    ->toMediaCollection('image');                            // singleFile – sena nuotrauka pakeičiama automatiškai

$media->getCustomProperty('credit');                         // masyvas iš JSON stulpelio
```

- **Kodėl ne `addMediaFromUrl()`:** jis netikrina dydžio prieš atsisiunčiant ir neleidžia bandyti kito rezultato; be to,
  mums reikia failo bibliotekoje (kitam seed'ui). Todėl atsisiunčiam patys, tikrinam ir prisegam iš disko.
- Miniatiūros `nonQueued()` daromos iškart `toMediaCollection()` metu – ir seed'e su `WithoutModelEvents`, nes jas kviečia
  `FileAdder`, o ne modelio įvykis. O `uuid` medialibrary priskiria `creating` įvykyje – seed'e nurodom patys
  (`withAttributes(['uuid' => Uuid::uuid5(...)])` – v5 iš vardo, todėl kiekvieną kartą tas pats).
- **Savas diskas** bibliotekai (`config/filesystems.php` → `stock-photos`) – testuose `Storage::fake('stock-photos')`
  jį pakeičia laikinu katalogu. Diską imam kiekvieną kartą iš naujo (`Storage::disk(...)`), o ne įsimenam konstruktoriuje
  – kitaip `Storage::fake()` po objekto sukūrimo nepaveiktų.
  → https://spatie.be/docs/laravel-medialibrary/v11/basic-usage/associating-files ·
  https://spatie.be/docs/laravel-medialibrary/v11/advanced-usage/using-custom-properties ·
  https://laravel.com/docs/13.x/filesystem#testing

##### 6. JSON stulpelis užklausose (`custom_properties->stock_id`)

```php
Media::query()
    ->whereNotNull('custom_properties->stock_id')
    ->groupBy('custom_properties->stock_id')       // SQLite: json_extract(...), MySQL: json_unquote(json_extract(...))
    ->selectRaw('MIN(id) as id')
    ->orderBy('id')
    ->paginate(24, pageName: 'puslapis');
```

- Laravel `->` kelią paverčia tos DB JSON funkcija, todėl ta pati užklausa veikia SQLite ir MySQL (patikrinta abiejose).
  Demo rinkinio nuotrauka prisegta prie dešimčių darbų – grupuojant ji autorių puslapyje rodoma vieną kartą.
- `paginate()` su `groupBy` pats suskaičiuoja grupes (`SELECT COUNT(*) FROM (…)`).
- **MySQL JSON stulpelis perrikiuoja raktus** (SQLite – ne): testuose masyvą lyginam `toEqual` (raktų tvarka nesvarbi),
  ne `toBe`.
  → https://laravel.com/docs/13.x/queries#json-where-clauses · https://laravel.com/docs/13.x/pagination

##### 7. Filament įskiepis ir „simple" resource

```bash
COMPOSER_ALLOW_SUPERUSER=1 composer require filament/spatie-laravel-media-library-plugin --prefer-install=source
```

```php
SpatieMediaLibraryFileUpload::make('image')->collection('image')->conversion('card')->image()
    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(10 * 1024);   // KB
SpatieMediaLibraryImageColumn::make('image')->collection('image')->conversion('card');   // pats prideda with('media')
TextEntry::make('image_credit')->state(fn (?Category $record) => ...)->visible(...);     // tekstas formoje (ne laukas)
```

- Paprastas `FileUpload` įrašytų failo kelią į modelio stulpelį; įskiepis įrašo **media įrašą** – su miniatiūromis ir
  custom properties, kaip ir visur projekte.
- **„Simple" resource** (`ManageRecords`): vienas puslapis, redagavimas modaliniame lange. `canCreate()` / `canDelete()`
  → `false`, nes vietas apibrėžia `SitePhotoKey` enum, o `mount()` trūkstamas eilutes sukuria (`SitePhoto::forKey()`).
- Filament antraštes daro „Title Case" („Svetainės Nuotraukos") – lietuviškai perrašom `getTitleCasePluralModelLabel()`.
- `orderByRaw()` Larastan reikalauja `literal-string` (apsauga nuo SQL injekcijos) – tik konstantos ir `?` parametrai.
- **Testuose:** `->fillForm(['image' => UploadedFile::fake()->image('a.jpg', 1600, 900)])->call('save')`, modaliniame
  lange – `->callAction(TestAction::make(EditAction::class)->table($record), data: ['photo' => UploadedFile::fake()…])`.
  → https://filamentphp.com/plugins/filament-spatie-media-library · https://filamentphp.com/docs/5.x/forms/file-upload ·
  https://filamentphp.com/docs/5.x/resources/overview#simple-modal-resources · https://filamentphp.com/docs/5.x/testing/overview

##### 8. CSP `worker-src`

FilePond (Filament failų įkėlimas) nuotraukos peržiūrą piešia Web Worker'yje, sukurtame iš `blob:` adreso. Jei CSP
neturi `worker-src`, naršyklė taiko `script-src` – o ten `blob:` nėra, todėl peržiūra tuščia (konsolėje „Refused to
create a worker"). Testai to nepagautų – reikėjo pažiūrėti naršyklėje.
→ https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Content-Security-Policy/worker-src

#### Naudingos komandos

**Savininko kompiuteryje (Windows PowerShell)** – pirmas kartas po sujungimo:

```powershell
cd C:\kelias\iki\laravel-service-marketplace
composer install                      # naujas paketas: filament/spatie-laravel-media-library-plugin
php artisan migrate
php artisan storage:link              # jei dar nedaryta – kad /storage/… nuotraukos būtų pasiekiamos

# 1. (Rekomenduojama) nemokamas Pexels raktas: https://www.pexels.com/api/ → „Get Started" → prisijunk → raktas.
#    Įrašyk į .env eilutę:  PEXELS_API_KEY=tavo_raktas
notepad .env
php artisan config:clear              # jei kada darei config:cache

# 2. Atsisiųsti viską (kategorijos, svetainės nuotraukos, demo portfolio rinkinys): Pexels ~3–5 min.
php artisan photos:download

# 3. Peržiūrėk storage\app\stock-photos\portfolio – nuotrauką su žmogumi ištrink ir paleisk dar kartą (atsisiųs kitą)
php artisan photos:download --only=portfolio

# Demo duomenys su tikromis portfolio nuotraukomis (vienkartinis kintamasis tik šiai komandai):
$env:SEED_MEDIA="true"; php artisan migrate:fresh --seed; Remove-Item Env:SEED_MEDIA
```

| Komanda                                                          | Ką daro                                                                   |
| ---------------------------------------------------------------- | ------------------------------------------------------------------------- |
| `php artisan photos:download`                                    | viskas; jau esamos praleidžiamos, iš bibliotekos prisegama be interneto   |
| `php artisan photos:download --source=openverse`                 | be rakto (CC licencijos; lėčiau – ~3 s pauzė tarp paieškų, ~5–10 min.)    |
| `php artisan photos:download --only=site`                        | tik svetainės nuotraukos (`categories`, `site`, `portfolio` – kableliais) |
| `php artisan photos:download --only=categories --force`          | kategorijų nuotraukas pakeisti kitomis (savų – nekeičia)                  |
| `php artisan photos:download --only=portfolio --per-category=12` | didesnis demo rinkinys                                                    |
| `php artisan photos:download -v`                                 | visos nepavykusių bandymų priežastys                                      |
| `php artisan help photos:download`                               | visos parinktys                                                           |
| `SEED_STOCK_PHOTOS=false php artisan migrate:fresh --seed`       | seed'as be atsisiųstų nuotraukų (kaip iki Etapo 10)                       |
| `php artisan test tests/Feature/Photos`                          | komandos testai (Http::fake)                                              |

`.env` (nebūtina): `PHOTOS_USER_AGENT="PaslauguPlatforma/1.0 (+https://tavo-domenas.lt; el@pastas.lt)"` – Openverse ir
Wikimedia prašo User-Agent su kontaktu (numatytasis – iš `APP_NAME` ir `APP_URL`).

#### Dažnos klaidos

- **Windows: `cURL error 60: SSL certificate problem`** – PHP neturi sertifikatų sąrašo. Atsisiųsk
  https://curl.se/ca/cacert.pem, `php.ini` (`php --ini` parodo, kur jis) įrašyk `curl.cainfo="C:\php\cacert.pem"` ir
  `openssl.cafile="C:\php\cacert.pem"`. Komanda šią klaidą atpažįsta: parodo patarimą ir to šaltinio toliau nebando
  (kitaip 60 kategorijų kartotų tą pačią klaidą). (Laravel Herd sertifikatus sutvarko pats.)
- **Raktas įrašytas, bet komanda sako „Openverse"** – `config:cache` palikta sena konfigūracija: `php artisan config:clear`.
- **„Pexels: išnaudotas užklausų limitas"** – 200 paieškų per valandą (pvz. keli `--force` iš eilės). Komanda pati pereina
  prie Openverse; arba palauk valandą. Openverse be rakto – ~200 per parą.
- **Po `migrate:fresh --seed` nėra 2 lygio kategorijų nuotraukų** – taip ir turi būti be `SEED_MEDIA` (seed'o greitis);
  `php artisan photos:download` jas prisega iš bibliotekos per kelias sekundes, be interneto.
- **Testai, kurie „kartais" kreipiasi į internetą** – visada `Http::preventStrayRequests()`; o kūrėjo `.env`
  `PEXELS_API_KEY` testuose nustatyk `null` (`config([...])`), kitaip testas eitų kitu keliu nei CI.
- **`Http::fake()` su tuo pačiu atsakymu kelioms užklausoms + `stream`** – visi gauna **tą patį** PSR srauto objektą:
  perskaitytas (ar uždarytas) srautas antrai užklausai tuščias („Stream is detached"). Todėl atsisiuntėjas srautą,
  jei galima, „atsuka" (`rewind()`) ir neuždaro.
- **Tas pats raktas `Http::fake()` masyve du kartus** – PHP palieka paskutinę reikšmę; konkretesni šablonai (pvz.
  `images.pexels.com/photos/1/*`) – atskiri raktai ir pirmiau.
- **Credit lyginimas `toBe` su MySQL** – lūžta dėl raktų tvarkos JSON stulpelyje; naudok `toEqual`.
- **Atsisiųstų nuotraukų commit'inimas** – `storage/app/*` (išskyrus `public/`, `private/`) Git'e ignoruojama, o
  `storage/app/public` – irgi; nuotraukos repozitorijoje neturi atsirasti.
- **Pamirštas `worker-src`** – Filament failo peržiūra tuščia, nors įkėlimas veikia (žr. 8 sąvoką).
