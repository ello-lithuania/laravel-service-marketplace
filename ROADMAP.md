# ROADMAP

`[x]` – atlikta, `[ ]` – liko.
Kiekvienos sesijos pradžioje tęsiam nuo **pirmo nepažymėto etapo**. Etapas baigtas, kai pažymėti visi jo darbai
**ir vartotojas jį patvirtino**.

Kiekvieno etapo pabaigoje:

- `docs/LEARNING.md` papildomas sąvokomis, komandomis ir dažnomis klaidomis;
- vartotojui papasakojama, kas padaryta, kaip ir kodėl (be užduočių ir klausimų);
- daromas commit'as su šio failo pažymėjimais.

---

## Etapas 0 – Planavimas: DB schema, seed'ų planas, dokumentai

**Tikslas:** suprojektuoti duomenų bazę ir susitarti dėl taisyklių prieš rašant kodą.
**Sąvokos:** migracija, Eloquent ryšiai, indeksai, normalizacija ir denormalizacija, soft deletes, enum'ai,
ledger, idempotencija, seeder, factory, Faker.

- [x] DB schema su paaiškinimais, ryšiais ir indeksais → `docs/DB_SCHEMA.md`
- [x] Seed'ų planas (Faker lt_LT; 20k teikėjų, 100k užklausų, 300k pasiūlymų, 100k atsiliepimų, 200k žinučių) → `docs/SEEDING.md`
- [x] `CLAUDE.md` – stack'as, konvencijos, struktūra, mokymosi režimas
- [x] `ROADMAP.md` – etapai 0–8
- [x] `docs/LEARNING.md` – Etapo 0 sąvokos, komandos, klaidos ir svarbiausių dalykų paaiškinimai
- [x] Užklausos ir pasiūlymo būsenų mašinos, kreditų grąžinimo taisyklės → `docs/STATES.md`
- [x] Teikėjo užklausų srauto pavyzdys su indekso paaiškinimu → `docs/DB_SCHEMA.md` 8.1
- [x] **Vartotojas peržiūrėjo ir patvirtino schemą** (pageidaujami pakeitimai – atskiru commit'u)

---

## Etapas 1 – Projekto pagrindas

**Tikslas:** veikiantis tuščias Laravel 13 + Inertia + Vue projektas su lietuvių kalba, admin panele ir testais.
**Sąvokos:** projekto struktūra, `.env` ir `config/`, Composer ir NPM, Vite, Inertia puslapis, Artisan, maršrutai.

- [x] Laravel 13 projektas iš oficialaus Vue starter kit (Inertia 3 + Vue 3 + Tailwind 4); vartotojo sprendimu – 13, ne 12
- [x] Sprendimai užfiksuoti `CLAUDE.md`: TypeScript, Wayfinder, Fortify funkcijos (be 2FA ir passkeys), versijos
- [x] `.env.example`: SQLite dev'ui, MySQL pavyzdys, `APP_LOCALE=lt`, `APP_FAKER_LOCALE=lt_LT`
- [x] Lietuviški Laravel tekstai (`lang/lt`: validation, auth, passwords, pagination + `lang/lt.json` laiškams), datos lietuviškai (Carbon `lt`)
- [x] Filament 5 admin panelė `/admin`, lietuviška (laikinai pasiekiama tik lokaliai)
- [x] Pest (testai konvertuoti), Pint, PHPStan, GitHub Actions CI (`main` push'ams ir pull request'ams)
- [x] Bazinis išdėstymas: header, footer, laikinas logotipas, pradžios puslapio „griaučiai"
- [x] Šriftas su lietuviškomis raidėmis (Fontsource, `latin-ext`)
- [ ] Platformos pavadinimas (`APP_NAME`) – pasirenka vartotojas (kol kas laikinas: „Paslaugų platforma")
- [x] `docs/LEARNING.md`: Etapas 1 + santrauka vartotojui

---

## Etapas 2 – Duomenų bazė: migracijos, modeliai, ryšiai, seed'ai

**Tikslas:** visa schema kode ir pilnas, nuoseklus testinių duomenų rinkinys.
**Sąvokos:** migracijos, Eloquent modeliai, ryšiai, cast'ai, enum'ai, accessor'iai, factories ir būsenos,
seeder'iai, morph map, masinis įterpimas.

- [x] PHP enum'ai (`app/Enums`) pagal `DB_SCHEMA.md` 6 sk.
- [x] Migracijos pagal `DB_SCHEMA.md` 7 sk. eiliškumą (įskaitant žiedinį FK)
- [x] Modeliai: `#[Fillable]`, `casts()`, ryšiai, accessor'iai (`public_name`), morph map, `preventLazyLoading`
- [x] Factories su būsenomis (`->completed()`, `->company()`…)
- [x] Žinyniniai duomenys `database/data`: apskritys, savivaldybės, kategorijų medis, kreditų paketai, planai
- [x] Lietuviškų tekstų bankai seed'ams
- [x] Dideli seeder'iai pagal `docs/SEEDING.md` (+ `config/seeding.php`, `SEED_SCALE`)
- [x] Vientisumo testai (`SEEDING.md` 8 sk.)
- [x] Pilnas seed'as MySQL – išmatuotas laikas įrašytas į `SEEDING.md` (MySQL 8.0: ~2,5 min., SQLite: ~50 s)
- [x] Filament: kategorijų ir savivaldybių CRUD (pirmas susipažinimas su Filament)
- [x] `docs/LEARNING.md`: Etapas 2 + santrauka vartotojui

---

## Etapas 3 – Autentifikacija, rolės, teikėjo profilis

**Tikslas:** vartotojai registruojasi kaip klientai arba teikėjai, teikėjai užpildo profilį.
**Sąvokos:** autentifikacija, middleware, Gates ir Policies, Form Request, failų įkėlimas, el. pašto patvirtinimas.

- [ ] Registracija su rolės pasirinkimu (Klientas / Paslaugų teikėjas), vardas ir pavardė
- [ ] El. pašto patvirtinimas ir slaptažodžio atkūrimas lietuviškai
- [ ] Starter kit puslapiai (prisijungimas, registracija, paskyra, nustatymai) išversti į lietuvių kalbą
- [ ] Inertia bendri props: `auth.user` – tik reikalingi laukai (dabar siunčiamas visas `User` modelis)
- [ ] `role` middleware, Policies pagrindiniams modeliams
- [x] Filament prieiga tik `admin` rolei (`canAccessPanel`) – padaryta Etape 2
- [ ] Teikėjo profilio vedlys: duomenys → kategorijos (3 lygių medis) → zonos (apskritis → savivaldybės, „visa Lietuva") → kainos „nuo"
- [ ] Profilio redagavimas, logotipas ir avataras (medialibrary)
- [ ] Portfolio CRUD su nuotraukomis
- [ ] Feature testai: registracija, prieigos teisės, profilio vedlys
- [ ] `docs/LEARNING.md`: Etapas 3 + santrauka vartotojui

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
- [ ] `docs/LEARNING.md`: Etapas 4 + santrauka vartotojui

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
- [ ] Klientas mato pasiūlymus, priima arba atmeta; būsenų perėjimai pagal `docs/STATES.md`
- [ ] Darbo užbaigimas ir atšaukimas; Scheduler uždaro pasibaigusias užklausas
- [ ] Pranešimų varpelis (neperskaityti)
- [ ] Testai: visas srautas, lygiagretus kreditų nurašymas
- [ ] `docs/LEARNING.md`: Etapas 5 + santrauka vartotojui

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
- [ ] `docs/LEARNING.md`: Etapas 6 + santrauka vartotojui

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
- [ ] `docs/LEARNING.md`: Etapas 7 + santrauka vartotojui

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
- [ ] `docs/LEARNING.md`: Etapas 8 + santrauka vartotojui
