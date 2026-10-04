## Etapas 9c – Prenumeratų privalumai ir administratoriaus veiksmų pranešimai

> Juodraštis (lygiagretus darbas). Sujungiant perkeliama į `docs/LEARNING.md` → „Etapas 9".

**Būsena (ROADMAP.md → Etapas 9):**

- [x] Prenumeratų privalumai: kategorijų limitas (`max_categories`; be prenumeratos – riba iš `config`)
- [x] Prenumeratų privalumai: ženklelis „PRO" viešame profilyje ir katalogo kortelėse
- [x] Pranešimas teikėjui, kai administratorius pakeičia profilio būseną (patikrintas, paslėptas, užblokuotas, aktyvuotas)
- [x] Testai (SQLite ir MySQL) – šios dalies: 38 nauji testai (4 failai); visas rinkinys (753) praeina SQLite ir MySQL 8.0
- [x] `docs/DB_SCHEMA.md` (H → `subscription_plans`, pranešimai), `docs/PERFORMANCE.md` (3.5 – ženklelio užklausa)

### Ką darėm ir kodėl

- **Viena vieta „ką duoda prenumerata"** – `App\Services\Subscriptions\PlanBenefits`. Ji žino dabar galiojančią
  prenumeratą (`ProviderProfile::currentSubscriptions()` → scope `Subscription::current()`), skaito plano `features` per
  `PlanFeatures` ir atsako į du klausimus: kiek kategorijų galima turėti (`maxCategories()`) ir ar rodyti ženklelį
  (`hasBadge()`). Vedlys, katalogas, profilis ir kainų puslapis klausia jos – taisyklė niekur nedubliuojama.
