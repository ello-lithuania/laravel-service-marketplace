# Etapas 7 – Kreditai, prenumeratos, mokėjimai

> **Juodraštis `docs/LEARNING.md` skilčiai.** Sujungiant šakas jį reikia perkelti į `LEARNING.md` ir pažymėti
> `ROADMAP.md` punktus.

## ROADMAP būsena

| Punktas                                                                              | Būsena                                                                                 |
| ------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------- |
| Kainų puslapis: kreditų paketai ir planai                                            | atlikta – `/kainos` (SEO, nuorodos meniu ir poraštėje)                                 |
| `PaymentGateway` sąsaja + Paysera įgyvendinimas (parašo tikrinimas, idempotencija)   | atlikta – Paysera be SDK (ss1 / ss2), testinis tiekėjas `fake` dev'ui                  |
| Kreditų ledger paslauga, balansas, istorijos puslapis                                | atlikta – `CreditLedger` iš Etapo 5 + `/teikejas/kreditai`, `/teikejas/mokejimai`      |
| Prenumeratos: pirkimas, kreditai kas laikotarpį (Scheduler), pratęsimas, atšaukimas… | atlikta – `subscriptions:renew` (kasdien), `subscriptions:grant-credits` (kas valandą) |
| Sąskaitos faktūros (numeracija, PDF)                                                 | atlikta – `SF-{metai}-{000123}`, `invoice_sequences`, dompdf                           |
| Filament: mokėjimai, kreditų operacijos, rankinis koregavimas                        | atlikta – + prenumeratos, pajamų suvestinė                                             |
| Testai su netikru mokėjimų tiekėju                                                   | atlikta – 85 nauji testai (iš viso 516)                                                |
| `docs/LEARNING.md`: Etapas 7 + santrauka vartotojui                                  | šis juodraštis                                                                         |

**Sąmoningai nepadaryta:**

- **Automatinis kortelės nuskaitymas** (recurring) – Paysera mūsų sąrankoje to nedaro; „automatinis pratęsimas" =
  priminimas su nuoroda apmokėti (žr. 7 sąvoką). Stripe atveju tai darytų pats Stripe.
- **Pinigų grąžinimas (refund)** – būsena `refunded` yra, bet veiksmo nėra: grąžinimą daro administratorius Paysera
  savitarnoje, kreditus atima „Koreguoti kreditus". Kreditinė sąskaita – vėliau.
- **Plano privalumų įgyvendinimas** (`max_categories`, ženklelis profilyje) – planų `features` kol kas tik rodomi
  kainų puslapyje.
- **„Suma žodžiais"** sąskaitoje ir PDF archyvas (dabar PDF generuojamas kaskart iš užfiksuotų rekvizitų).
- **Stripe** – sąsaja paruošta, įgyvendinimo nėra (kaip numatyta CLAUDE.md).
- **Užšifruoti Paysera callback'ai** (projekto nustatymas su AES-GCM) – palaikom tik pasirašytus (ss1 / ss2).

---

### Ką darėm ir kodėl

- **Schema pirma** (`DB_SCHEMA.md`): `payments.subscription_id` (kurios prenumeratos laikotarpis apmokamas),
  `payments.billing_details` (sąskaitos rekvizitų snapshot'as), `subscriptions.credits_granted_until` (kreditų
  suteikimo idempotencija), nauja lentelė `invoice_sequences`. Trys naujos migracijos, senos nekeistos.
  Seed'ai papildyti: prenumeratų mokėjimai susieti su prenumerata, kreditai pažymėti suteiktais.
- **Mokėjimų tiekėjai už sąsajos**: `App\Services\Payments\PaymentGateway` (interface) ir du įgyvendinimai –
  `PayseraGateway` ir `FakeGateway`. Kurį naudoti, sprendžia `PaymentGatewayManager` pagal `config('payments.default')`.
  Bindings – atskirame `PaymentServiceProvider` (`bootstrap/providers.php`).
