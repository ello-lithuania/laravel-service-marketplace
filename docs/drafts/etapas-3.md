# Etapas 3 – juodraštis `docs/LEARNING.md` skyriui

## ROADMAP būsena (Etapas 3)

- [x] Registracija su rolės pasirinkimu (Klientas / Paslaugų teikėjas), vardas ir pavardė
- [x] El. pašto patvirtinimas ir slaptažodžio atkūrimas lietuviškai
- [x] Starter kit puslapiai (prisijungimas, registracija, paskyra, nustatymai) išversti į lietuvių kalbą
- [x] Inertia bendri props: `auth.user` – tik reikalingi laukai
- [x] `role` middleware (buvo), Policies pagrindiniams modeliams: `ProviderProfilePolicy`, `PortfolioItemPolicy`
      (`ServiceRequestPolicy` ir `OfferPolicy` – Etape 5, kartu su tais srautais)
- [x] Filament prieiga tik `admin` rolei – padaryta Etape 2
- [x] Teikėjo profilio vedlys: duomenys → kategorijos → zonos → kainos „nuo"
- [x] Profilio redagavimas, logotipas ir avataras (medialibrary)
- [x] Portfolio CRUD su nuotraukomis
- [x] Feature testai: registracija, prieigos teisės, profilio vedlys, įkėlimai, portfolio, `auth.user` forma
- [ ] `docs/LEARNING.md`: Etapas 3 – šis juodraštis, į `LEARNING.md` perkels sujungimas

**Nepadaryta (ir kodėl):**

- **Demo nuotraukos seed'e (`SEED_MEDIA`, `docs/SEEDING.md` 7 sk.)** – nebuvo Etapo 3 darbų sąraše; lentelė jau
  yra, seeder'į galima pridėti vėliau.
- **Filament medialibrary įskiepis** (nuotraukų peržiūra admin panelėje) – prireiks moderuojant (Etapas 8).
- **Viešas profilis `/meistrai/{slug}`** – Etapas 4. Jam paruošta `ProviderProfilePolicy::view()` ir
  `logoUrl()` / `coverUrl()` / `PortfolioItem::imageUrls()`.

---

## Etapas 3 – Autentifikacija, rolės, teikėjo profilis

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
