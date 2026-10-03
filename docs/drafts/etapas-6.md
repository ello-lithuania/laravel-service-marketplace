# Etapas 6 – Žinutės, atsiliepimai, skundai

> **Juodraštis `docs/LEARNING.md` skilčiai.** Sujungiant šakas jį reikia perkelti į `LEARNING.md` ir pažymėti
> `ROADMAP.md` punktus.

## ROADMAP būsena

| Punktas                                                                           | Būsena                                                                                                  |
| --------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| Pokalbiai ir žinutės, neperskaitytų skaičius (`last_read_message_id`), priedai    | atlikta                                                                                                 |
| Atnaujinimas realiu laiku: pradžioje polling, paskui sprendimas dėl Reverb        | atlikta: Inertia `usePoll`; sprendimas dėl Reverb + Echo – 12 sąvoka                                    |
| Atsiliepimai po darbo, pakvietimo nuoroda buvusiems klientams, teikėjo atsakymas  | atlikta                                                                                                 |
| Reitingo perskaičiavimas (observer → job)                                         | atlikta                                                                                                 |
| Skundo mygtukas (užklausa, pasiūlymas, atsiliepimas, žinutė, profilis) + Filament | atlikta                                                                                                 |
| Rate limiting: žinutės, skundai, užklausos                                        | atlikta (+ atsiliepimai)                                                                                |
| Testai                                                                            | atlikta: 115 naujų (iš viso 546)                                                                        |
| `docs/LEARNING.md`: Etapas 6 + santrauka vartotojui                               | šis juodraštis                                                                                          |
| _Iš Etapo 5:_ užklausos kūrimo forma – **nuotraukos**                             | atlikta: paskutiniame formos žingsnyje ir užklausos puslapyje (kol `pending` / `open`)                  |
| _Iš Etapo 5:_ `STATES.md` papildomos taisyklės                                    | atlikta: teikėjo prašymas pažymėti darbą atliktu (kas 3 d.) ir kasdienis 60 d. priminimas (vieną kartą) |

**Sąmoningai nepadaryta (ir kodėl):**

- **Laravel Reverb + Echo** – užduotyje: tik sprendimas, be naujų paketų (12 sąvoka).
- **Skundo įrodymai (`complaints` kolekcija `evidence`)** – skundui pakanka priežasties ir aprašymo; prireikus –
  ta pati `AttachmentValidationRules` + privatus diskas.