- **Pirkimo eiga**: „Pirkti" → `PurchaseCreditPackage` / `PurchaseSubscriptionPlan` sukuria **laukiantį** mokėjimą →
  `Inertia::location()` nukreipia į tiekėją → tiekėjo serveris kviečia **callback'ą** → `ProcessPaymentResult`
  (idempotentiškai) → `CompletePayment`: kreditai per `CreditLedger`, prenumerata, sąskaitos numeris, snapshot'as →
  po transakcijos `PaymentSucceeded`. Pirkėjas grįžta į `/mokejimai/{uuid}` – puslapis laukia patvirtinimo (`usePoll`).
- **Prenumeratos**: `ActivateSubscription`, `RenewSubscription`, `CancelSubscription`, `CreateRenewalPayment`,
  `EndSubscriptionPeriod`, `GrantSubscriptionCredits`; būsenų perėjimai – `SubscriptionStatus::allowedTransitions()`,
  lentelė – `DB_SCHEMA.md` → subscriptions.
- **Sąskaitos**: `InvoiceNumberGenerator` (užrakinamas metų skaitiklis), `BillingDetails` (snapshot),
  `InvoicePdf` + `resources/views/invoices/invoice.blade.php` (dompdf, DejaVu Sans). PVM – konfigūruojamas
  (`INVOICE_VAT_PAYER`, `INVOICE_VAT_RATE`): kainos DB laikomos galutinėmis, PVM išskiriamas iš jų.
