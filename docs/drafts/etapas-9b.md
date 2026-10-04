# Etapas 9b – Mokėjimo grąžinimas, kreditinė sąskaita, suma žodžiais (juodraštis)

> Juodraštis `docs/LEARNING.md` Etapo 9 skyriui (lygiagretus darbas, šaka `worktree-agent-a4a1ad56826703f33`).
> Sujungus perkelti į `docs/LEARNING.md`, o šį failą ištrinti.

**Būsena:**

- [x] Mokėjimo grąžinimas Filament'e: būsena `refunded`, kreditų atėmimas per ledger'į (balansas niekada < 0),
      prenumeratos sutrumpinimas, pranešimas teikėjui (`PaymentRefunded`)
- [x] Kreditinė sąskaita faktūra: atskira numeracija `KS-YYYY-NNNNNN`, PDF, atsisiuntimas teikėjui ir administratoriui
- [x] Suma žodžiais lietuviškai sąskaitoje faktūroje ir kreditinėje sąskaitoje (`App\Support\AmountInWords`)
- [x] Teikėjo puslapiai: „Mokėjimai" ir mokėjimo puslapis rodo grąžinimą ir kreditinę sąskaitą; BDAR archyve – grąžinimai
- [x] Testai: visas rinkinys praeina su SQLite (856) ir MySQL 8.0 (850 + 5 praleisti, kaip ir anksčiau)
- Sąmoningai nepadaryta: Paysera grąžinimo API (pinigus administratorius grąžina savitarnoje – žr. žemiau), daliniai
  grąžinimai, grąžinimai seed'uose (kad nesikirstų su lygiagrečiu seed'ų darbu), pajamų suvestinė „grąžinta per mėnesį".

### Ką darėm ir kodėl

- **Schema pirma** (`docs/DB_SCHEMA.md`): nauja lentelė `refunds` (grąžinimas + kreditinė sąskaita, `UNIQUE(payment_id)`),
  `invoice_sequences` gavo `series` stulpelį ir sudėtinį pirminį raktą `(series, year)`, `PaymentStatus` perėjimų
  lentelė, `CreditTransactionType::payment_refund`, naujas enum'as `InvoiceSeries`. Dvi naujos migracijos, senos nekeistos.
- **Grąžinimo eiga**: Filament → Mokėjimai → apmokėtas mokėjimas → „Grąžinti pinigus" → priežastis + privaloma varnelė
  „pinigus grąžinsiu tiekėjo savitarnoje" → `RefundPayment`:
  vienoje DB transakcijoje užrakina mokėjimą, patikrina būseną, užrakina teikėją ir prenumeratą, atima kreditus
  (`CreditLedger::debit`), sutrumpina prenumeratą, išduoda `KS` numerį, sukuria `refunds` eilutę → po COMMIT –
  `PaymentRefunded` (mail + database, nustatymų grupė `billing`).
- **Peržiūra prieš patvirtinant**: patvirtinimo lange administratorius mato pasekmes – „bus atimta 12 kred., 18 atimti
  nepavyks", „prenumerata bus baigta iškart". Tą patį skaičiavimą (`RefundCalculator`) vėliau pakartoja `RefundPayment`
  su užrakintomis eilutėmis.
- **Kreditinė sąskaita**: tas pats dompdf šablonas (`resources/views/invoices/invoice.blade.php`) su `$credit_note`
  bloku – „Kreditinė (PVM) sąskaita faktūra", „Koreguojama sąskaita faktūra: SF-…", priežastis, sumos su minusu,
  „Iš viso grąžinama". Rekvizitai – grąžinimo metu nukopijuotas `billing_details` snapshot'as.
  Maršrutas `GET /mokejimai/{payment}/kreditine-saskaita`, teisės – `PaymentPolicy::downloadCreditNote`.
- **Grąžinto mokėjimo sąskaita faktūra lieka** atsisiunčiama (`Payment::hasInvoice()` – ir `refunded` būsenai):
  sąskaitos neištrinsi, ją „atšaukia" kreditinė sąskaita.
- **Suma žodžiais**: `AmountInWords::eur(2420)` → „dvidešimt keturi eurai 20 ct", sąskaitoje – su didžiąja raide.
- **Teikėjo pusė**: „Mokėjimai" – po sąskaitos numeriu kreditinės sąskaitos nuoroda; mokėjimo puslapis – atskira
  būsena „Mokėjimas grąžintas" (anksčiau grąžintas mokėjimas rodė „Pinigai nenuskaičiuoti"), data, priežastis,
  kiek kreditų atimta. Varpelio ikona `PaymentRefunded` pranešimui.