- **Kategorijų riba vedlyje.** Be prenumeratos – `config('marketplace.free_max_categories')` (5), su planu –
  `max_categories` (10 / 30 / 100). Skaičiuojamos pivot eilutės, todėl visa grupė („Apdailos darbai") – viena.
  Serveryje tikrina `SyncProviderCategories` (lietuviška klaida), Vue medis neleidžia pažymėti daugiau ir rodo
  „Pasirinkta: X iš Y", plano pavadinimą ir nuorodą į `/kainos`.
- **Seni teikėjai virš ribos** (seed'e – iki 10 kategorijų, o nemokama riba 5) kategorijų nepraranda: jas galima palikti
  ar pašalinti, bet naujos pridėti negalima, kol iš viso daugiau nei riba. Vedlyje tai paaiškina geltonas perspėjimas.
- **Ženklelis „PRO"** – teikėjams, kurių galiojančios prenumeratos plane `badge: true` (Profesionalas, Verslas).
  Kortelėse ir profilyje – `has_pro_badge`; sąraše prenumeratos užkraunamos viena užklausa visam puslapiui.
  Rikiavimas nesikeičia (tai būtų atskiras produkto sprendimas – „mokamos TOP pozicijos").
- **Pranešimai teikėjui** apie administratoriaus veiksmus: `ProviderStatusChanged` (paslėptas, užblokuotas, aktyvus,
  atkurtas) su neprivaloma priežastimi ir `ProviderVerified`. Juos siunčia Actions (`ChangeProviderStatus`,
  `SetProviderVerification`, `UnbanUser`), todėl veikia nepriklausomai nuo to, iš kur veiksmas paleistas.
  Filament „Paslėpti" ir „Užblokuoti profilį" turi lauką „Priežastis teikėjui".

### Kaip išbandyti lokaliai

1. `php artisan migrate:fresh --seed` (seed'e ~15 % teikėjų turi prenumeratą, pusė jų galioja dabar; vidutiniškai
   5–6 kategorijos teikėjui, todėl daug kas viršija nemokamą 5 ribą).
2. Teikėjas virš ribos: `tinker` →
   `App\Models\ProviderProfile::active()->has('categories', '>', 5)->whereDoesntHave('subscriptions', fn ($q) => $q->current())->first()->user->email`,
   prisijunkite (`password`) ir atidarykite „Teikėjo profilis" → „Kategorijos": geltonas perspėjimas, „Pasirinkta: 10 iš 5",
   nepažymėtos kategorijos neaktyvios, bet pašalinti galima.
3. Ženklelis: `App\Models\Subscription::current()->whereIn('subscription_plan_id', [2, 3])->first()->providerProfile->slug`
   → `/meistrai/{slug}` ir paieška pagal pavadinimą.
4. Pranešimas: `/admin/teikejai` → teikėjas → „Paslėpti" su priežastimi → prisijungus tuo teikėju varpelyje (ikona
   `UserCog`) ir Mailpit / `storage/logs/laravel.log` (laiškas). Pranešimai siunčiami eilėje – turi veikti eilės darbuotojas (`composer run dev`).

### Produkto taisyklės (savininkas gali keisti)

| Taisyklė                                             | Dabar                                     | Kur keisti                                                |
| ---------------------------------------------------- | ----------------------------------------- | --------------------------------------------------------- |
| Kategorijų be prenumeratos                           | 5                                         | `.env` → `FREE_MAX_CATEGORIES` (`config/marketplace.php`) |
| Kategorijų su planu                                  | Startas 10, Profesionalas 30, Verslas 100 | `subscription_plans.features.max_categories`              |
| Kas gauna ženklelį                                   | planai su `badge: true`                   | `subscription_plans.features.badge`                       |
| Kas skaičiuojama kaip viena kategorija               | viena pivot eilutė (visa grupė – viena)   | `SyncProviderCategories`                                  |
| Ką daryti su perteklium pasibaigus planui            | nieko netrinti; naujų pridėti negalima    | `SyncProviderCategories::ensureWithinLimit()`             |
| `past_due` (nesumokėtas pratęsimas)                  | privalumų neduoda                         | `Subscription::current()` / `isCurrent()`                 |
| Ar galima išjungti būsenos pranešimus                | ne (paskyros žinia)                       | `IgnoresNotificationSettings`                             |
| Pranešimas užblokavus paskyrą / nuėmus „Patikrintas" | nesiunčiamas                              | `BanUser`, `SetProviderVerification`                      |

### Svarbiausi sprendimai ir alternatyvos

| Klausimas                       | Pasirinkta                                                   | Alternatyva ir kodėl ne                                                                                                                                                                |
| ------------------------------- | ------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Kur tikrinti kategorijų ribą    | Action (`SyncProviderCategories`) + `ValidationException`    | Form Request `max:N` – nepalaikytų „senų virš ribos" ir skaičiuotų atsiųstus ID, o ne eilutes po normalizavimo; savas `Rule` objektas – galima, bet tada normalizavimą tektų dubliuoti |
| Kaip sužinoti ženklelį sąraše   | eager loading `currentSubscriptions` (+1 užklausa puslapiui) | `withExists()` pagrindinėje užklausoje – subužklausa su `now()` keistų katalogo COUNT cache raktą kas sekundę (našumu panašu: MySQL ją vykdo tik 20 eilučių)                           |
| Kaip rasti „badge" planus       | planai perskaitomi PHP'e (`PlanBenefits`, viena užklausa)    | JSON SQL'e: MySQL `features->>'$.badge'`, SQLite `json_extract()` – skirtinga sintaksė; JOIN su planais kiekvienam sąrašui                                                             |
| Ar laikyti planus Cache         | ne, tik atmintyje vienai užklausai (`#[Scoped]`)             | `Cache::rememberForever` – seed'ai vyksta be model events, todėl cache liktų pasenęs; nauda – ~0,1 ms                                                                                  |
| Ar saugoti ženklelį stulpelyje  | ne – skaičiuojamas iš galiojančios prenumeratos              | `provider_profiles.has_badge` – reikėtų job'o, kuris jį nuimtų pasibaigus prenumeratai (ir jis vėluotų)                                                                                |
| Būsenos pranešimai nustatymuose | neišjungiami (kaip `ComplaintResolved`)                      | nauja grupė „Paskyra" – žmogus galėtų nesužinoti, kad jo profilis paslėptas, ir galvoti, kad platforma neveikia                                                                        |
| Kas siunčia pranešimą           | Action, po transakcijos                                      | Filament veiksmo closure – praleistų `UnbanUser` ir būsimus kitus kelius; observer'is ant `status` – nežinotų priežasties                                                              |
| Priežasties saugojimas          | tik pranešime (`notifications.data.reason`)                  | stulpelis `provider_profiles.status_reason` – kol kas niekur kitur nerodomas, migracija nereikalinga                                                                                   |

### Išmoktos sąvokos

#### 1. Produkto taisyklės – `config`, o ne konstanta kode

Skaičius, kurį gali norėti pakeisti savininkas (o ne programuotojas), laikom `config/marketplace.php`, reikšmę imam iš
`.env`:

```php
// config/marketplace.php
'free_max_categories' => (int) env('FREE_MAX_CATEGORIES', 5),

// kode – visada config(), ne env(): po `php artisan config:cache` env() už config failų grąžina null
$limit = max(1, (int) config('marketplace.free_max_categories'));
```

Testuose reikšmę galima pakeisti vienam testui: `config(['marketplace.free_max_categories' => 2]);`.
WordPress analogas – `define('…')` `wp-config.php` faile arba `get_option()` nustatymų puslapyje.
→ https://laravel.com/docs/13.x/configuration#accessing-configuration-values

#### 2. JSON stulpelis → tipizuotas objektas (value object)

`features` modelyje turi `'array'` cast'ą, bet masyvas gali būti `NULL`, be rakto ar su `"30"` vietoj `30`.
`PlanFeatures::fromArray()` vienoje vietoje nusprendžia, ką tai reiškia, o kitur rašom `$features->badge`:

```php
final readonly class PlanFeatures
{
    public function __construct(public ?int $maxCategories = null, public bool $badge = false, public bool $prioritySupport = false) {}

    public static function fromArray(?array $features): self { /* is_numeric, === true … */ }
}

$plan->planFeatures()->maxCategories; // 30 arba null
```

`readonly` – sukurto objekto pakeisti negalima (PHP 8.2), todėl jį saugu perduoti bet kur.
→ https://laravel.com/docs/13.x/eloquent-mutators#array-and-json-casting

#### 3. Scope + ryšys su sąlyga: „dabar galiojanti prenumerata"

```php
// Subscription – SQL atitikmuo isCurrent()
#[Scope]
protected function current(Builder $query): void
{
    $query->whereIn('subscriptions.status', [SubscriptionStatus::Active, SubscriptionStatus::Cancelled])
        ->where('subscriptions.starts_at', '<=', now())
        ->where('subscriptions.ends_at', '>', now());
}

// ProviderProfile – ryšys, kurį galima užkrauti iš anksto
public function currentSubscriptions(): HasMany
{
    return $this->hasMany(Subscription::class)->current();
}
```

Ta pati taisyklė parašyta du kartus (PHP `isCurrent()` ir SQL `current()`), todėl testas tikrina, kad jos sutampa
visiems atvejams (aktyvi, atšaukta, suplanuota, `past_due`, pasibaigusi). WordPress analogas – `WP_Query` argumentai,
įvilkti į funkciją.
→ https://laravel.com/docs/13.x/eloquent#local-scopes · https://laravel.com/docs/13.x/eloquent-relationships#constraining-eager-loads

#### 4. Eager loading, `withExists()` ar subužklausa?

Trys būdai sužinoti „ar teikėjas turi PRO" sąraše:

| Būdas                                                           | SQL                                                    | Kada tinka                                                              |
| --------------------------------------------------------------- | ------------------------------------------------------ | ----------------------------------------------------------------------- |
| `->with('currentSubscriptions')` (pasirinkta)                   | antra užklausa `WHERE provider_profile_id IN (…20 ID)` | kai reikia kelių laukų ar logikos PHP'e; nedidina pagrindinės užklausos |
| `->withExists(['subscriptions as pro' => fn ($q) => …])`        | `EXISTS (SELECT …)` pagrindinės užklausos `SELECT`'e   | kai reikia tik taip/ne ir pagrindinė užklausa paprasta                  |
| `->addSelect(['x' => Subscription::select(…)->whereColumn(…)])` | koreliuota subužklausa `SELECT`'e                      | kai reikia vienos reikšmės (pvz. paskutinio mokėjimo datos)             |

Mūsų atveju lėmė katalogo COUNT cache: jo raktas – visos užklausos SQL su parametrais, o `withExists()` sąlygoje būtų
`now()`, kuris keičiasi kas sekundę, todėl cache niekada nepasikartotų. Našumu abu būdai panašūs – patikrinta
`EXPLAIN ANALYZE` su pilnu seed'u: MySQL 8.0 projekcijos subužklausą vykdo tik grąžinamoms 20 eilučių (`loops=20`), net
rikiuojant be indekso (filesort), o eager loading užklausa trunka 0,07 ms (`docs/PERFORMANCE.md` 3.5). Pamoka: prieš
atmetant variantą „dėl našumo", verta pasimatuoti – spėjimas („subužklausa bus vykdoma 18 000 kartų") nepasitvirtino.
→ https://laravel.com/docs/13.x/eloquent-relationships#eager-loading ·
https://laravel.com/docs/13.x/eloquent-relationships#other-aggregate-functions · https://laravel.com/docs/13.x/eloquent#subquery-selects

#### 5. `#[Scoped]` – vienas objektas per užklausą

`PlanBenefits` planus perskaito vieną kartą ir laiko savyje. `#[Scoped]` atributas reiškia: konteineris grąžina tą patį
objektą visos HTTP užklausos (ar eilės darbo) metu, o kitai užklausai – naują. Taip „cache" negali pasenti ilgiau nei
vieną užklausą. Pakeitus planą (`SubscriptionPlan::booted()` → `saved`), sąrašas pamirštamas iš karto.
WordPress analogas – `static $cache` funkcijoje arba `wp_cache_set()` su nepastovia grupe.
→ https://laravel.com/docs/13.x/container#binding-scoped

#### 6. Validacija su dinamine riba

Riba priklauso nuo prisijungusio teikėjo plano **ir** nuo dabartinio pasirinkimo, todėl ją tikrina Action ir meta tą
pačią klaidą, kokią mestų Form Request:

```php
throw ValidationException::withMessages([
    'category_ids' => __('plan_benefits.categories.over_limit', ['limit' => $limit, 'noun' => $noun, 'count' => $count]),
]);
```

Inertia ją gauna kaip `form.errors.category_ids` – Vue kodas nesiskiria nuo įprastos validacijos. Form Request'e liko tik
struktūra (`array`, `min:1`, `exists`) ir techninė apsauga nuo milžiniškų masyvų (`max:300`). Alternatyva – savas
taisyklės objektas (`php artisan make:rule WithinCategoryLimit`), kuris gautų ribą per konstruktorių.
→ https://laravel.com/docs/13.x/validation#manually-creating-validators · https://laravel.com/docs/13.x/validation#custom-validation-rules

#### 7. Daugiskaita lietuviškai – `trans_choice`

Lietuvių kalba turi tris formas (1, 21… | 2–9, 22–29… | 10–20, 30…). Laravel jas žino:

```php
// lang/lt/plan_benefits.php: 'noun_genitive' => 'kategorijos|kategorijų|kategorijų'
trans_choice('plan_benefits.categories.noun_genitive', 21); // „kategorijos" → „iki 21 kategorijos"
```

Vue pusėje tą patį daro `plural()` iš `@/lib/format` (`Intl.PluralRules('lt')`).
→ https://laravel.com/docs/13.x/localization#pluralization

#### 8. Pranešimai po administratoriaus veiksmų

- Siunčia **Action, po transakcijos** – jei pakeitimas atšaukiamas (klaida), laiškas neišeina.
- **Neišjungiami** pranešimai: trait'as `IgnoresNotificationSettings` perrašo `BaseNotification::via()` – varpelis
  visada, laiškas tik patvirtintu el. paštu. Trait'o metodas gali ir įgyvendinti abstraktų tėvinės klasės metodą
  (`settingsGroup()`), ir perrašyti paveldėtą (`via()`).
- **Nuoroda** skaičiuojama pagal **dabartinę** profilio būseną (`NotificationTarget::providerAccountUrl()`): aktyvus –
  viešas profilis, nebaigtas – vedlys, paslėptas ar užblokuotas – „Mano paskyra". Senas pranešimas veda teisingai, net
  jei būsena vėliau pasikeitė.
- Testuose `Notification::fake()` + `assertSentTo($user, ProviderStatusChanged::class, fn ($n, $channels) => …)` –
  callback'as gauna ir kanalus (`['mail', 'database']`).
- WordPress analogas – `wp_mail()` + `add_user_meta()` „admin notice" vartotojui.
  → https://laravel.com/docs/13.x/notifications · https://laravel.com/docs/13.x/mocking#notification-fake

#### 9. Filament veiksmas su forma ir jo testas

```php
Action::make('hide')
    ->requiresConfirmation()
    ->schema([Textarea::make('reason')->label('Priežastis teikėjui (neprivaloma)')->maxLength(500)])
    ->action(fn (ProviderProfile $record, array $data) => app(ChangeProviderStatus::class)->handle($record, ProviderStatus::Hidden, $data['reason'] ?? null));

// testas (pest-plugin-livewire neįdiegtas – Livewire::test)
Livewire::test(ListProviderProfiles::class)
    ->callAction(TestAction::make('hide')->table($profile), ['reason' => 'Dvigubas profilis']);
```

→ https://filamentphp.com/docs/5.x/actions/modals · https://filamentphp.com/docs/5.x/testing/testing-actions

### Naudingos komandos

```bash
php artisan config:show marketplace                 # dabartinė nemokama riba
php artisan config:clear                            # pakeitus .env (jei config buvo cache'intas)
php artisan tinker
>>> $p = App\Models\ProviderProfile::first();
>>> app(App\Services\Subscriptions\PlanBenefits::class)->maxCategories($p);
>>> app(App\Services\Subscriptions\PlanBenefits::class)->hasBadge($p);
>>> App\Models\Subscription::query()->current()->count();   # kiek prenumeratų galioja dabar
php artisan test --filter='CategoryLimit|PlanBenefits|ProBadge|ProviderStatusNotification'
# tie patys testai su MySQL
DB_CONNECTION=mysql DB_DATABASE=laravel_test DB_USERNAME=… DB_PASSWORD=… DB_URL= php artisan test
```

### Dažnos klaidos

- **Ryšys, užkrautas su keliais stulpeliais, ir pranešimai.** Filament sąraše savininkas užkraunamas
  `user:id,first_name,last_name,email,banned_at` – be `email_verified_at`. Tokiam objektui `hasVerifiedEmail()` grąžina
  `false`, ir laiškas „tyliai" nebeišsiunčiamas (liko tik varpelis). Todėl Actions gavėją užkrauna iš naujo:
  `User::query()->find($profile->user_id)`. Testas tai pagavo per `$channels === ['mail', 'database']`.
- **`now()` užklausoje, kuri yra cache rakto dalis.** Raktas `sha1($query->toRawSql())` su laiku keičiasi kas sekundę –
  cache niekada nepataikytų, o lentelėje kauptųsi raktai. Laikas – tik atskiroje (eager loading) užklausoje.
- **Pamirštas eager loading sąraše.** `PlanBenefits::hasBadge()` skaito `$profile->currentSubscriptions`; jei sąraše jis
  neužkrautas, `preventLazyLoading` meta `LazyLoadingViolationException` (ir gerai – tai N+1). Vienam profiliui
  Laravel jį užkrauna pats.
- **Testuose tas pats prisijungęs objektas per kelias užklausas.** `actingAs($user)` palieka tą patį `User` objektą, o jo
  `providerProfile` ir `currentSubscriptions` ryšiai užkraunami tik kartą. Tikroje užklausoje taip nebūna, todėl testas
  po prenumeratos pakeitimo prisijungia nauju objektu (`$profile->user()->firstOrFail()`). Panašiai `#[Scoped]` objektai
  teste išlieka tarp užklausų – `app()->forgetScopedInstances()` imituoja naują užklausą.
- **Dvi tos pačios taisyklės versijos.** `isCurrent()` (PHP) ir `current()` (SQL) turi sutapti – kitaip vedlys ir
  katalogas „matytų" skirtingas prenumeratas. Saugo testas, lyginantis abu rezultatus.
- **Klientas tikrina, serveris – sprendžia.** Vue medis neleidžia pažymėti per daug, bet tai tik patogumas: tą pačią
  taisyklę (`next.size <= max || viskas iš išsaugotų`) tikrina `SyncProviderCategories`.