- **Pranešimai**: `PaymentSucceeded`, `SubscriptionExpiring` (ir „nesumokėta" variantas), `LowCredits` – nauja
  nustatymų grupė `billing`. `LowCredits` siunčia `CreditTransactionObserver`, todėl pasiūlymų kodo keisti nereikėjo.
- **Puslapiai**: `/kainos`, `/teikejas/kreditai`, `/teikejas/mokejimai`, `/mokejimai/{uuid}`, testinio tiekėjo puslapis;
  „Nepakanka kreditų" dabar veda pirkti; meniu – „Kreditai" ir „Mokėjimai".
- **Filament** („Finansai"): mokėjimai (filtrai, paieška, PDF, pajamų suvestinė), kreditų operacijos (tik skaityti +
  „Koreguoti kreditus"), prenumeratos (atšaukti).

### Kaip išbandyti lokaliai

1. `.env` – `PAYMENT_GATEWAY=fake` (numatyta). `php artisan migrate`, `npm run build`, `composer run dev`.
2. Prisijunkite `teikejas1@example.test` / `password` → „Kreditai" → „Pirkti". Atsidarys **testinis tiekėjas**:
   „Apmokėti" / „Mokėjimas nepavyko" / „Atšaukti". Po apmokėjimo – kreditai, sąskaita PDF „Mokėjimai" skiltyje.
3. Prenumerata: `/kainos` → „Prenumeruoti". Laiko „sukimui" – `php artisan subscriptions:renew` ir
   `php artisan subscriptions:grant-credits` (arba `php artisan schedule:work`).
4. **Paysera testinis režimas**: Paysera savitarnoje sukurkite projektą, `.env` – `PAYMENT_GATEWAY=paysera`,
   `PAYSERA_PROJECT_ID`, `PAYSERA_SIGN_PASSWORD`, `PAYSERA_TEST=true`. Paysera serveris `localhost` nepasiekia, todėl
   callback'ui reikia tunelio (`ngrok http 8000`) ir `PAYSERA_CALLBACK_URL=https://….ngrok.app/mokejimai/callback/paysera`.
   Viešąjį raktą parsiųskite `php artisan payments:paysera-key`.

**Produkcijoje:** `PAYMENT_GATEWAY=paysera`, `PAYSERA_TEST=false`, `php artisan payments:paysera-key` diegiant,
cron su `php artisan schedule:run` kas minutę, eilės darbuotojas (laiškai). Testinis tiekėjas produkcijoje uždraustas
dviem saugikliais (`PaymentGatewayManager` ir 404 maršrute).

---

### Išmoktos sąvokos

#### 1. Service container ir sąsaja (interface)

Sąsaja – „sutartis": kokius metodus klasė privalo turėti, bet ne kaip juos įgyvendinti.

```php
interface PaymentGateway
{
    public function type(): GatewayType;                          // kas įrašoma į payments.gateway
    public function startPayment(Payment $payment): string;       // kur nukreipti pirkėją
    public function handleCallback(Request $request): PaymentResult; // patikrinti parašą → mūsų DTO
    public function acknowledge(PaymentResult $result): Response; // Paysera laukia „OK"
}
```

Controller'is ir Actions žino tik `PaymentGateway`, todėl Stripe pridėti = nauja klasė + vienas metodas manager'yje.
**Service container** – Laravel „objektų dėžė": paprašius tipo konstruktoriuje, jis pats sukuria objektą. Ko pats
neatspėja (sąsajai – kuri klasė? Paysera klasei – kokie nustatymai?), nurodom service provider'yje:

```php
$this->app->singleton(PaymentGatewayManager::class);
$this->app->bind(PaymentGateway::class, fn ($app) => $app->make(PaymentGatewayManager::class)->gateway());
```

`bind` – kaskart naujas objektas, `singleton` – vienas visai užklausai. WordPress analogas – WooCommerce
`WC_Payment_Gateway`, kurią paveldi kiekvienas mokėjimo įskiepis, o WooCommerce kviečia jos `process_payment()`.
→ https://laravel.com/docs/13.x/container · https://laravel.com/docs/13.x/providers

#### 2. Manager šablonas (drivers)

`PaymentGatewayManager extends Illuminate\Support\Manager` – tas pats šablonas kaip `Cache::store('redis')`,
`Mail::mailer('ses')`: `create{Vardas}Driver()` metodas kiekvienam tiekėjui, `getDefaultDriver()` – iš config.
Svarbu: nauji mokėjimai eina per numatytąjį tiekėją, o callback'as ir „Apmokėti dar kartą" – per tą, kuris įrašytas
mokėjime (`payments.gateway`). Pakeitus numatytąjį, seni laukiantys mokėjimai nesulūžta.

#### 3. Paysera be SDK: kodavimas ir parašai

- **Užklausa**: `data = base64url(http_build_query(parametrai))`, `sign = md5(data + slaptažodis)`, nukreipiam į
  `https://bank.paysera.com/pay/?data=…&sign=…`. `amount` – centais (kaip mūsų DB), `orderid` – `payments.uuid`.
- **Callback'as**: tas pats `data` + `ss1` ir `ss2`.
    - `ss1 = md5(data + slaptažodis)` – patikimas tol, kol slaptažodis slaptas;
    - `ss2` – RSA (SHA1) parašas Paysera **privačiu** raktu; tikrinam jų **viešuoju** raktu (`openssl_verify`).
      Net nutekėjus mūsų slaptažodžiui, ss2 suklastoti neįmanoma – todėl jis numatytasis.
- Viešasis raktas: failas (`payments:paysera-key` jį parsiunčia diegiant) → cache parai → parsiuntimas. Nepavykus –
  callback'as **atmetamas** („fail closed"), o ne priimamas be patikros.
- Po parašo dar tikrinam: projekto numerį, ar testinis mokėjimas neatėjo į produkciją, sumą ir valiutą.
- Lyginimui – `hash_equals()`, ne `===`: laikas nepriklauso nuo sutapusių simbolių skaičiaus („timing" ataka).
- Kodėl be oficialaus `libwebtopay`: sąsaja paprasta (~100 eilučių), o taip matyti, kas vyksta „po gaubtu".
  → https://developers.paysera.com/en/checkout/basic · https://www.php.net/manual/en/function.openssl-verify.php

#### 4. Callback'ai ir webhook'ai

Callback'ą (webhook'ą) kviečia **tiekėjo serveris**, ne vartotojo naršyklė. Todėl:

- maršrutas be `auth` ir be CSRF – `bootstrap/app.php`: `$middleware->preventRequestForgery(except: ['mokejimai/callback/*'])`
  (Laravel 13 pavadinimas; senesnis `validateCsrfTokens` – pasenęs). Vienintelė apsauga – parašas;
- **accepturl ≠ apmokėjimas**: pirkėjas gali grįžti ir neapmokėjęs, o URL'ą galima suklastoti. Kreditus užskaito tik
  patikrintas callback'as; grįžimo puslapis rodo „Laukiame patvirtinimo" ir kas 3 s atsinaujina (`usePoll`);
- atsakymas „OK" – kitaip Paysera kartoja. Klaidos atveju – 400 ir įrašas log'e.
  → https://laravel.com/docs/13.x/csrf#csrf-excluding-uris

#### 5. Idempotencija – „galima kartoti saugiai"

Tas pats callback'as gali ateiti 2, 5, 10 kartų. `ProcessPaymentResult`:

```php
DB::transaction(function () use ($result) {
    $payment = Payment::where('uuid', $result->paymentUuid)->lockForUpdate()->first();
    if ($payment->status === PaymentStatus::Paid) {
        return; // jau apdorota – nieko nedarom, bet atsakom „OK"
    }
    // … paid, kreditai, sąskaitos numeris – viskas toje pačioje transakcijoje
});
```

Trys sluoksniai: (1) užrakinta eilutė – du lygiagretūs callback'ai vyksta po vieną; (2) būsenos patikra;
(3) DB saugikliai – `UNIQUE(gateway, gateway_reference)` ir ledger'is su `source = payment`.
Tas pats principas – `GrantSubscriptionCredits` (žr. 8) ir `CreateRenewalPayment` (antro laukiančio mokėjimo nekuria).

#### 6. Ledger'is pakartotinai – nekuriant naujo

Etapo 5 `CreditLedger` (`credit`, `debit`) naudojamas visur: pirkimas (`purchase`, šaltinis – mokėjimas), prenumerata
(`subscription`, šaltinis – prenumerata), administratoriaus koregavimas (`admin_adjustment`, šaltinis – administratorius,
neigiamas – per `debit`, todėl balansas negali tapti < 0). `CreditLedger::credit()` turi savo `DB::transaction()`;
iškvietus kitos transakcijos viduje, tai tampa **savepoint'u** – viskas vis tiek įvyksta kartu arba neįvyksta.

**Užraktų tvarka** visur ta pati: mokėjimas → teikėjas → prenumerata → sąskaitų skaitiklis. Jei viena operacija
rakintų A → B, o kita B → A, MySQL'e gautume deadlock (Etapo 5 6 sąvoka).

#### 7. Prenumeratos be Cashier: dizainas ir alternatyvos

- Viena eilutė = viena prenumerata, daug laikotarpių; `ends_at` – iki kada apmokėta.
- **Pratęsimas**: kasdien `subscriptions:renew` likus 7 d. sukuria pratęsimo mokėjimą ir išsiunčia nuorodą
  (`SubscriptionExpiring`). Apmokėjus – `ends_at` + 1 laikotarpis. Neapmokėjus – `past_due` 3 d. malonės laikotarpiui,
  tada `expired`. Laikotarpis skaičiuojamas nuo senos pabaigos (vėlavimas „nedovanojamas"), kaip Stripe.
- **Atšaukimas** – `auto_renew = false`, galioja iki `ends_at` (už laikotarpį sumokėta).
- **Plano keitimas** paprastas: naujas planas prasideda pasibaigus dabartiniam, dabartinė nebepratęsiama.
  Vienu metu galioja tik viena prenumerata (užrakinta teikėjo eilutė). Proporcingas perskaičiavimas („proration")
  būtų sudėtingesnis ir reikalautų dalinių grąžinimų.
- **Alternatyvos**: _Laravel Cashier_ (Stripe/Paddle) – prenumeratas, korteles, sąskaitas ir webhook'us tvarko pats,
  turi savo lenteles; Lietuvoje populiarios Paysera jis nepalaiko. _Stripe Billing be Cashier_ – kortelę nuskaito Stripe,
  mes tik gautume `invoice.paid` webhook'ą ir prailgintume `ends_at` (tas pats `RenewSubscription`). _Paysera
  pasikartojantys mokėjimai_ – reikia atskiros sutarties ir kitos API. Mūsų sprendimas veikia su bet kuriuo tiekėju,
  nes pratęsimas – tiesiog dar vienas mokėjimas.
  → https://laravel.com/docs/13.x/billing

#### 8. Scheduler ir idempotentiški kreditai „kas laikotarpį"

```php
Schedule::command('subscriptions:renew')->dailyAt('08:00')->timezone('Europe/Vilnius')->withoutOverlapping()->onOneServer();
Schedule::command('subscriptions:grant-credits')->hourly()->withoutOverlapping()->onOneServer();
```

Kodėl kreditai ne iškart apmokėjus: pratęsimą galima apmokėti iš anksto, o pakeistas planas prasideda vėliau.
Kreditai suteikiami laikotarpiui **prasidėjus**. `credits_granted_until` – iki kada jau suteikta:

```php
$start = $sub->credits_granted_until ?? $sub->starts_at;
if ($start < $sub->ends_at && $start <= now()) {         // prasidėjęs ir apmokėtas
    $ledger->credit(...);                                  // + kreditai
    $sub->credits_granted_until = $period->addTo($start); // toje pačioje transakcijoje
}
```

Kartojant (Scheduler kas valandą, rankinis paleidimas) laikotarpis antrą kartą kreditų negauna – be atskiros lentelės.
Ciklas suteikia ir praleistus laikotarpius, jei Scheduler kurį laiką neveikė. WordPress `wp_cron` priklauso nuo
lankytojų, o Laravel Scheduler – nuo vieno serverio cron įrašo. → https://laravel.com/docs/13.x/scheduling

#### 9. Sąskaitų numeracija ir „snapshot'as"

- Ištisinė numeracija be tarpų metų viduje: `invoice_sequences` eilutė užrakinama (`lockForUpdate`), numeris
  išduodamas **toje pačioje** transakcijoje, kurioje mokėjimas tampa `paid`. Atšaukus transakciją, atšaukiamas ir
  skaitiklis. `MAX(invoice_number) + 1` netinka – du lygiagretūs apmokėjimai gautų tą patį numerį.
- Pirmą kartą metuose skaitiklis pradedamas nuo didžiausio jau esančio numerio (seed'ai naudoja mokėjimo ID).
  `insertOrIgnore` – jei kita transakcija ką tik įterpė tą pačią eilutę, klaidos nėra.
- Metai – pagal **Lietuvos** laiką (gruodžio 31 d. 23:30 UTC jau sausio 1-oji).
- **Snapshot'as** (`billing_details`): išrašytos sąskaitos keisti negalima, todėl rekvizitai įrašomi apmokėjimo metu,
  o ne imami iš profilio kaskart generuojant PDF.

#### 10. PDF: Blade + dompdf

```php
Pdf::loadView('invoices.invoice', $data)->setPaper('a4')->download('SF-2026-000123.pdf');
```

Dompdf HTML ir CSS paverčia PDF be naršyklės. Supranta tik dalį CSS (be flex/grid – išdėstymas lentelėmis).
Lietuviškoms raidėms – Unicode šriftas DejaVu Sans (platinamas su dompdf). Alternatyvos: Browsershot (Chrome –
modernus CSS, bet serveryje reikia Node ir Chromium), Snappy (wkhtmltopdf – nebeprižiūrimas).
→ https://github.com/barryvdh/laravel-dompdf

#### 11. Observer po COMMIT (`LowCredits`)

```php
#[ObservedBy([CreditTransactionObserver::class])]
class CreditTransaction extends Model { … }

class CreditTransactionObserver implements ShouldHandleEventsAfterCommit
{
    public function created(CreditTransaction $t): void { /* balansas perėjo ribą → LowCredits */ }
}
```

Observer reaguoja į modelio įvykius (kaip WordPress `save_post`), todėl `SendOffer` neliestas. `ShouldHandleEventsAfterCommit`
– vykdoma tik transakcijai pavykus. Pranešimas – tik **perėjus** ribą (3 → 2), ne po kiekvieno pasiūlymo.
→ https://laravel.com/docs/13.x/eloquent#observers-and-database-transactions

#### 12. Inertia: išorinis nukreipimas ir polling

- `Inertia::location($url)` – Inertia užklausai grąžina 409 + `X-Inertia-Location`, ir naršyklė atidaro Paysera visu
  langu (paprastas redirect'as būtų bandomas įkelti kaip Inertia puslapis). → https://inertiajs.com/redirects
- `usePoll(3000, { only: ['payment'] }, { autoStart })` – kas 3 s perkrauna tik vieną prop'ą; sustabdom, kai būsena
  pasikeičia arba po 2 min. → https://inertiajs.com/polling

#### 13. Filament: veiksmas su forma, widget'as, filtrai

- „Koreguoti kreditus" – `Action::make()->schema([Select, TextInput, Textarea])->requiresConfirmation()->action(...)`;
  teikėjų tūkstančiai, todėl `Select::getSearchResultsUsing()` ieško serveryje.
- `Filter::make('created_at')->schema([DatePicker…])->query(...)` – datų intervalas.
- `StatsOverviewWidget` – pajamų suvestinė (`getHeaderWidgets()` sąrašo puslapyje). Widget'ai krauna „tingiai" (lazy).
- Policies be `create`/`update`/`delete` metodų → Filament tų mygtukų ir puslapių nerodo.
- Lietuviški pavadinimai: Filament „Title Case" (`Kreditų Operacijos`) – perrašom `getTitleCasePluralModelLabel()`.

#### 14. Testai be tinklo

- `Http::preventStrayRequests()` + `Http::fake([...])` – jokių tikrų užklausų į paysera.com.
- Paysera ss2 – testuose sugeneruojam RSA porą (`openssl_pkey_new`), pasirašom privačiu raktu, viešąjį įrašom į laikiną
  failą (`tests/Support/PayseraKeys`).
- `$this->travelTo('2026-04-11 08:00')` + `$this->artisan('subscriptions:renew')` – prenumeratos ciklas per kelias
  „dienas" vienu testu.
- Idempotencija: tas pats callback'as POST + POST + GET → vienas ledger įrašas, vienas pranešimas.
- `app()->detectEnvironment(fn () => 'production')` – patikrina, kad testinio tiekėjo produkcijoje nėra.
- Filament – `Livewire::test(ListCreditTransactions::class)->callAction('adjustCredits', [...])`.

### Naudingos komandos

| Komanda                                             | Ką daro                                                      |
| --------------------------------------------------- | ------------------------------------------------------------ |
| `php artisan subscriptions:renew`                   | pasibaigusios → past_due / expired, pratęsimo mokėjimai      |
| `php artisan subscriptions:grant-credits`           | kreditai už prasidėjusius apmokėtus laikotarpius             |
| `php artisan payments:paysera-key`                  | parsiunčia Paysera viešąjį raktą (ss2)                       |
| `php artisan schedule:list` / `schedule:work`       | suplanuotos užduotys / vykdyti dev'e                         |
| `php artisan route:list --path=mokejimai`           | mokėjimų maršrutai                                           |
| `php artisan make:filament-resource Payment --view` | Filament resource su peržiūra                                |
| `composer require barryvdh/laravel-dompdf`          | PDF paketas (cloud konteineryje – `--prefer-install=source`) |

### Dažnos klaidos

- **Kreditai užskaitomi accepturl'e** (kai pirkėjas grįžta) – URL'ą galima atidaryti ir neapmokėjus. Tik callback'as.
- **Callback'as be idempotencijos** – antras „status=1" padvigubina kreditus. Užraktas + būsenos patikra.
- **Callback'as su CSRF** – Paysera gauna 419 ir kartoja be galo. Išimtis `preventRequestForgery(except: …)`.
- **Testinis Paysera mokėjimas produkcijoje** – `test=1` callback'as turi būti atmestas, kai `PAYSERA_TEST=false`.
- **Netikras tiekėjas produkcijoje** = nemokami kreditai. Du saugikliai: manager'is ir 404 maršrute.
- **`MAX() + 1` sąskaitos numeriui** – lygiagrečiai gaunami vienodi numeriai; ir sąskaitos numeris už transakcijos ribų
  palieka tarpus numeracijoje.
- **`isPast()` lyginant su „dabar"** – tą pačią sekundę sukurta prenumerata (`starts_at == now`) nėra „praeityje";
  naudok `! isFuture()`.
- **Ryšys į soft-deleted vartotoją** grąžina `null` – finansiniams įrašams `belongsTo(User::class)->withTrashed()`.
- **Pinigų formatas be centų** – `Intl.NumberFormat` su `minimumFractionDigits: 0` rodo „9,9 €"; mūsų puslapiai
  naudoja `formatPrice()` (`lib/format.ts`). Pastaba: Etapo 5 `formatMoney()` (`lib/marketplace.ts`) turi tą pačią
  problemą (pvz. „320,5 €") – verta pataisyti sujungus.
- **`withHeader('X-Inertia', 'true')` testuose išlieka** kitoms tos pačios testo užklausoms – `flushHeaders()`.