- **Vartotojo (ne jo turinio) skundimas** – skundžiamas turinys (užklausa, žinutė, profilis); vartotojų blokavimas –
  Etapas 8 („vartotojų blokavimas").
- **Ilgų pokalbių puslapiavimas** – rodomos paskutinės 100 žinučių (`ConversationController::MESSAGES_LIMIT`);
  pokalbiai trumpi (seed'uose 1–5 žinutės). Prireikus – Inertia `InfiniteScroll` + `Inertia::scroll()`.
- **Atsiliepimo redagavimas** – autorius jo keisti negali (paprasčiau moderuoti); klaidą taiso administratorius.
- **Demo nuotraukos ir priedai seed'e** – kaip ir Etape 3, seed'as failų nekuria (lėta, užima vietą).

---

### Ką darėm ir kodėl

- **Pokalbiai** (`/zinutes`, `/zinutes/{id}`): pokalbis priklauso pasiūlymui (`UNIQUE(offer_id)`). Klientas gali
  pradėti pokalbį dėl bet kurio savo užklausos pasiūlymo, teikėjas – tik kai jo pasiūlymas priimtas (kitaip jis
  galėtų „užversti" klientą žinutėmis; jo prisistatymas – pasiūlymas). Rašyti galima, kol pasiūlymas laukia atviroje
  užklausoje, o priimtam – visada (garantija, papildomi darbai). Administratorius pokalbį gali skaityti (skundams).
  Taisyklės – `ConversationPolicy`, mygtukas „Rašyti žinutę" – `App\Support\OfferMessaging` + `MessageButton.vue`
  (pasiūlymo, kliento užklausos ir teikėjo užklausos puslapiuose).
- **Neperskaitytos** – per `conversation_user.last_read_message_id`: sąraše kiekvienam pokalbiui (`withCount`),
  meniu – bendras Inertia prop'as `inbox.unread_count` (closure, vienas `COUNT` su `JOIN`). Atidarius pokalbį (ir
  kiekvieno automatinio atnaujinimo metu) jis pažymimas perskaitytu, o jo `NewMessage` pranešimai varpelyje –
  perskaitytais.
- **Priedai** – nuotraukos (Etapo 3 taisyklės) ir PDF iki 10 MB, iki 5 žinutėje. **Privačiame diske**: failą atiduoda
  `PrivateMediaController` (`/failai/{media}`), patikrinęs savininko Policy (`view`). Tas pats controller'is atiduoda
  ir užklausų nuotraukas.
- **`NewMessage` be šlamšto**: pranešama tik apie pirmą neperskaitytą žinutę; varpelis – iš karto, laiškas – po 5 min.
  ir tik jei žinutė vis dar neperskaityta (`withDelay()` + `shouldSend()`).
- **Atnaujinimas** – Inertia polling: pokalbis kas 10 s (`only: ['messages', 'can', 'inbox']`), sąrašas kas 30 s.
- **Atsiliepimai.** Užbaigus darbą klientas gauna `ReviewInvitation`, o užklausos puslapyje ir „Mano paskyra" – formą
  / priminimą. Patvirtintas atsiliepimas: tik užklausos klientas, vieną kartą, per 60 d., paskelbiamas iš karto, teikėjui
  – `NewReview`. **Pakvietimo nuoroda** (`/paskyra/atsiliepimai`): pasirašytas URL 30 d.; atsiliepimą gali palikti tik
  klientas, ne savo profiliui, vieną tam pačiam teikėjui per 12 mėn.; jis laukia administratoriaus (`pending`), o UI
  pažymimas „Pagal pakvietimą". Teikėjas į atsiliepimą atsako vieną kartą (`ReviewReplied` autoriui).
- **Reitingas** – `ReviewObserver` → eilės job'as `RecalculateProviderRating` (ta pati formulė kaip seed'ų `CounterSync`).
- **Skundai** – „Pranešti" (`ReportDialog.vue`) prie teikėjo užklausos, pasiūlymo, žinutės, atsiliepimo ir profilio;
  `reportable` – polimorfinis ryšys. Vienas neužbaigtas skundas tam pačiam įrašui iš to paties žmogaus.
  Filament `/admin/skundai`: eilė (nauji, nagrinėjami – seniausi viršuje), „Imti nagrinėti", „Išspręsti" (galima iš
  karto paslėpti atsiliepimą ar žinutę), „Atmesti"; pranešėjui – `ComplaintResolved`.
- **Atsiliepimų moderavimas** – Filament `/admin/atsiliepimai`: „Laukia moderavimo", „Su skundais", filtrai, paskelbimas /
  paslėpimas (ir masinis).
- **Dažnio ribos** – pavadinti limiter'iai `messages`, `complaints`, `service-requests` (be Precognition užklausų),
  `reviews`; lietuviškas atsakymas (`App\Support\TooManyAttempts`).
- **Užklausos nuotraukos** (iš Etapo 5) – iki 8, formos paskutiniame žingsnyje ir užklausos puslapyje; mato klientas ir
  tinkami teikėjai; teikėjo sraute – nuotraukų skaičius.
- **Darbo užbaigimo priminimai** (iš Etapo 5) – teikėjo mygtukas „Paprašyti pažymėti atliktu" (kas 3 d.) ir kasdienė
  komanda `service-requests:remind-completion` (60+ d., vieną kartą). Nauja migracija
  `add_completion_reminders_to_service_requests_table`.
- **Dokumentai**: `DB_SCHEMA.md` (naujų stulpelių, `media` kolekcijų, taisyklių aprašymai), `STATES.md` 1 ir 4 sk.

**Kaip išbandyti:** `php artisan migrate:fresh --seed`, `composer run dev`. Klientas `klientas1@example.test`, teikėjas
`teikejas1@example.test`, administratorius `admin1@example.test` (slaptažodis `password`). Žinutės – meniu „Žinutės";
pakvietimo nuoroda – teikėjo „Atsiliepimai"; skundai ir atsiliepimai – `/admin`. Laiškai – `storage/logs/laravel.log`
(`NewMessage` laiškas ateis po 5 min., todėl eilės darbuotojas turi veikti).

### Išmoktos sąvokos

#### 1. `belongsToMany` su pivot laukais

`conversation_user` – ne tik „kas su kuo susijęs", bet ir **papildomas laukas** `last_read_message_id`. Ryšyje jį reikia
paminėti, kitaip Eloquent jo neskaito:

```php
public function participants(): BelongsToMany
{
    return $this->belongsToMany(User::class)->withPivot('last_read_message_id');
}

$conversation->participants()->syncWithoutDetaching([$clientId, $providerUserId]); // idempotentiškai įrašo dalyvius
$conversation->participants()->updateExistingPivot($user->id, ['last_read_message_id' => $message->id]);
$participant->pivot->last_read_message_id;                                           // reikšmė perskaitant
```

Kadangi `$user->conversations()` užklausa jau prijungia `conversation_user`, koreliuotoje subužklausoje galima naudoti
pivot stulpelį: `->withCount(['messages as unread_count' => fn ($q) => $q->whereRaw('messages.id > COALESCE(conversation_user.last_read_message_id, 0)')])`.
WordPress analogas – `wp_term_relationships` su papildomu `term_order` stulpeliu.
→ https://laravel.com/docs/13.x/eloquent-relationships#retrieving-intermediate-table-columns ·
https://laravel.com/docs/13.x/eloquent-relationships#updating-a-record-on-the-intermediate-table

#### 2. `createOrFirst()` ir „vienas iš daugelio" ryšys

- `Conversation::createOrFirst(['offer_id' => …])` pirma bando `INSERT`, o jei `UNIQUE` jau užimtas (dvigubas paspaudimas,
  abu dalyviai vienu metu) – paima esamą. `firstOrCreate()` daro atvirkščiai (`SELECT`, tada `INSERT`), ir tarp jų
  lygiagreti užklausa gali spėti įterpti. → https://laravel.com/docs/13.x/eloquent#retrieving-or-creating-models
- `hasOne(Message::class)->latestOfMany()` – paskutinė žinutė kiekvienam pokalbiui viena užklausa visam sąrašui.
  → https://laravel.com/docs/13.x/eloquent-relationships#has-one-of-many

#### 3. Pranešimai be šlamšto: `withDelay()`, `shouldSend()`

```php
public function withDelay(object $notifiable, string $channel): ?DateTimeInterface
{
    return $channel === 'mail' ? now()->addMinutes(5) : null;   // varpelis – iš karto
}

public function shouldSend(object $notifiable, string $channel): bool
{
    // kviečiama eilės job'e, jau po uždelsimo: perskaitė svetainėje – laiško nebereikia
}
```

Plius taisyklė `SendMessage` veiksme: pranešti tik tiems, kurie buvo perskaitę viską iki šios žinutės. `deleteWhenMissingModels`
– jei kol job'as laukė, žinutė buvo paslėpta (soft delete), job'as tyliai išmetamas, o ne kartojamas.
→ https://laravel.com/docs/13.x/notifications#delaying-notifications ·
https://laravel.com/docs/13.x/notifications#determining-if-the-queued-notification-should-be-sent

#### 4. Privatūs failai

`public` diske failas pasiekiamas kiekvienam, kas žino URL (`/storage/{media_id}/{failas}`, o `media_id` didėja iš eilės).
Todėl žinučių priedai ir užklausų nuotraukos – `local` diske (`storage/app/private`), o atiduoda controller'is:

```php
Gate::authorize('view', $media->model);   // Message → MessagePolicy, ServiceRequest → ServiceRequestPolicy
return Storage::disk($media->disk)->response($media->getPathRelativeToRoot($conversion), $media->file_name, [...], 'inline');
```

Medialibrary kolekcijoje – `->useDisk('local')`. Antraštė `X-Content-Type-Options: nosniff` neleidžia naršyklei „spėti"
tipo, PDF atiduodamas atsisiuntimui. Failo tipą (nuotrauka ar PDF) Form Request nustato pagal **turinį**
(`getMimeType()`), ne plėtinį. WordPress analogas – failų apsauga per PHP „proxy" vietoj nuorodos į `wp-content/uploads`.
→ https://laravel.com/docs/13.x/filesystem#downloading-files · https://spatie.be/docs/laravel-medialibrary

#### 5. Pasirašyti URL (signed URLs)

```php
URL::temporarySignedRoute('reviews.invitation.show', now()->addDays(30), ['providerProfile' => $profile]);
// /atsiliepimas/jonas-1?expires=1793646943&signature=96237a…

Route::get('atsiliepimas/{providerProfile:slug}', …)->middleware('signed');
```

Parašas – HMAC (su `APP_KEY`) nuo viso URL, įskaitant `expires`. Pakeitus slug'ą, datą ar parašą – 403
(`lang/lt.json`: „Nuoroda neteisinga arba jos galiojimas baigėsi."). Neprisijungusį `auth` nukreipia prisijungti ir po to
grąžina į tą pačią nuorodą su parašu (`redirect()->intended()`). Forma siunčiama į tą patį URL – `signed` tikrina ir POST.
Pasirašytas URL ≠ slaptas: kas jį turi, tas gali naudoti, todėl papildomai – Policy (tik klientai, vienas per 12 mėn.) ir
moderavimas. WordPress analogas – slaptažodžio atkūrimo `key` arba `wp_nonce_url()`.
→ https://laravel.com/docs/13.x/urls#signed-urls

#### 6. Observers

```php
#[ObservedBy(ReviewObserver::class)]
class Review extends Model { … }

class ReviewObserver implements ShouldHandleEventsAfterCommit
{
    public function updated(Review $review): void
    {
        if ($review->wasChanged(['status', 'rating', 'provider_profile_id'])) {
            RecalculateProviderRating::dispatch($review->provider_profile_id);
        }
    }
}
```

Observer reaguoja, kad ir kur modelis būtų išsaugotas: Action, Filament veiksmas, tinker. `ShouldHandleEventsAfterCommit` –
tik po sėkmingos transakcijos. **Masinis** `Review::query()->update([...])` įvykių nekelia – todėl Filament masinis
veiksmas keičia kiekvieną įrašą per modelį. WordPress analogas – `add_action('save_post', …)`.
→ https://laravel.com/docs/13.x/eloquent#observers

#### 7. Eilės: unikalūs job'ai

`RecalculateProviderRating implements ShouldBeUniqueUntilProcessing` su `uniqueId()` = teikėjo ID: kol to paties teikėjo
job'as laukia eilėje, naujas neįdedamas (jis vis tiek perskaitys naujausius duomenis). Kodėl ne `ShouldBeUnique`: jis
laiko užraktą iki job'o **pabaigos**, todėl pakeitimas, įvykęs skaičiavimo metu, būtų prarastas. Laravel 13 dar turi
`#[DebounceFor(10)]` – job'as atidedamas ir paleidžiamas tik paskutinis per laikotarpį; tiktų, jei perskaičiavimas būtų
brangus, bet reitingas tada atsinaujintų pavėluotai. Job'ui perduodam ID, o ne modelį – profilis gali būti „ištrintas".
→ https://laravel.com/docs/13.x/queues#unique-jobs

#### 8. Polimorfiniai ryšiai praktikoje

`complaints.reportable_type` + `reportable_id` – skundas gali būti dėl penkių skirtingų modelių. Morph map'as (Etapas 2)
saugo trumpus vardus, o enum `ReportableType` jais naudojasi validacijai ir modelio paieškai.

- `$complaint->reportable()->associate($review)` – įrašo abu stulpelius;
- `Complaint::query()->whereMorphedTo('reportable', $review)` – „skundai dėl šio įrašo";
- eager loading su sąlygomis kiekvienam tipui: `MorphTo::constrain([Message::class => fn ($q) => $q->withTrashed()])` –
  administratorius mato ir jau paslėptą žinutę.

WordPress analogas – `wp_comments.comment_post_ID`, kai „post" gali būti bet kokio tipo.
→ https://laravel.com/docs/13.x/eloquent-relationships#polymorphic-relationships ·
https://laravel.com/docs/13.x/eloquent-relationships#custom-polymorphic-types

#### 9. Rate limiting

```php
RateLimiter::for('messages', fn (Request $request) => [
    Limit::perMinute(15)->by('messages:60:'.$request->user()->id)->response(…),
    Limit::perDay(500)->by('messages:86400:'.$request->user()->id)->response(…),
]);

Route::post('zinutes/{conversation}', …)->middleware('throttle:messages');
```

- Kelios ribos vienu metu – kiekviena su **savo raktu** (`by`), kitaip jos dalytųsi skaitliuku.
- `Limit::none()` – neribojama: užklausos formos **Precognition** žingsnių tikrinimas eina į tą patį `POST /uzklausos`,
  todėl limiter'is tikrina `$request->isPrecognitive()` (veikia, nes `HandlePrecognitiveRequests` middleware prioritetų
  sąraše eina prieš `ThrottleRequests`).
- `->response()` – savas atsakymas: JSON – 429 su lietuvišku tekstu, Inertia – atgal su klaida ir toast (429 Inertia'i
  atrodytų kaip klaidos langas), kita – 429 puslapis.
- Skaitliukai – cache'e (dev – DB, prod – Redis). WordPress analogas – „Limit Login Attempts" įskiepis su transient'ais.

Kodėl teikėjo prašymui „pažymėti atliktu" naudojam **ne** `RateLimiter`, o stulpelį `completion_requested_at`: tai verslo
taisyklė, kuri turi išlikti išvalius cache, o puslapis rodo „Paskutinį kartą prašėte prieš 2 d.".
→ https://laravel.com/docs/13.x/routing#rate-limiting · https://laravel.com/docs/13.x/rate-limiting

#### 10. Form Request: `after()` ir taisyklės kiekvienam failui

- `after()` – papildoma patikra po pagrindinių taisyklių (pvz. „ar skundžiamas įrašas dar egzistuoja").
  → https://laravel.com/docs/13.x/validation#performing-additional-validation-on-form-requests
- Taisyklės gali būti skaičiuojamos: `foreach ($this->file('attachments') as $i => $file) $rules["attachments.$i"] = …` –
  nuotraukai vienos, PDF – kitos (`dimensions` PDF'ui visada nepavyktų).
- `authorize()` grąžina `Gate::inspect(...)` – vartotojas mato priežastį.

#### 11. Polling su Inertia

```ts
usePoll(10_000, { only: ['messages', 'can', 'inbox'] });
```

Kas 10 s – dalinis perkrovimas: serveris vykdo tą patį controller'į, bet skaičiuoja tik prašytus props (todėl jie –
closure'ai). Fone (kitas skirtukas) Inertia užklausas retina pati. Paprasta, nereikia papildomų serverių, veikia per
įprastą HTTP. Trūkumas – vėlavimas iki 10 s ir užklausos net tada, kai nieko naujo. → https://inertiajs.com/polling

#### 12. Sprendimas: kada Laravel Reverb + Echo

**Dabar – polling.** 1 000 vienu metu atidarytų pokalbių = ~100 mažų užklausų per sekundę (dalinis perkrovimas, vienas
`SELECT` žinutėms) – pirmai versijai to užtenka, o diegimas paprastas.

**Pereiti į Reverb, kai:** vienu metu pokalbiuose – šimtai ar tūkstančiai žmonių ir polling apkrova tampa pastebima;
reikia „rašo…" indikatoriaus, „perskaityta" varnelių ar momentinio varpelio; norim mažinti vėlavimą iki sekundės dalies.

**Kas pasikeistų:**

1. `composer require laravel/reverb` ir `php artisan install:broadcasting` (įdiegia `laravel-echo`, `pusher-js`,
   `@laravel/echo-vue`), `.env`: `BROADCAST_CONNECTION=reverb`, `REVERB_*`.
2. Serveryje – nuolat veikiantis `php artisan reverb:start` procesas (supervisor), nginx – WebSocket proxy, prod – Redis
   (keli serveriai dalijasi įvykiais).
3. Įvykis `MessageSent implements ShouldBroadcast` su `PrivateChannel('conversations.'.$id)`; `SendMessage` jį paskelbia
   po transakcijos. Antras kanalas – vartotojo `App.Models.User.{id}` meniu ženkleliui.
4. `routes/channels.php` – kanalo autorizacija (ta pati taisyklė kaip `ConversationPolicy::view`).
5. Vue: `useEcho('conversations.'+id, 'MessageSent', () => router.reload({ only: ['messages'] }))`; polling lieka kaip
   atsarginis variantas su retesniu intervalu (pvz. 60 s), jei WebSocket ryšys nutrūksta.

Alternatyvos: Pusher / Ably (mokamos paslaugos, nereikia savo serverio), Soketi (atviro kodo Pusher protokolas).
Reverb – oficialus Laravel, nemokamas, bet tai dar vienas procesas, kurį reikia diegti ir stebėti (Etapas 8).
→ https://laravel.com/docs/13.x/broadcasting · https://laravel.com/docs/13.x/reverb

#### 13. Filament: masiniai veiksmai, filtrai, rikiavimas

- `BulkAction::make('hideSelected')->action(fn (Collection $records) => …)` – veiksmas pažymėtiems įrašams.
- `TernaryFilter` – trijų būsenų filtras (visi / patvirtinti / pagal pakvietimą) su `->queries(true: …, false: …)`.
- `->defaultSort(fn (Builder $query) => $query->orderByRaw('CASE status …')->orderBy('created_at'))` – eilė.
- Veiksmų formos (`->schema([Textarea::make('note')->required(), Toggle::make('hide_content')])`), `->visible()`,
  `->authorize('handle')` – teisės iš Policy.
- Testai: `Livewire::test(ListComplaints::class)->callAction(TestAction::make('reject')->table($complaint), [...])`,
  `->selectTableRecords([...])->callAction(TestAction::make('hideSelected')->table()->bulk())`, `->assertActionHidden()`.
  → https://filamentphp.com/docs/5.x/actions/overview · https://filamentphp.com/docs/5.x/tables/filters/ternary

#### 14. Scheduler su laiko juosta

`Schedule::command('service-requests:remind-completion')->dailyAt('09:00')->timezone('Europe/Vilnius')` – DB ir serveris
dirba UTC, o laiškas turi ateiti 9 val. Lietuvos laiku (ir vasarą, ir žiemą). → https://laravel.com/docs/13.x/scheduling#timezones

#### 15. Testai

- `Storage::fake('local')` + `UploadedFile::fake()->image('a.jpg', 800, 600)`; PDF – `createWithContent('a.pdf', "%PDF-1.4 …")`
  (medialibrary tipą tikrina pagal turinį, tuščias netikras failas būtų „application/x-empty").
- `Notification::assertSentToTimes($user, NewMessage::class, 1)`; jei `via()` grąžina `[]`, pranešimas visai
  nesiunčiamas – tada `assertNotSentTo`.
- `Queue::fake()` + unikalūs job'ai: antras `dispatch()` su tuo pačiu `uniqueId()` į eilę nepatenka.
- Pasirašyti URL: `URL::temporarySignedRoute(...)`, suklastotas slug'as ir `$this->travel(2)->days()` – 403.
- Rate limiting: 15 užklausų, 16-a – klaida; `$this->travel(61)->seconds()` – vėl galima; Precognition – `postJson(...,
['Precognition' => 'true', 'Precognition-Validate-Only' => 'title'])` niekada neribojama.
- N+1: `DB::enableQueryLog()` – 1 ir 6 nuotraukų užklausų puslapis daro tiek pat SQL užklausų.

### Naudingos komandos

| Komanda                                                   | Ką daro                                                             |
| --------------------------------------------------------- | ------------------------------------------------------------------- |
| `php artisan route:list --path=zinutes`                   | pokalbių maršrutai (taip pat `--path=atsiliepim`, `--path=skundai`) |
| `php artisan make:observer ReviewObserver --model=Review` | naujas observer'is                                                  |
| `php artisan make:job RecalculateProviderRating`          | naujas job'as                                                       |
| `php artisan make:filament-resource Complaint --view`     | Filament resource su peržiūros puslapiu                             |
| `php artisan service-requests:remind-completion`          | rankiniu būdu išsiunčia 60 d. priminimus                            |
| `php artisan schedule:list`                               | suplanuotos užduotys (matysis ir 9:00 priminimas)                   |
| `php artisan queue:work`                                  | vykdo eilę: pranešimus, uždelstus laiškus, miniatiūras, reitingą    |
| `php artisan cache:clear`                                 | išvalo ir rate limiting skaitliukus (dev'e)                         |
| `php artisan tinker` → `URL::temporarySignedRoute(…)`     | pasirašytos nuorodos generavimas bandymams                          |

### Dažnos klaidos

- **Masinis `update()` ir observer'iai.** `Review::query()->whereIn(...)->update(['status' => 'hidden'])` neiškviečia
  `updated()` – reitingas nepersiskaičiuos. Keisk per modelį arba po masinio pakeitimo paleisk job'ą pats.
- **`ShouldBeUnique` vietoj `ShouldBeUniqueUntilProcessing`** – pakeitimas, įvykęs job'o vykdymo metu, prarandamas.
- **Bendro Inertia prop'o ir puslapio prop'o vardų sutapimas.** Puslapio `messages` perrašytų bendrą `messages` – todėl
  meniu skaitliukas vadinasi `inbox`.
- **Rate limiting ir Precognition.** Ribojant visą `POST /uzklausos`, daugiažingsnė forma „užstrigtų" po kelių žingsnių.
- **Kelios ribos su tuo pačiu `by` raktu** dalijasi skaitliuku – minutės riba suvalgo paros ribą.
- **Privatūs failai `public` diske** – URL atspėjamas. Jautrius failus – į `local` ir per controller'į su Policy.
- **Pasirašytas URL ir kitas domenas.** Parašas apima ir host'ą: nuoroda, sugeneruota su `APP_URL=http://localhost:8000`,
  neveiks per `127.0.0.1:8106`. Tinker'yje – `URL::forceRootUrl(...)`; jei reikia nepriklausyti nuo domeno –
  `->middleware('signed:relative')` + `URL::signedRoute(..., absolute: false)`.
- **Pest pagalbinės funkcijos vardas** sutapo su Laravel helper'iu `report()` – „Cannot redeclare". Testų failuose
  funkcijoms duok konkrečius vardus.
- **`$this->travel(1)->days()->...`** – ne grandinė: `days()` grąžina ne `Wormhole`, todėl kiekvienam žingsniui – atskiras `travel()`.
- **Filament veiksmas, kurio `visible()` jau false**, teste tiesiog neįvykdomas – lenktynes (kitas administratorius
  užbaigė skundą) tikrink Action lygiu (`ComplaintAlreadyHandledException`).
- **`vp check --fix` formatuoja ir Markdown** – po `docs/*.md` pakeitimų paleisk jį prieš commit'ą, kitaip CI `npm run check`
  nepraeis.
- **Larastan ir ryšio closure'ai** `with(['x' => fn (MorphTo $m) => …])` – tipas turi būti `Relation`, o `MorphTo` tikrinti
  `instanceof` viduje.