### Verslo taisyklės (DB_SCHEMA → `refunds`)

- **Pinigai grąžinami visi.** Dalinių grąžinimų nėra (lentelė jiems paruošta).
- **Kreditai – ne daugiau nei balansas.** Kreditų paketas: atimama tiek, kiek suteikė šis mokėjimas (ledger eilučių su
  `source = payment` suma). Jei teikėjas dalį jau išleido – atimama, kiek yra, o skirtumas įrašomas į
  `refunds.credits_shortfall` ir parodomas administratoriui ir teikėjui. Balansas niekada netampa neigiamas.
- **Prenumerata – „vienas mokėjimas = vienas laikotarpis".** Grąžinus mokėjimą, prenumerata sutrumpinama paskutiniu
  apmokėtu laikotarpiu: jei jis jau prasidėjo (ką tik nupirktas planas) – prenumerata baigiama iškart ir atimami to
  laikotarpio kreditai; jei dar neprasidėjo (iš anksto apmokėtas pratęsimas) – `ends_at` grąžinamas atgal, prenumerata
  `cancelled` ir nebepratęsiama. Suplanuotas (plano keitimo) planas tiesiog niekada neprasideda.
- **Kodėl nekviečiam Paysera grąžinimo API:** grąžinimų – vienetai per mėnesį, savitarnoje tai minutės darbas; API
  reikalautų atskiros prieigos, naujos parašų logikos, klaidų apdorojimo (nepakankamas likutis, pakartojimai).
  Sistema fiksuoja buhalterinę pusę, o pinigų pervedimą primena varnelė patvirtinimo lange.

### Kaip išbandyti lokaliai

1. `php artisan migrate`, `npm run build`, `composer run dev`.
2. Prisijunkite `admin1@example.test` / `password` → `/admin/mokejimai` → atidarykite apmokėtą mokėjimą →
   „Grąžinti pinigus". Lange matysite, kiek kreditų bus atimta; įrašykite priežastį, pažymėkite varnelę.
3. Peržiūroje atsiras „Grąžinimas" sekcija ir „Kreditinė sąskaita PDF" mygtukas; sąraše po SF numeriu – KS numeris.
4. Prisijunkite to teikėjo vardu → „Mokėjimai": grąžintas mokėjimas, abi sąskaitos PDF; varpelyje – pranešimas.
   Laiškas – `storage/logs/laravel.log` (`MAIL_MAILER=log`), jei veikia eilės darbuotojas (`composer run dev`).

### Svarbiausi sprendimai ir alternatyvos

