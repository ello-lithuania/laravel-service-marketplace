# ROADMAP

`[x]` – atlikta, `[ ]` – liko.
Kiekvienos sesijos pradžioje tęsiam nuo **pirmo nepažymėto etapo**. Etapas baigtas, kai pažymėti visi jo darbai
**ir vartotojas jį patvirtino**. „PADARYK PATS" užduotys kito etapo neblokuoja, bet jas rekomenduojama atlikti.

Kiekvieno etapo pabaigoje:
- `docs/LEARNING.md` papildomas sąvokomis, komandomis ir dažnomis klaidomis;
- duodamos 1–3 „PADARYK PATS" užduotys (su užuominomis, be atsakymų);
- užduodami 3–5 pasitikrinimo klausimai;
- daromas commit'as su šio failo pažymėjimais.

---

## Etapas 0 – Planavimas: DB schema, seed'ų planas, dokumentai

**Tikslas:** suprojektuoti duomenų bazę ir susitarti dėl taisyklių prieš rašant kodą.
**Sąvokos:** migracija, Eloquent ryšiai, indeksai, normalizacija ir denormalizacija, soft deletes, enum'ai,
ledger, idempotencija, seeder, factory, Faker.

- [ ] DB schema su paaiškinimais, ryšiais ir indeksais → `docs/DB_SCHEMA.md`
- [ ] Seed'ų planas (Faker lt_LT; 20k teikėjų, 100k užklausų, 300k pasiūlymų, 100k atsiliepimų, 200k žinučių) → `docs/SEEDING.md`
- [ ] `CLAUDE.md` – stack'as, konvencijos, struktūra, mokymosi režimas
- [ ] `ROADMAP.md` – etapai 0–8
- [ ] `docs/LEARNING.md` – Etapo 0 sąvokos, komandos, klaidos, klausimai
- [ ] **Vartotojas peržiūrėjo ir patvirtino schemą** (pageidaujami pakeitimai – atskiru commit'u)

**PADARYK PATS**
- [ ] **#1 `favorites` lentelė.** Suprojektuok lentelę, kurioje klientas išsisaugo patinkančius teikėjus. Aprašyk
  ją tuo pačiu formatu kaip `docs/DB_SCHEMA.md`: stulpeliai, pirminis raktas ar unikalumas, indeksai, ON DELETE.
  *Užuominos:* koks tai ryšio tipas – 1:N ar N:M? Kaip tokią lentelę pavadintų Laravel konvencija ir ar čia
  geriau tiktų prasminis vardas? Dažniausios bus dvi užklausos: „mano išsaugoti teikėjai" ir „ar jau išsaugojau
  šį teikėją?". Kurį indeksą naudos kiekviena? Ar reikia `created_at`?
- [ ] **#2 Teikėjo užklausų srautas.** Parašyk SQL arba Eloquent pseudo-kodą: teikėjui grąžinti `open` užklausas
  jo kategorijose ir zonose, naujausios viršuje, po 20 puslapyje. Nurodyk, kurį indeksą naudos užklausa.
  *Užuominos:* teikėjas galėjo pasirinkti 2 lygio kategoriją, o užklausos visada 3 lygio. Kaip rasti vaikus?
  (`DB_SCHEMA.md` 2.2 sk. aprašytas atvirkštinis atvejis.) Kaip įtraukti `serves_whole_country`?
  Kuo skiriasi `whereIn` ir `whereExists`?
- [ ] **#3 Būsenų diagrama.** Nupiešk užklausos (`ServiceRequestStatus`) ir pasiūlymo (`OfferStatus`) būsenų
  perėjimus: kas gali įvykti iš kiekvienos būsenos ir kas tai inicijuoja (klientas, teikėjas, sistema, admin).
  Galima popieriuje arba Mermaid `stateDiagram-v2` naujame faile `docs/STATES.md`.
  *Užuominos:* kas nutinka kitiems pasiūlymams, kai vienas priimamas? O laukiantiems pasiūlymams, kai užklausa
  pasibaigia ar atšaukiama? Ar galima atšaukti `in_progress` užklausą? Ar teikėjas atgauna kreditus, jei
  atšaukia savo pasiūlymą?
- [ ] Atsakyta į pasitikrinimo klausimus (`docs/LEARNING.md`)

---

## Etapas 1 – Projekto pagrindas

**Tikslas:** veikiantis tuščias Laravel 12 + Inertia + Vue projektas su lietuvių kalba, admin panele ir testais.
**Sąvokos:** projekto struktūra, `.env` ir `config/`, Composer ir NPM, Vite, Inertia puslapis, Artisan, maršrutai.

- [ ] Laravel 12 projektas iš oficialaus Vue starter kit (Inertia 2 + Vue 3 + Tailwind 4)
- [ ] Sprendimai užfiksuoti `CLAUDE.md`: TypeScript ar JS, maršrutai Vue pusėje, tikslios paketų versijos
- [ ] `.env.example`: SQLite dev'ui, MySQL pavyzdys, `APP_LOCALE=lt`, `APP_FAKER_LOCALE=lt_LT`
- [ ] Lietuviški Laravel tekstai (`lang/lt`: validation, auth, passwords, pagination), datos lietuviškai (Carbon `lt`)
- [ ] Filament admin panelė `/admin` (laikinai pasiekiama tik lokaliai)
- [ ] Pest, Pint, GitHub Actions CI (testai + Pint kiekvienam push'ui)
- [ ] Bazinis išdėstymas: header, footer, laikinas logotipas, pradžios puslapio „griaučiai"
- [ ] Platformos pavadinimas (`APP_NAME`) – pasirenka vartotojas
- [ ] `docs/LEARNING.md`: Etapas 1 · PADARYK PATS · klausimai

PADARYK PATS (planuojamos): statinis Inertia puslapis „Apie mus" su maršrutu; savo validacijos pranešimas `lang/lt`.

---

## Etapas 2 – Duomenų bazė: migracijos, modeliai, ryšiai, seed'ai

**Tikslas:** visa schema kode ir pilnas, nuoseklus testinių duomenų rinkinys.
**Sąvokos:** migracijos, Eloquent modeliai, ryšiai, cast'ai, enum'ai, accessor'iai, factories ir būsenos,
seeder'iai, morph map, masinis įterpimas.

- [ ] PHP enum'ai (`app/Enums`) pagal `DB_SCHEMA.md` 6 sk.
- [ ] Migracijos pagal `DB_SCHEMA.md` 7 sk. eiliškumą (įskaitant žiedinį FK)
- [ ] Modeliai: `$fillable`, `casts()`, ryšiai, accessor'iai (`public_name`), morph map, `preventLazyLoading`
- [ ] Factories su būsenomis (`->completed()`, `->company()`…)
- [ ] Žinyniniai duomenys `database/data`: apskritys, savivaldybės, kategorijų medis, kreditų paketai, planai
- [ ] Lietuviškų tekstų bankai seed'ams
- [ ] Dideli seeder'iai pagal `docs/SEEDING.md` (+ `config/seeding.php`, `SEED_SCALE`)
- [ ] Vientisumo testai (`SEEDING.md` 8 sk.)
- [ ] Pilnas seed'as MySQL – išmatuotas laikas įrašytas į `SEEDING.md`
- [ ] Filament: kategorijų ir savivaldybių CRUD (pirmas susipažinimas su Filament)
- [ ] `docs/LEARNING.md`: Etapas 2 · PADARYK PATS · klausimai

PADARYK PATS (planuojamos): `reviews` migracija; ryšys `ProviderProfile::categories()` su pivot laukais;
factory būsena `ServiceRequest::expired()`.

---

## Etapas 3 – Autentifikacija, rolės, teikėjo profilis

**Tikslas:** vartotojai registruojasi kaip klientai arba teikėjai, teikėjai užpildo profilį.
**Sąvokos:** autentifikacija, middleware, Gates ir Policies, Form Request, failų įkėlimas, el. pašto patvirtinimas.

- [ ] Registracija su rolės pasirinkimu (Klientas / Paslaugų teikėjas), vardas ir pavardė
- [ ] El. pašto patvirtinimas ir slaptažodžio atkūrimas lietuviškai
- [ ] `role` middleware, Policies pagrindiniams modeliams
- [ ] Filament prieiga tik `admin` rolei (`canAccessPanel`)
- [ ] Teikėjo profilio vedlys: duomenys → kategorijos (3 lygių medis) → zonos (apskritis → savivaldybės, „visa Lietuva") → kainos „nuo"
- [ ] Profilio redagavimas, logotipas ir avataras (medialibrary)
- [ ] Portfolio CRUD su nuotraukomis
- [ ] Feature testai: registracija, prieigos teisės, profilio vedlys
- [ ] `docs/LEARNING.md`: Etapas 3 · PADARYK PATS · klausimai

PADARYK PATS (planuojamos): `ProviderProfilePolicy::update()`; Form Request portfolio darbui;
testas „klientas negali atidaryti teikėjo profilio vedlio".

---

## Etapas 4 – Katalogas ir paieška (viešoji dalis)

**Tikslas:** lankytojas randa paslaugą ir teikėją pagal kategoriją, miestą ar žodį.
**Sąvokos:** route model binding (slug), eager loading ir N+1, puslapiavimas, query scopes, cache, SEO.

- [ ] Pradžios puslapis: kategorijos, paieška, populiarūs miestai
- [ ] Kategorijų puslapiai (3 lygiai), „duonos trupiniai", SEO meta
- [ ] Teikėjų sąrašas pagal kategoriją ir miestą: filtrai, rikiavimas (reitingas, atsiliepimai), puslapiavimas
- [ ] Viešas teikėjo profilis: aprašymas, kainos, portfolio, atsiliepimai
- [ ] Paieška tekstu (MySQL FULLTEXT) ir sprendimas dėl Laravel Scout + Meilisearch
- [ ] Kategorijų medžio ir savivaldybių cache
- [ ] SEO puslapiai „{Paslauga} {mieste}" (`cities.name_locative`)
- [ ] Testai
- [ ] `docs/LEARNING.md`: Etapas 4 · PADARYK PATS · klausimai

PADARYK PATS (planuojamos): query scope `ProviderProfile::scopeActive()`; puslapiavimas teikėjų sąraše;
testas filtrui pagal miestą.

---

## Etapas 5 – Užklausos ir pasiūlymai (platformos šerdis)

**Tikslas:** klientas sukuria užklausą, tinkami teikėjai gauna pranešimą ir siunčia pasiūlymus už kreditus.
**Sąvokos:** daugiažingsnė forma, Actions, DB transakcijos ir užraktai, eilės (Jobs), Notifications, Scheduler,
būsenų mašina.

- [ ] Užklausos kūrimo forma (keli žingsniai, nuotraukos), Form Request validacija
- [ ] Moderavimas (`pending` → `open`) Filament'e + automatinės taisyklės
- [ ] Atitikimas: job randa teikėjus (kategorija su tėvais, zona arba „visa Lietuva") ir siunčia pranešimus
      (mail + database) pagal `notification_settings`
- [ ] Teikėjo užklausų srautas su filtrais
- [ ] Pasiūlymo siuntimas: kreditų patikra, `DB::transaction` + `lockForUpdate`, ledger įrašas
- [ ] Klientas mato pasiūlymus, priima arba atmeta; būsenų perėjimai pagal patvirtintą diagramą
- [ ] Darbo užbaigimas ir atšaukimas; Scheduler uždaro pasibaigusias užklausas
- [ ] Pranešimų varpelis (neperskaityti)
- [ ] Testai: visas srautas, lygiagretus kreditų nurašymas
- [ ] `docs/LEARNING.md`: Etapas 5 · PADARYK PATS · klausimai

PADARYK PATS (planuojamos): validacijos taisyklės užklausos formai; Action `WithdrawOffer` su kreditų grąžinimu;
testas „negalima siųsti pasiūlymo be kreditų".

---

## Etapas 6 – Žinutės, atsiliepimai, skundai

**Tikslas:** klientas ir teikėjas susirašinėja, po darbo paliekamas atsiliepimas, netinkamas turinys skundžiamas.
**Sąvokos:** `belongsToMany` su pivot laukais, observers, rate limiting, (pasirinktinai) broadcasting.

- [ ] Pokalbiai ir žinutės, neperskaitytų skaičius (`last_read_message_id`), priedai
- [ ] Atnaujinimas realiu laiku: pradžioje polling, paskui sprendimas dėl Laravel Reverb + Echo
- [ ] Atsiliepimai po darbo, pakvietimo nuoroda buvusiems klientams, teikėjo atsakymas
- [ ] Reitingo perskaičiavimas (observer → job)
- [ ] Skundo mygtukas (užklausa, pasiūlymas, atsiliepimas, žinutė, profilis) + nagrinėjimas Filament'e
- [ ] Rate limiting: žinutės, skundai, užklausos
- [ ] Testai
- [ ] `docs/LEARNING.md`: Etapas 6 · PADARYK PATS · klausimai

PADARYK PATS (planuojamos): `ReviewPolicy::create()`; observer, atnaujinantis `reviews_count`;
rate limiter žinutėms.

---

## Etapas 7 – Kreditai, prenumeratos, mokėjimai

**Tikslas:** teikėjai perka kreditus ir prenumeratas, mokėjimai patikimi ir idempotentiški.
**Sąvokos:** service container ir interfeisai, callback'ai ir webhook'ai, idempotencija, ledger, Scheduler, PDF.

- [ ] Kainų puslapis: kreditų paketai ir planai
- [ ] `PaymentGateway` sąsaja + Paysera įgyvendinimas (parašo tikrinimas, idempotencija)
- [ ] Kreditų ledger paslauga, balansas, istorijos puslapis
- [ ] Prenumeratos: pirkimas, kreditai kas laikotarpį (Scheduler), pratęsimas, atšaukimas, pasibaigimas
- [ ] Sąskaitos faktūros (numeracija, PDF)
- [ ] Filament: mokėjimai, kreditų operacijos, rankinis koregavimas
- [ ] Testai su netikru mokėjimų tiekėju
- [ ] `docs/LEARNING.md`: Etapas 7 · PADARYK PATS · klausimai

PADARYK PATS (planuojamos): `FakePaymentGateway` testams; testas „pakartotinis callback'as kreditų dukart
neužskaito"; Scheduler komanda pasibaigusioms prenumeratoms.

---

## Etapas 8 – Administravimas, kokybė, paleidimas

**Tikslas:** platforma paruošta realiems vartotojams: greita, saugi, prižiūrima.
**Sąvokos:** Filament widgets, `EXPLAIN` ir indeksų derinimas, cache, saugumas, BDAR, diegimas (deploy).

- [ ] Filament dashboard (statistika), moderavimo įrankiai, vartotojų blokavimas
- [ ] Našumas: `EXPLAIN` pagrindinėms užklausoms su pilnu seed'u (MySQL), indeksų korekcijos, cache
- [ ] Saugumas: autorizacijos auditas, failų įkėlimo validacija, rate limiting, saugos antraštės
- [ ] BDAR: duomenų eksportas, paskyros anonimizavimas
- [ ] SEO: `sitemap.xml`, struktūriniai duomenys (schema.org)
- [ ] Logai, klaidų stebėsena, atsarginės kopijos
- [ ] Diegimas: serveris, eilių supervisor, cron (scheduler), CI/CD
- [ ] Galutinė testų peržiūra
- [ ] `docs/LEARNING.md`: Etapas 8 · PADARYK PATS · klausimai

PADARYK PATS (planuojamos): Filament widget'as „užklausos per savaitę"; `EXPLAIN` analizė vienai lėtai užklausai;
anonimizavimo Action.
