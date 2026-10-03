# Etapas 5 – Užklausos ir pasiūlymai (platformos šerdis)

> **Juodraštis `docs/LEARNING.md` skilčiai.** Sujungiant šakas jį reikia perkelti į `LEARNING.md` ir pažymėti
> `ROADMAP.md` punktus.

## ROADMAP būsena

| Punktas                                                                                              | Būsena                                                                                                                                                       |
| ---------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Užklausos kūrimo forma (keli žingsniai, nuotraukos), Form Request validacija                         | **atlikta, be nuotraukų.** Nuotraukos – po sujungimo su Etapu 3 (medialibrary diegia Etapas 3): kolekcija `photos` `ServiceRequest` modelyje + 1-as žingsnis |
| Moderavimas (`pending` → `open`) Filament'e + automatinės taisyklės                                  | atlikta                                                                                                                                                      |
| Atitikimas: job randa teikėjus ir siunčia pranešimus (mail + database) pagal `notification_settings` | atlikta                                                                                                                                                      |
| Teikėjo užklausų srautas su filtrais                                                                 | atlikta                                                                                                                                                      |
| Pasiūlymo siuntimas: kreditų patikra, `DB::transaction` + `lockForUpdate`, ledger įrašas             | atlikta                                                                                                                                                      |
| Klientas mato pasiūlymus, priima arba atmeta; būsenų perėjimai pagal `docs/STATES.md`                | atlikta                                                                                                                                                      |
| Darbo užbaigimas ir atšaukimas; Scheduler uždaro pasibaigusias užklausas                             | atlikta                                                                                                                                                      |
| Pranešimų varpelis (neperskaityti)                                                                   | atlikta (+ pranešimų puslapis ir „Pranešimai" nustatymuose)                                                                                                  |
| Testai: visas srautas, lygiagretus kreditų nurašymas                                                 | atlikta (lygiagretumas SQLite'e tikrinamas nuosekliai – žr. 6 sąvoką)                                                                                        |
| `docs/LEARNING.md`: Etapas 5 + santrauka vartotojui                                                  | šis juodraštis                                                                                                                                               |

**Sąmoningai nepadaryta (kitų etapų darbas):**

- nuotraukos užklausoje – po Etapo 3 sujungimo;
- kvietimas palikti atsiliepimą po „Darbas atliktas" ir teikėjo prašymas pažymėti darbą atliktu – Etapas 6
  (atsiliepimai, žinutės);
- priminimas klientui, kai užklausa `in_progress` stovi 60 d. (`STATES.md` papildoma taisyklė) – kartu su Etapo 6
  priminimais;
- rate limiting užklausoms ir pasiūlymams – Etapas 6 („Rate limiting: žinutės, skundai, užklausos");
- kreditų pirkimas („Nepakanka kreditų" kol kas tik pranešimas) – Etapas 7;
- viešas užklausų sąrašas be prisijungimo – roadmap'e nėra.

---

### Ką darėm ir kodėl

- **Būsenų mašina enum'uose** – `ServiceRequestStatus` ir `OfferStatus` gavo `allowedTransitions()`,
  `canTransitionTo()`, `isFinal()`. Kiekvienas perėjimas – atskira **Action** klasė (`app/Actions/ServiceRequests`,
  `app/Actions/Offers`), vykdoma `DB::transaction()` viduje. Lentelė „perėjimas → Action → Policy → pranešimai" –
  `docs/STATES.md` 4 sk.
- **Kreditų ledger** – `App\Services\Credits\CreditLedger` (`debit`, `credit`, `refund`). Vienintelė vieta, kuri keičia
  `credits_balance`; ją naudos ir Etapas 7 (pirkimai, prenumeratos, admin koregavimai).
- **Atitikimas** – `App\Services\Matching\ProviderMatcher`: viena taisyklė „kuris teikėjas tinka kuriai užklausai",
  naudojama pranešimams, teikėjo srautui, Policy ir pasiūlymo siuntimui.
- **Daugiažingsnė užklausos forma** (`/uzklausos/nauja`): paslauga (paieška kategorijų medyje) → aprašymas →
  vieta ir laikas → biudžetas ir peržiūra. Kiekvienas žingsnis tikrinamas serveryje per **Precognition**.
- **Automatinis moderavimas** – `App\Services\Moderation\AutoModerator`: paskelbiama iškart, jei el. paštas patvirtintas
  ir tekste nėra nuorodų, el. pašto adresų ar telefono numerių. Kitaip – `pending`, ir užklausą tvirtina administratorius
  Filament'e (`/admin/uzklausos`), kur matomos tos pačios priežastys. Patvirtinus el. paštą, laukiančios užklausos
  patikrinamos dar kartą (listener'is `PublishPendingRequestsAfterVerification`).
- **Pranešimai** – 7 Notification klasės (mail + database), siunčiamos eilėje, pagal `users.notification_settings`
  (struktūra – `DB_SCHEMA.md` → users). Paskelbus užklausą job'as `NotifyMatchingProviders` praneša visiems tinkamiems
  teikėjams, skaitydamas juos dalimis po 500.
- **Puslapiai**: „Mano užklausos", užklausos puslapis klientui ir teikėjui (adresas ir kontaktai – tik išrinktam
  teikėjui), pasiūlymo puslapis (atidarius nustatomas `viewed_at`), teikėjo srautas su filtrais, „Mano pasiūlymai",
  varpelis antraštėje, `/pranesimai`, nustatymai → „Pranešimai".
- **Scheduler** – `php artisan service-requests:expire` kas valandą uždaro pasibaigusias užklausas ir grąžina kreditus
  tik už neatidarytus pasiūlymus.
- **Nauja migracija** `add_cancellation_reason_to_service_requests_table` – atmetimo / atšaukimo priežastis.
- **Testai**: 150 naujų (iš viso 266): visas srautas per HTTP, visos `STATES.md` 3 sk. grąžinimo taisyklės, Policies,
  Filament veiksmai, varpelis, laiškų tekstai, Scheduler.

### Išmoktos sąvokos

#### 1. Daugiažingsnė forma ir Precognition

Visi formos laukai laikomi **vienoje** `useForm()` būsenoje, o žingsniai tik rodo jų dalį (`v-show`). Kad nereikėtų
validacijos taisyklių rašyti dukart (PHP ir JavaScript), „Toliau" mygtukas siunčia **Precognition** užklausą: tą patį
`POST /uzklausos` su antraštėmis `Precognition: true` ir `Precognition-Validate-Only: title,description`. Laravel
paleidžia `StoreServiceRequestRequest` taisykles tik tiems laukams ir grąžina 422 su klaidomis arba 204 – controller'is
nevykdomas, niekas neišsaugoma.

```ts
const form = useForm(store(), { category_id: null, title: '', … });
form.validate({ only: ['title', 'description'], onSuccess: () => step.value++ });
```

Maršrutui reikia middleware `HandlePrecognitiveRequests`. Alternatyvos: validuoti tik paskutiniame žingsnyje
(vartotojas klaidas pamato per vėlai) arba kiekvieną žingsnį saugoti DB kaip juodraštį (sudėtingiau, reikia „pusiau
sukurtų" užklausų valymo). → https://laravel.com/docs/13.x/precognition · https://inertiajs.com/forms#precognition

#### 2. Form Request: ne tik taisyklės

- `authorize()` gali grąžinti `Gate::inspect(...)` – atsisakius vartotojas mato priežastį („Šiai užklausai pasiūlymą jau
  išsiuntėte"), o ne bendrą „403".
- `attributes()` ir `messages()` – lietuviški laukų pavadinimai ir pranešimai iš `lang/lt/service_requests.php`.
- `Rule::when($this->filled('budget_min'), 'gte:budget_min')` – sąlyginė taisyklė (be jos `gte` lygintų su `null`).
- Metodas `toServiceRequestAttributes()` paverčia formos duomenis modelio atributais: **eurai → centai**
  (`(int) round($price * 100)`, nes `12.3 * 100 = 1229.999…`).
  → https://laravel.com/docs/13.x/validation#form-request-validation

#### 3. Action klasės

Viena verslo operacija = viena klasė su vienu viešu metodu `handle()`. Controller'is lieka 5–10 eilučių:
`Gate::authorize()` → `$action->handle()` → flash pranešimas → nukreipimas. Tą pačią operaciją kviečia ir controller'is,
ir Filament veiksmas, ir Scheduler komanda, ir testai. Priklausomybės (`CreditLedger`, `ProviderMatcher`) gaunamos per
konstruktorių – Laravel **service container** jas sukuria pats. Tai ne Laravel „funkcija", o susitarimas (kaip
WordPress'e logiką iškelti iš šablono į klasę). → https://laravel.com/docs/13.x/container

#### 4. Būsenų mašina enum'e

```php
public function allowedTransitions(): array
{
    return match ($this) {
        self::Pending => [self::Open, self::Cancelled],
        self::Open => [self::InProgress, self::Cancelled, self::Expired],
        …
    };
}
```

Kiekviena Action, prieš keisdama būseną, klausia `canTransitionTo()`. Jei negalima – `InvalidStateTransitionException`,
kurios `render()` metodas vartotoją grąžina atgal su klaidos pranešimu (Laravel pats kviečia `render()`).
Alternatyva – `spatie/laravel-model-states` (`STATES.md` pradžia). → https://laravel.com/docs/13.x/errors#renderable-exceptions

#### 5. Policies

`ServiceRequestPolicy` (`view`, `create`, `cancel`, `complete`, `publish`, `viewAny`) ir `OfferPolicy` (`create`, `view`,
`withdraw`, `accept`, `decline`). Laravel jas randa pagal pavadinimą. Naudojimas:

- controller'yje `Gate::authorize('accept', $offer)` (atsisakius – 403);
- Vue puslapiui siunčiam `can: { accept: $user->can('accept', $offer) }`, kad mygtukas būtų rodomas tik kai galima;
- **Filament** pats naudoja tą pačią Policy (`viewAny` – ar rodyti meniu punktą, `->authorize('publish')` veiksmams).

Policy tikrina ir būseną (kad UI būtų teisingas), bet galutinai ją dar kartą patikrina Action, užrakinusi eilutę.
`Response::deny('priežastis')` leidžia atsisakymą paaiškinti. → https://laravel.com/docs/13.x/authorization#creating-policies

#### 6. DB transakcijos ir pesimistinis užraktas (`lockForUpdate`)

**Transakcija** – „viskas arba nieko": pasiūlymas, kreditų nurašymas ir `offers_count + 1` įvyksta kartu arba
neįvyksta visai. → https://laravel.com/docs/13.x/database#database-transactions

**Kodėl vien transakcijos neužtenka.** Teikėjas turi 1 kreditą ir vienu metu (du skirtukai) siunčia du pasiūlymus.
Be užrakto MySQL'e:

| Laikas | Užklausa A                     | Užklausa B                     |
| ------ | ------------------------------ | ------------------------------ |
| 1      | `SELECT credits_balance` → 1   |                                |
| 2      |                                | `SELECT credits_balance` → 1   |
| 3      | 1 ≥ 1, nurašo → `UPDATE … = 0` |                                |
| 4      |                                | 1 ≥ 1, nurašo → `UPDATE … = 0` |

Abu pasiūlymai išsiųsti, nurašytas tik vienas kreditas (arba, jei rašytume `credits_balance - 1`, balansas taptų −1).
Su `lockForUpdate()` (`SELECT … FOR UPDATE`) B 2-ame žingsnyje **laukia**, kol A baigs transakciją, ir tada perskaito jau
0 – „Nepakanka kreditų". Užraktas laikomas tik iki `COMMIT`, todėl transakcijos turi būti trumpos (pranešimus siunčiam
po jų). → https://laravel.com/docs/13.x/queries#pessimistic-locking

**Užraktų tvarka.** Visos operacijos rakina ta pačia tvarka: užklausa → pasiūlymai → teikėjai (didėjančia ID tvarka).
Jei viena rakintų A → B, o kita B → A, abi lauktų viena kitos amžinai (**deadlock**); MySQL tai aptinka ir vieną
transakciją atšaukia, bet geriau to išvengti.

**SQLite (dev ir testai).** `lockForUpdate()` SQLite'e nieko nedaro – SQLite rašymus ir taip vykdo po vieną (visa DB
užrakinama rašymo metu). Todėl testuose tikrinam tai, ką galima: du nuoseklūs siuntimai su pasenusiu modeliu, kai
kreditų užtenka vienam – antras atmetamas, balansas ne neigiamas, ledger suma = balansas. Tikrą lygiagretumą reikėtų
tikrinti MySQL'e dviem procesais (Etapas 8).

**Papildomi saugikliai:** `UNIQUE(service_request_id, provider_profile_id)` – net jei kas nors praslystų, DB neleis
antro pasiūlymo; `credits_balance` – `unsigned`, MySQL neleis neigiamo.

#### 7. Ledger ir idempotentiškas grąžinimas

`CreditLedger::refund($offer)` neieško „ar jau grąžinta" atskiru stulpeliu – jis susumuoja visus to šaltinio ledger
įrašus (−2 nurašymas + 2 grąžinimas = 0). Jei suma 0 – grąžinti nebėra ką. Todėl kvietimas du kartus (pvz. pakartotas
job'as) negrąžins dvigubai. **Idempotentiškumas** = operaciją galima saugiai pakartoti. Etape 7 tas pats principas
saugos nuo dvigubo Paysera callback'o.

#### 8. Jobs ir eilės

`NotifyMatchingProviders implements ShouldQueue` – darbas atidedamas į eilę, klientas nelaukia. Svarbu:

- `Queueable` trait'e yra `SerializesModels`: į eilę įrašomas tik modelio ID, vykdant modelis paimamas iš DB
  (todėl job'as tikrina, ar užklausa vis dar `open`);
- `dispatch(...)->afterCommit()` – į eilę patenka tik transakcijai pavykus;
- `chunkById(500, …)` – teikėjai skaitomi dalimis, ne visi iš karto;
- `$tries = 3` – nepavykus bandoma dar kartą.

Dev'e eilė – `database` (lentelė `jobs`), vykdo `php artisan queue:work` (paleidžia `composer run dev`). Testuose
`QUEUE_CONNECTION=sync` – job'ai vykdomi iškart. WordPress analogas – `wp_schedule_single_event()`, tik patikimesnis.
→ https://laravel.com/docs/13.x/queues

#### 9. Notifications: vienas pranešimas – keli kanalai

```php
class NewOffer extends BaseNotification   // ShouldQueue
{
    public function via(object $notifiable): array { /* ['mail', 'database'] pagal nustatymus */ }
    public function toMail(object $notifiable): MailMessage { … }
    public function toArray(object $notifiable): array { return ['offer_id' => …, 'message' => '…']; }
}
$client->notify(new NewOffer($offer));
```

- `database` kanalas įrašo `toArray()` į lentelę `notifications` – iš jos varpelis (`$user->unreadNotifications()`,
  `->markAsRead()`).
- `via()` skaito `users.notification_settings` (`App\Support\NotificationSettings`); nepatvirtintu el. paštu laiškų
  nesiunčiam.
- Eilėje modelis atkuriamas be ryšių – `toMail()` juos užkrauna `loadMissing()` (ne lazy loading).
- Kur veda paspaudimas, skaičiuojama iš `type` + `data` (`NotificationTarget`), todėl veikia ir seed'ų pranešimai.
  → https://laravel.com/docs/13.x/notifications

#### 10. Events ir listeners (≈ WordPress hooks)

Patvirtinus el. paštą Laravel paskelbia įvykį `Illuminate\Auth\Events\Verified`. Mūsų listener'is
`PublishPendingRequestsAfterVerification` į jį reaguoja: laukiančias užklausas patikrina dar kartą ir paskelbia.
Listener'ių registruoti nereikia – Laravel randa juos `app/Listeners` pagal `handle()` parametro tipą (kaip
`add_action('user_verified', …)`). → https://laravel.com/docs/13.x/events

#### 11. Scheduler (≈ `wp_cron`, tik patikimesnis)

```php
// routes/console.php
Schedule::command('service-requests:expire')->hourly()->withoutOverlapping()->onOneServer();
```

Serveryje vienas cron įrašas kas minutę paleidžia `php artisan schedule:run`, o Laravel sprendžia, kas vykdoma dabar.
Skirtingai nei `wp_cron`, nepriklauso nuo lankytojų. `withoutOverlapping()` – nepradėti, jei ankstesnis dar dirba;
`onOneServer()` – keliuose serveriuose vykdyti tik viename. Komandos klasė – su PHP atributais
`#[Signature('service-requests:expire')]`. → https://laravel.com/docs/13.x/scheduling

#### 12. Route model binding pagal slug ir `scopeBindings()`

`Route::get('uzklausos/{serviceRequest:slug}', …)` – Laravel pats suranda užklausą pagal `slug` arba grąžina 404.
`/uzklausos/{serviceRequest:slug}/pasiulymai/{offer}` su `->scopeBindings()` – pasiūlymas turi priklausyti tai
užklausai, kitaip 404 (negalima „pasižiūrėti" svetimo pasiūlymo pakeitus ID). Wayfinder'is Vue pusėje sugeneruoja
`show({ slug })`. → https://laravel.com/docs/13.x/routing#implicit-model-binding-scoping

#### 13. API Resources – tik reikalingi laukai

`ServiceRequestResource::make($request)->withPrivateDetails($isChosen)->resolve()` – adresas į Vue patenka tik klientui
ir išrinktam teikėjui (`$this->when(...)` lauką visai praleidžia). Sąraše pasiūlymo žinutė – tik ištrauka, nes visa
žinutė = „atidarė" (`viewed_at`), nuo to priklauso kreditų grąžinimas. Puslapiuotas sąrašas
(`::collection($paginator)`) Vue pusėje turi `data`, `links`, `meta`. → https://laravel.com/docs/13.x/eloquent-resources

#### 14. Inertia: bendri props, `useHttp`, flash

- `HandleInertiaRequests::share()` – `notifications.unread_count` kiekviename puslapyje. Reikšmė – **closure**: ji
  vykdoma tik kai prop'o reikia (dalinis perkrovimas su `only` jos neskaičiuoja). → https://inertiajs.com/shared-data
- Varpelio sąrašas užkraunamas tik jį atidarius – `useHttp().get(latest.url())` (Inertia 3 JSON užklausa, puslapis
  nepersikrauna). → https://inertiajs.com/http-requests
- `Inertia::flash('toast', [...])` – vienkartinis pranešimas po nukreipimo (rodomas kaip toast).

#### 15. Filament: veiksmai, skirtukai, infolist

- `Action::make('approve')->requiresConfirmation()->visible(...)->authorize('publish')->action(...)` – mygtukas su
  patvirtinimo langu; `->schema([Textarea::make('reason')->required()])` – veiksmas su forma (atmetimo priežastis).
- `ListRecords::getTabs()` – skirtukai „Laukia patvirtinimo / Atviros / Visos"; `getNavigationBadge()` – skaitliukas meniu.
- `ViewRecord` + `Infolist` (`TextEntry`, `IconEntry`, `Section`) – peržiūros puslapis be redagavimo.
- Enum'as su `HasLabel` ir `HasColor` – `TextColumn::make('status')->badge()` pats parodo lietuvišką pavadinimą ir spalvą.
  → https://filamentphp.com/docs/5.x/actions/overview

#### 16. Testai

- `Notification::fake()` + `Notification::assertSentTo($user, NewOffer::class, fn ($n) => …)` – pranešimai
  nesiunčiami, tik įsimenami. `Queue::fake()` + `Queue::assertPushed(NotifyMatchingProviders::class)`.
- `assertInertia(fn (Assert $page) => $page->component('offers/Show')->where('can.accept', true)->missing('serviceRequest.address'))`.
- `assertInertiaFlash('toast.message', '…')` – flash pranešimas.
- Filament: `Livewire::test(ViewServiceRequest::class, [...])->callAction('approve')`, lentelės veiksmui –
  `TestAction::make('reject')->table($record)`.
- `$this->freezeSecond()` – laikas „sustabdomas", kad būtų galima lyginti `published_at` su `now()`.
- Pagalbininkai `Tests\Support\Marketplace::eligibleProvider()` – tinkamas teikėjas užklausai.

#### 17. Larastan ir modelių cast'ai

Larastan pagal nutylėjimą žiūri tik į `casts()` metodo grąžinamą tipą (`array`), todėl `$request->status` laikė
`string`. `phpstan.neon` → `parseModelCastsMethod: true` – Larastan perskaito `casts()` turinį ir žino, kad tai enum'as.

### Naudingos komandos

| Komanda                                                    | Ką daro                                                   |
| ---------------------------------------------------------- | --------------------------------------------------------- |
| `php artisan queue:work`                                   | vykdo eilės job'us (pranešimai, laiškai)                  |
| `php artisan queue:work --stop-when-empty`                 | įvykdo, kas eilėje, ir baigia                             |
| `php artisan queue:failed` / `queue:retry all`             | nepavykę job'ai ir jų kartojimas                          |
| `php artisan service-requests:expire`                      | rankiniu būdu uždaro pasibaigusias užklausas              |
| `php artisan schedule:list`                                | suplanuotos užduotys ir kada jos vyks                     |
| `php artisan schedule:work`                                | dev'e vykdo Scheduler'į kas minutę (vietoj cron)          |
| `php artisan route:list --path=uzklausos`                  | Etapo 5 maršrutai                                         |
| `php artisan make:notification NewOffer`                   | nauja Notification klasė                                  |
| `php artisan make:policy OfferPolicy --model=Offer`        | nauja Policy                                              |
| `php artisan make:job NotifyMatchingProviders`             | naujas job'as                                             |
| `php artisan make:filament-resource ServiceRequest --view` | Filament resource su peržiūros puslapiu                   |
| `php artisan wayfinder:generate`                           | perkurti Vue maršrutų funkcijas (daro ir `npm run build`) |

### Dažnos klaidos

- **Pranešimas siunčiamas transakcijos viduje.** Jei transakcija vėliau nepavyks, žmogus gaus žinią apie neįvykusį
  dalyką. Pranešimus siunčiam po `DB::transaction()`, job'ams – `->afterCommit()`.
- **`lockForUpdate()` be transakcijos** – užraktas atleidžiamas iškart po `SELECT`, t. y. nieko nesaugo.
- **`chunk()`, kai cikle keičiama ta pati sąlyga** (`status = open` → `expired`) – kitas „puslapis" (`OFFSET`)
  praleidžia dalį eilučių. Naudok `chunkById()`: jis tęsia nuo paskutinio ID (`WHERE id > ?`).
- **Būsena tikrinama tik Policy'je.** Tarp puslapio atidarymo ir paspaudimo ji gali pasikeisti – Action turi tikrinti
  dar kartą, užrakinusi eilutę.
- **`loadMissing('ryšys:id,name')` pranešime** – modelio ryšys lieka su dalimi stulpelių, ir kitas kodas (pvz.
  `$offer->providerProfile->user`) gauna `null`. Bendrai naudojamiems modeliams stulpelių neribok.
- **`gte:budget_min`, kai `budget_min` tuščias** – Laravel lygina su `null` ir atmeta. Naudok `Rule::when(...)`.
- **Precognition testas be JSON** – `post()` gauna 302 nukreipimą; naudok `postJson()`, tada 422 / 204.
- **Puslapio komponento nėra Vite manifeste** – po naujo `.vue` puslapio sukūrimo testams ir naršyklei reikia
  `npm run build` (arba `npm run dev`).
- **Notification `type` pervadinimas** – DB saugomas klasės vardas (`App\Notifications\NewOffer`); pervadinus klasę
  seni pranešimai „pasimestų". Tipą galima užfiksuoti metodu `databaseType()`.
