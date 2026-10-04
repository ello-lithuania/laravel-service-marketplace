## Etapas 10b – Naujas viešos dalies dizainas

> Lygiagrečiai su Etapu 10a (nuotraukų atsisiuntimas, Filament nuotraukų valdymas, autorių puslapis).
> Šis juodraštis sujungiant perkeliamas į `docs/LEARNING.md` (Etapas 10).

**Būsena**

- [x] Dizaino sistema: spalvos (šviesus ir tamsus režimai), šriftai, spinduliai, šešėliai, mygtukų variantai
- [x] Logotipas (ženklas + pavadinimas iš `config('app.name')`)
- [x] Nuotraukų vietos su atsarginiu dizainu (`PhotoSlot`, `PhotoFallback`, tonai pagal sritį)
- [x] Antraštė ir poraštė (`PublicLayout`), mobilusis meniu
- [x] Pradžios puslapis: viršus, skaičiai iš DB, kategorijos, „Kaip tai veikia", teikėjai, atsiliepimai, miestai,
      kvietimas teikėjams, DUK
- [x] Katalogas: `/paslaugos`, kategorija (ir „paslauga mieste"), `/meistrai`, `/paieska` – filtrų šoninė juosta
- [x] Teikėjo profilis: viršelis, galerija su peržiūra, lipnus mygtukas telefone
- [x] Kainų puslapis (logika nepakeista)
- [x] Prisijungimas ir registracija su nuotrauka; paskyros apvalkalas pagal naują paletę
- [x] Testai: `tests/Feature/Site/SiteHighlightsTest.php`, `tests/Feature/Site/DesignPropsTest.php`

### Ką darėm ir kodėl

Svetainė veikė, bet atrodė kaip „šablonas": juoda–balta shadcn paletė, vienodos kortelės, centruoti tekstai, jokių
nuotraukų. Tikslas – kad platforma atrodytų kaip tikras, patikimas Lietuvos paslaugų portalas (Thumbtack, Checkatrade,
Bark lygis), bet su savo veidu.

- **Dizaino sistema** (`resources/css/app.css`). Visos spalvos – CSS kintamieji (`--primary`, `--cta`…), o Tailwind
  tema juos paverčia klasėmis (`bg-primary`, `text-cta-foreground`). Paletė: šiltas „popieriaus" fonas `#f7f5f0`,
  gilus pušų žalias `#0f5b45` (pasitikėjimas, namai, gamta), šilta oranžinė `#f28c28` **tik** pagrindiniams veiksmams
  („Sukurti užklausą"), tamsus `brand-deep` poraštei ir kvietimams. Tamsiame režime – žalsvai juodas fonas ir šviesesnė
  mėtų žalia. Kontrastai patikrinti pagal WCAG AA (pvz. pilkas tekstas ant fono 5,5:1, baltas ant žalios 8:1,
  tamsus tekstas ant oranžinės 7,5:1).
- **Šriftai.** Tekstui paliktas Instrument Sans, antraštėms pridėtas **Bricolage Grotesque** (kintamas šriftas su
  optiniu dydžiu) iš Fontsource. Charakteringas, bet įskaitomas; turi `latin-ext` (ą č ę ė į š ų ū ž).
- **Logotipas.** Ženklas – stogas su varnele („patikimas darbas namuose") ir oranžinis taškas („naujas pasiūlymas").
  Pavadinimas imamas iš `config('app.name')`: pirmas žodis paryškintas, kiti plonesni – tinka bet kokiam vardui.
- **Nuotraukos ir jų nebuvimas.** Kol administratorius neatsisiuntė nuotraukų, kiekviena vieta rodo sąmoningą
  atsarginį dizainą: srities spalvos gradientą, „topografines" linijas ir didelę srities ikoną. Tonas parenkamas pagal
  ikoną (sodas – samanų žalia, elektra – ochra, santechnika – žydra), todėl kortelės nevienodos. `PhotoSlot` net
  nepavykus užkrauti paveikslėlio (`@error`) parodo atsarginį dizainą – sugedusio paveikslėlio niekada nebus.
- **Tikri skaičiai ir atsiliepimai** pradžios puslapyje (`App\Services\Site\SiteHighlights`): aktyvūs teikėjai,
  atsiliepimai, svertinis vidutinis įvertinimas, atlikti darbai – **viena** agregato užklausa iš teikėjų skaitliukų;
  naujausi 5★ atsiliepimai su išsamiu komentaru. Cache 1 val. Kol platforma nauja (< 10 teikėjų), vietoj skaičių
  rodomi trys pažadai – „3 teikėjai" atrodytų prastai.
- **Pradžios puslapis** – nevienodo ritmo skiltys: asimetrinis viršus (tekstas + nuotrauka su „plaukiojančiomis"
  kortelėmis iš tikrų duomenų), skaičių juosta, kategorijų „bento" tinklelis (pirma kortelė 2 × 2), „Kaip tai veikia"
  su iliustracija, teikėjų kortelės, tamsi didelė atsiliepimo kortelė + mažesnės, miestai, tamsus kvietimas teikėjams,
  DUK. Be nuotraukos viršuje – iliustracija iš sąsajos elementų (užklausa ir į ją atsakę teikėjai).
- **Katalogas.** Kategorijos puslapio viršuje plati juosta (`image_wide_url`, 1600×700) su antrašte ir CTA; 2–3 lygiai
  paveldi srities nuotrauką. Bendras `ProviderResults`: filtrai šoninėje juostoje (telefone – `Sheet` skydelyje),
  aktyvių filtrų „čipai", tuščios būsenos kvietimas sukurti užklausą.
- **Profilis.** Viršelis, ant jo „užlipanti" antraštės kortelė, skilčių meniu, kainos „ženkleliuose", galerija su
  peržiūra visame ekrane (`Dialog`, ← →, miniatiūros), lipni juosta su mygtuku telefone.
- **Prisijungimas** – forma kairėje, svetainės nuotrauka dešinėje (be nuotraukos – tamsus skydelis su raštu).
- **Paskyra** – didelio pertvarkymo nedarėm: paletė ir šriftai ateina per tuos pačius CSS kintamuosius, pridėta tik
  nuoroda „Grįžti į svetainę".

### Sprendimai ir alternatyvos

| Klausimas                    | Pasirinkta                                          | Alternatyva ir kodėl ne                                                                                               |
| ---------------------------- | --------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------- |
| Kaip keisti spalvas          | CSS kintamieji + `@theme inline`                    | „kietos" spalvos klasėse (`bg-[#0f5b45]`) – tamsus režimas reikalautų `dark:` prie kiekvienos klasės                  |
| Pagrindinio veiksmo spalva   | atskiras `--cta` tokenas ir mygtuko variantas `cta` | shadcn `--accent` – jis skirtas „hover" fonams (meniu, ghost mygtukai), pakeitus nuspalvintų visus meniu              |
| Antraščių šriftas            | Bricolage Grotesque (Fontsource, kintamas)          | Google Fonts / Bunny – užblokuoti ir siunčia lankytojų IP trečiajai šaliai; serifinis (Fraunces) – per „žurnalinis"   |
| Nuotraukos nėra              | spalvinis atsarginis dizainas su ikona              | pilkas kvadratas ar „stock" nuotrauka repozitorijoje – atrodo nebaigta arba neatitinka licencijų                      |
| Poraštės sritys ir miestai   | bendras prop'as `site` su `Inertia::once`           | į kiekvieno controller'io props – 6 vietos; sąrašas Vue faile – administratoriui pakeitus kategoriją nuorodos „lūžtų" |
| `site` paskyros puslapiuose  | nesiunčiamas (maršrutai su `auth` middleware)       | visada – be reikalo 3 cache skaitymai kiekvieno paskyros puslapio pirmam atidarymui                                   |
| Skaičių ir atsiliepimų cache | `Cache::remember` 1 val.                            | `rememberForever` + observer – kiekvienas naujas atsiliepimas valytų cache, o minutės tikslumo nereikia               |
| DUK                          | `<details>`/`<summary>`                             | `ui/collapsible` (reka-ui) – veiktų, bet `<details>` veikia ir be JavaScript, prieinamumas „iš dėžės"                 |
| Kategorijų tinklelis         | fiksuotas eilučių aukštis (`auto-rows-[12rem]`)     | `aspect-[4/3]` + `h-full` – planšetėje kortelės „išlįsdavo" (aukštis ir proporcija prieštarauja vienas kitam)         |
| Galerijos peržiūra           | `Dialog` (jau yra) + `onKeyStroke`                  | lightbox biblioteka – papildomi KB dėl vienos funkcijos                                                               |

### Išmoktos sąvokos

#### 1. Tailwind 4 tema ir CSS kintamieji

Tailwind 4 konfigūruojamas pačiame CSS faile. `@theme` bloke kiekvienas kintamasis tampa klasių šeima:
`--color-cta` → `bg-cta`, `text-cta`, `border-cta/30`; `--font-display` → `font-display`; `--shadow-soft` → `shadow-soft`.

```css
@theme inline {
    --color-cta: var(--cta); /* klasė bg-cta → background: var(--cta) */
    --font-display: 'Bricolage Grotesque Variable', sans-serif;
    --shadow-soft: var(--elevation-1);
}
:root {
    --cta: #f28c28;
}
.dark {
    --cta: #f59a3c;
}
```

- `inline` reiškia, kad klasėje lieka `var(--cta)`, todėl `.dark` klasė pakeičia tik kintamąjį, o ne klases.
- Kintamojo vardas temoje ir `:root` turi skirtis (`--shadow-soft: var(--elevation-1)`) – `--x: var(--x)` būtų ciklas.
- `@utility page-container { … }` – sava klasė (kaip `.container`), veikia su `md:`, `hover:` ir kt.
- Permatomumas – `/` po spalvos: `bg-primary/10`, `ring-white/25`.
  → https://tailwindcss.com/docs/theme · https://tailwindcss.com/docs/adding-custom-styles

#### 2. Tamsus režimas

Starter kit `.dark` klasę uždeda `<html>` elementui (nustatymuose „Išvaizda" arba pagal sistemą, `app.blade.php`
skriptas su nonce). `@custom-variant dark (&:is(.dark *))` leidžia rašyti `dark:bg-…`, bet geriau – tokenai, kurie
patys keičiasi. `color-scheme: dark` liepia naršyklei ir savo valdiklius (select sąrašą, slinkties juostą) piešti
tamsius. Dažna klaida – `bg-white text-foreground`: tamsiame režime `foreground` šviesus → baltas ant balto.
→ https://tailwindcss.com/docs/dark-mode

#### 3. Fontsource ir `unicode-range`

`npm install @fontsource-variable/bricolage-grotesque`, CSS: `@import '@fontsource-variable/bricolage-grotesque/opsz.css';`.
Vite šrifto failus nukopijuoja į `public/build`, todėl nereikia jokio išorinio serverio (CSP `font-src 'self'`).
Paketas turi kelis failus su `unicode-range` – naršyklė parsisiunčia tik tuos, kurių raidžių puslapyje yra:
lietuviškos raidės (U+0100–017F) yra `latin-ext` faile. Kintamas šriftas (variable font) – vienas failas visiems
storiams, o `opsz` ašis didelėms antraštėms parenka „glaudesnes" raidžių formas (`font-optical-sizing: auto`).
→ https://fontsource.org/docs/getting-started/install

#### 4. Prisitaikantys paveikslėliai ir išdėstymo „šokinėjimas" (CLS)

- `width`/`height` atributai (tikri konversijos matmenys) – naršyklė žino proporcijas dar prieš atsisiųsdama.
- Formatą nustato tėvas (`aspect-[4/3]`, `h-72`), o `<img class="absolute inset-0 size-full object-cover">` jį užpildo.
- `loading="lazy"` – paveikslėliai žemiau ekrano kraunami tik priartėjus; viršuje (hero) – `eager` ir
  `fetchpriority="high"`. `decoding="async"` – dekodavimas neblokuoja puslapio.
- `sizes` pasako, kokio pločio bus paveikslėlis (`(min-width: 1024px) 25vw, 50vw`) – prireiks, kai medialibrary
  generuos `srcset` (responsive images).
  → https://developer.mozilla.org/docs/Web/HTML/Element/img · https://spatie.be/docs/laravel-medialibrary

#### 5. Inertia: duomenys dizainui

- Dizainui reikalingi duomenys (nuotraukų URL, skaičiai) – tokie patys props kaip kiti: controller'is → Vue.
- **Bendri props** (`HandleInertiaRequests::share`) – kiekvienam puslapiui. `Inertia::once(fn () => …)` – serveris
  apskaičiuoja vieną kartą, naršyklė prisimena, o vėlesniuose perėjimuose serveris jo nebesiunčia ir neskaičiuoja.
- Prop'ą galima siųsti tik daliai maršrutų: `$request->route()->gatherMiddleware()` → ar yra `auth`.
- Testuose: `->has('site.cities', 8)`, `->missing('site')`.
  → https://inertiajs.com/shared-data

#### 6. Prieinamumas (a11y) – ką tikrinom

- **Kontrastas** ≥ 4,5:1 tekstui (WCAG AA); tamsus tekstas ant oranžinio mygtuko, nes baltas būtų tik 3,7:1.
- **Fokusas:** visi interaktyvūs elementai turi matomą `focus-visible:ring`; „Pereiti prie turinio" nuoroda
  (matoma tik fokusavus klaviatūra).
- **Semantika:** vienas `<h1>` puslapyje, skiltys `<section aria-labelledby>`, `<nav aria-label>`, `<figure>` ir
  `<blockquote>` atsiliepimams, `<dl>` skaičiams, radio mygtukai įvertinimo filtrui (`fieldset` + `legend`).
- **Dekoratyvūs** paveikslėliai ir ikonos – `alt=""` / `aria-hidden="true"`; prasmingi – su `alt` tekstu.
- **Judesys:** `prefers-reduced-motion: reduce` – animacijos ir perėjimai išjungiami, sklandus slinkimas tik kitiems.
- `Sheet`/`Dialog` (reka-ui) patys sulaiko fokusą skydelio viduje ir uždaro su Esc.
  → https://developer.mozilla.org/docs/Web/Accessibility · https://www.w3.org/WAI/WCAG22/quickref/#contrast-minimum

#### 7. Smulkūs CSS būdai, kurie „atgaivina" dizainą

- `sticky top-0 backdrop-blur-md bg-background/85` – permatoma lipni antraštė.
- „Visa kortelė paspaudžiama": nuoroda su `after:absolute after:inset-0` kortelėje su `relative`.
- `has-checked:` / `has-[[data-state=checked]]:` – tėvo stilius pagal vaiko būseną (pažymėtas radio ar checkbox).
- `text-balance` antraštėms, `text-pretty` pastraipoms – gražesnis eilučių laužymas; `&nbsp;` prieš brūkšnį,
  kad eilutė neprasidėtų „–".
- `grid auto-rows-[12rem]` + `row-span-2` – „bento" tinklelis be proporcijų konfliktų.

### Naudingos komandos

```bash
npm install @fontsource-variable/bricolage-grotesque   # šriftas iš npm (latin-ext įeina)
npm run build && php artisan serve                      # peržiūra su sukompiliuotais failais
npm run check:fix && npm run types:check                # formatavimas, lint, TypeScript
php artisan test --filter=SiteHighlights                # skaičių ir atsiliepimų testai
php artisan tinker --execute 'app(App\Services\Site\SiteHighlights::class)->forget();'   # išvalyti skaičių cache
```

### Dažnos klaidos

- **`bg-white text-foreground`** – tamsiame režime tekstas tampa šviesus ant balto. Ant baltų ženkliukų – `text-brand-deep`.
- **`h-full` kartu su `aspect-*`** – kai eilutės aukštį nustato kitas elementas, plotis apskaičiuojamas iš aukščio ir
  kortelė išlenda už stulpelio. Tinklelyje – fiksuotas eilučių aukštis.
- **`line-clamp-1` kartu su `block`** – `block` perrašo `display: -webkit-box`, ir teksto apkarpymas neveikia.
- **Grid elementas su `truncate` viduje** praplečia stulpelį (`min-width: auto`) – reikia `min-w-0` arba `grid-cols-1`.
- **Antraštė ar paieška netelpa** – per daug elementų vienoje eilutėje; mažesniame ekrane dalį slėpti (`hidden xl:inline-flex`)
  arba perkelti į skydelį.
- **Tas pats `id` du kartus** (filtrai šoninėje juostoje ir skydelyje) – `idPrefix` prop'as.
- **Bendras prop'as su DB užklausa** kiekvienam puslapiui – N+1 testai su šaltu cache tai pagauna; skaičiuoti tik ten,
  kur reikia, ir imti iš cache.
- **Nuotraukų nėra** – nepalikti tuščių `<img>`: `PhotoSlot` rodo atsarginį dizainą ir tada, kai URL yra, bet failas dingo.