| Klausimas                         | Pasirinkta                                            | Alternatyva ir kodėl ne                                                                                     |
| --------------------------------- | ----------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| Kur saugoti kreditinę sąskaitą    | atskira `refunds` lentelė                             | stulpeliai `payments` lentelėje – tušti beveik visose eilutėse, dalinių grąžinimų nebūtų kur dėti           |
| Kreditinių sąskaitų numeracija    | `series` stulpelis `invoice_sequences` lentelėje      | atskira skaitiklių lentelė – ta pati logika dukart                                                          |
| Kas, jei kreditai jau išleisti    | atimti, kiek yra, skirtumą įrašyti ir parodyti        | neleisti grąžinti (grąžinimas gali būti privalomas), neigiamas balansas (sulaužytų ledger'į), dalinė suma   |
| Prenumeratos mokėjimas            | sutrumpinti paskutiniu apmokėtu laikotarpiu           | `CancelSubscription` – paliktų galioti laikotarpį, už kurį pinigai jau grąžinti                             |
| Laikotarpio pradžia               | eiti nuo `starts_at` su `BillingPeriod::addTo()`      | `ends_at − 1 mėn.` – sausio 31 + 1 mėn. = vasario 28, bet vasario 28 − 1 mėn. = sausio 28 (ribos nesutampa) |
| Peržiūra ir vykdymas              | vienas `RefundCalculator` abiem                       | skaičiuoti modale atskirai – du kodo variantai, kurie anksčiau ar vėliau išsiskirtų                         |
| Kreditinės sąskaitos PDF šablonas | tas pats šablonas su `$credit_note` bloku             | atskiras šablonas – rekvizitų, lentelės ir stiliaus kopija                                                  |
| Tekstai                           | naujas `lang/lt/refunds.php`                          | `billing.php` – lygiagrečiai jį keičia kitas Etapo 9 darbas, būtų sujungimo konfliktų                       |
| Suma žodžiais                     | eurai žodžiais, centai skaitmenimis (`… eurai 20 ct`) | ir centai žodžiais („… ir dvidešimt centų") – ilgiau, o apskaitos programose dažniau naudojamas „ct"        |

### Išmoktos sąvokos

#### 1. Kreditinė sąskaita faktūra

Išrašytos sąskaitos faktūros keisti ar trinti negalima. Jei pinigai grąžinami, išrašomas naujas dokumentas –
**kreditinė sąskaita faktūra** (PVM mokėtojui – kreditinė PVM sąskaita faktūra): savo serija ir numeriu, nuoroda į
koreguojamą sąskaitą, priežastimi ir **neigiamomis** sumomis. Sąskaita + kreditinė sąskaita kartu duoda 0.
Todėl mūsų PDF naudoja tą patį PVM skaičiavimą (`InvoicePdf::amounts()`) – suma be PVM ir PVM sutampa iki cento.
WordPress analogas – WooCommerce „Refund" užsakyme, o PDF įskiepiai iš jo daro „Credit note".

#### 2. Sudėtinis pirminis raktas ir migracija, kuri jį keičia

```php
Schema::table('invoice_sequences', function (Blueprint $table) {
    $table->string('series', 20)->default('invoice');   // esamoms eilutėms – „invoice"
});
Schema::table('invoice_sequences', function (Blueprint $table) {
    $table->dropPrimary();
    $table->unsignedSmallInteger('year')->change();      // SQLite: nebe „rowid"
    $table->primary(['series', 'year']);
});
```

MySQL tai daro `ALTER TABLE … DROP PRIMARY KEY, ADD PRIMARY KEY`. SQLite pirminio rakto keisti nemoka, todėl Laravel
(nuo 11 versijos) **perkuria lentelę**: sukuria laikiną, perkopijuoja duomenis, seną ištrina, naują pervadina.
Spąstai: SQLite vienintelį `INTEGER PRIMARY KEY` stulpelį laiko `rowid` (automatiškai didėjančiu), Laravel perkurdamas
tą požymį išsaugo ir sudėtinį raktą **tyliai praleidžia**. `->change()` aprašo stulpelį iš naujo, be autoincrement.
Patikrinta abiem kryptimis (`migrate:rollback` ir `migrate`) su duomenimis – SQLite ir MySQL.
→ https://laravel.com/docs/13.x/migrations#modifying-columns · https://laravel.com/docs/13.x/migrations#dropping-indexes

#### 3. Idempotencija ir pesimistinis užraktas – dar kartą

`RefundPayment` saugo nuo dvigubo grąžinimo trimis sluoksniais, kaip Etapo 7 callback'as:

```php
DB::transaction(function () use ($payment) {
    $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

    if (! $locked->status->canTransitionTo(PaymentStatus::Refunded)) {   // tikrinam UŽRAKINUS, ne prieš
        throw InvalidStateTransitionException::for($locked->status, PaymentStatus::Refunded, 'Mokėjimo');
    }
    // … kreditai, prenumerata, KS numeris, refunds eilutė
});
```

1. užrakinta eilutė – du administratoriai vienu metu vykdo po vieną; 2. būsena tikrinama užrakinus (pasenęs modelis
   iš atidaryto puslapio nieko nereiškia); 3. `UNIQUE(refunds.payment_id)`. Užraktų tvarka ta pati kaip `CompletePayment`:
   mokėjimas → teikėjas → prenumerata → numerių skaitiklis (kitaip MySQL'e – deadlock).
   → https://laravel.com/docs/13.x/queries#pessimistic-locking · https://laravel.com/docs/13.x/database#database-transactions

#### 4. „Sprendimas" atskirai nuo „vykdymo" (DTO)

`RefundCalculator` nieko nerašo į DB – tik apskaičiuoja ir grąžina `RefundCalculation` (readonly DTO: kiek kreditų,
nauja prenumeratos būsena ir pabaiga). Jį naudoja ir Filament langas (peržiūra), ir `RefundPayment` (vykdymas).
Tokią „gryną" klasę paprasta testuoti, o dviem vietoms nereikia dviejų kodo variantų.
`final readonly class` (PHP 8.2+) – visos savybės nekeičiamos po sukūrimo.
→ https://www.php.net/manual/en/language.oop5.properties.php#language.oop5.properties.readonly-properties

#### 5. Būsenų mašina mokėjimui

`PaymentStatus::allowedTransitions()` – kaip `SubscriptionStatus` ir `OfferStatus`: `paid → refunded`, `refunded` –
galutinė. Action prieš keisdama būseną klausia `canTransitionTo()`. Lentelė – `DB_SCHEMA.md` → payments.

#### 6. Ledger'is: atimti, bet ne žemiau nulio

Klaidos ar grąžinimai ledger'yje taisomi **nauja priešinga eilute** (`-12`, `type = payment_refund`,
`source = payment`), sena nekeičiama. `CreditLedger::debit()` meta `InsufficientCreditsException`, jei balansas taptų
neigiamas, todėl prieš tai apskaičiuojam `min(suteikta, balansas)`, o likutį įrašom kaip `credits_shortfall`.
`CreditTransactionObserver` dėl šios eilutės `LowCredits` nebesiunčia – teikėjas jau gauna `PaymentRefunded`.

#### 7. Filament veiksmas su forma, peržiūra ir patvirtinimu

```php
Action::make('refund')
    ->visible(fn (Payment $record) => $record->isPaid())
    ->authorize('refund')                                   // PaymentPolicy::refund
    ->requiresConfirmation()
    ->modalDescription(fn (Payment $record) => self::preview($record))   // skaičiuojama atidarant langą
    ->schema([
        Textarea::make('reason')->required()->maxLength(500),
        Checkbox::make('money_returned')->accepted(),       // „pinigus grąžinsiu savitarnoje"
    ])
    ->action(fn (Payment $record, array $data) => …);
```

`visible()` slepia mygtuką, `authorize()` dar ir neleidžia jo įvykdyti (Filament patikrina abu ir vykdydamas).
Livewire kiekvienai užklausai įrašą perskaito iš DB, todėl jei kitas administratorius ką tik grąžino tą patį
mokėjimą, veiksmas tiesiog nebeįvykdomas. Infolist sekcija `->visible(fn ($record) => $record->refund !== null)`.
→ https://filamentphp.com/docs/5.x/actions/modals · https://filamentphp.com/docs/5.x/actions/overview

#### 8. Filament testai be pest-plugin-livewire

```php
Livewire::test(ViewPayment::class, ['record' => $payment->getRouteKey()])
    ->mountAction('refund')
    ->assertMountedActionModalSee('bus atimta tik 12, 18 atimti nepavyks');   // peržiūros tekstas

Livewire::test(ViewPayment::class, ['record' => $payment->getRouteKey()])
    ->callAction('refund', ['reason' => '', 'money_returned' => false])
    ->assertHasActionErrors(['reason' => 'required', 'money_returned' => 'accepted']);

Livewire::test(ListPayments::class)
    ->callAction(TestAction::make('refund')->table($payment), [...]);        // lentelės eilutės veiksmas
```

Lygiagretumo imitacija: `mountAction()` → „kitas administratorius" grąžina tiesiogiai per Action →
`setActionData()->callMountedAction()` → grąžinimas vis tiek vienas.
→ https://filamentphp.com/docs/5.x/testing/testing-actions

#### 9. Skaičiai žodžiais ir Pest dataset'ai

Lietuvių kalboje daiktavardžio forma priklauso nuo paskutinių skaitmenų: 1, 21, 101 → „euras"; 2–9 → „eurai";
0, 10–20, 30, 111 → „eurų". 11–19 visada su kilmininku („vienuolika tūkstančių"), o „šimtas", „tūkstantis",
„milijonas" – be „vienas". Skaičius skaidomas į triženkles grupes iš dešinės; kiekvienai grupei – žodžiai ir laipsnio
forma. Testas su ~90 atvejų parašytas dataset'ais – vienas testas, daug eilučių su pavadinimais.
→ https://pestphp.com/docs/datasets

#### 10. API Resource ir neužkrauti ryšiai

`PaymentResource` grąžinimą įdeda tik tada, kai controller'is jį užkrovė: `$payment->relationLoaded('refund')`.
Taip „Kreditų" puslapis (kur grąžinimas nereikalingas) nedaro papildomos užklausos, o `preventLazyLoading` nesuveikia.
Laravel turi tam ir pagalbinį `$this->whenLoaded('refund')`. Sąraše – `->with(['purchasable', 'refund'])` (be N+1).
`can.download_credit_note` Policy klausiama tik grąžintiems mokėjimams.
→ https://laravel.com/docs/13.x/eloquent-resources#conditional-relationships

#### 11. PDF tikrinimas be naršyklės

Testai tikrina `creditNoteData()` (masyvą) ir `view(...)->render()` (HTML tekstą), o HTTP testas – antraštes ir
`%PDF` pradžią. Kaip PDF atrodo iš tikrųjų – `pdftotext -layout failas.pdf -` (tekstas) ir
`pdftoppm -png -r 80 failas.pdf puslapis` (paveikslėlis) iš `poppler-utils`.

### Naudingos komandos

| Komanda                                                          | Ką daro                                                |
| ---------------------------------------------------------------- | ------------------------------------------------------ |
| `php artisan migrate:rollback --step=2` ir `php artisan migrate` | patikrina, ar naujos migracijos veikia abiem kryptimis |
| `php artisan test tests/Feature/Billing`                         | visi mokėjimų, sąskaitų ir grąžinimų testai            |
| `php artisan test tests/Unit/AmountInWordsTest.php`              | sumos žodžiais atvejai                                 |
| `php artisan tinker` → `App\Support\AmountInWords::eur(12345)`   | greitai pažiūrėti, kaip užrašoma suma                  |
| `php artisan queue:work --stop-when-empty`                       | apdoroja eilėje laukiančius pranešimus ir sustoja      |
| `pdftotext -layout ks.pdf -` / `pdftoppm -png ks.pdf ks`         | PDF tekstas / paveikslėlis peržiūrai                   |
| `DB_CONNECTION=mysql DB_DATABASE=… DB_URL= php artisan test`     | testai prieš MySQL (CLAUDE.md 11 sk.)                  |

### Dažnos klaidos

- **Būsena tikrinama prieš užraktą** – du lygiagretūs paspaudimai abu mato „apmokėta" ir grąžina dukart. Tikrinti
  tik užrakinus eilutę.
- **Grąžinimas be ledger'io** (`credits_balance -= 30`) – cache nebesutampa su `SUM(amount)`, o balansas gali tapti
  neigiamas. Tik per `CreditLedger`.
- **`ucfirst('šimtas')`** – PHP `ucfirst` dirba su baitais, todėl lietuviškos raidės nepakeičia (ar sugadina).
  Naudok `Str::ucfirst()` (daugiabaitis).
- **SQLite ir `INTEGER PRIMARY KEY`** – pirminio rakto keitimas be `->change()` tyliai nepritaikomas (žr. 2 sąvoką).
  Visada pažiūrėk sukurtą schemą (`sqlite_master`) ir patikrink su MySQL.
- **Grąžinto mokėjimo sąskaita dingsta** – `hasInvoice()` tikrino tik `paid`. Sąskaita faktūra po grąžinimo lieka.
- **„Pinigai nenuskaičiuoti" grąžintam mokėjimui** – sena „nepavyko" šaka apėmė ir `refunded`. Grąžinimas – atskira būsena.
- **Prenumeratos laikotarpis „atimant mėnesį"** – `ends_at->subMonth()` ne visada sutampa su laikotarpio riba. Eiti nuo
  `starts_at` tuo pačiu `addTo()`.
- **Seed'ų duomenys kitokie nei tikri** – seed'uose prenumeratos kreditai suteikti ir už būsimus laikotarpius, todėl
  „neprasidėjusio laikotarpio kreditų nėra" netiesa. Taisyklė turi remtis duomenimis (`credits_granted_until`), o ne prielaida.
- **`DB::transactionLevel()` apsaugos testas** – su `RefreshDatabase` kiekvienas testas jau vyksta transakcijoje,
  todėl „be transakcijos" išimties testu patikrinti neįmanoma.
- **`assertActionHidden()` po `mountAction()`** – kol veiksmas atidarytas, Filament vardą ieško kaip vidinio veiksmo
  (`ActionNotResolvableException`). Tikrinti naujame `Livewire::test()`.
- **Markdown lentelė ir formatuotojas** – pailginus vieną lentelės eilutę, `vp fmt` perlygiuoja visą lentelę, o
  lygiagrečiai dirbant tai garantuotas konfliktas. Papildymus rašėm pastaba po lentele.
