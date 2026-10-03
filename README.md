# Paslaugų platforma

Paslaugų platforma lietuvių kalba (funkcionalumas kaip paslaugos.lt): klientai skelbia užklausas, teikėjai siunčia
pasiūlymus, žinutės, atsiliepimai, kreditai ir prenumeratos, Filament admin panelė.

**Stack:** Laravel 13 · Inertia 3 + Vue 3 (TypeScript) · Tailwind CSS 4 · Filament · MySQL / SQLite · Pest.

## Paleidimas

Reikia: PHP 8.3+, Composer, Node.js 22+.

```bash
composer setup     # priklausomybės, .env, raktas, migracijos, frontend build
composer run dev   # serveris http://localhost:8000 + eilės + Vite + logai
```

Testai ir kodo stilius:

```bash
composer test      # Pint + PHPStan + testai
npm run check      # frontend lint ir formatavimas
```

## Dokumentai

| Failas | Kam |
|---|---|
| [`CLAUDE.md`](CLAUDE.md) | stack'as, konvencijos, darbo taisyklės |
| [`ROADMAP.md`](ROADMAP.md) | etapai 0–8 ir kur esam |
| [`docs/DB_SCHEMA.md`](docs/DB_SCHEMA.md) | duomenų bazės schema |
| [`docs/SEEDING.md`](docs/SEEDING.md) | testinių duomenų planas |
| [`docs/STATES.md`](docs/STATES.md) | užklausų ir pasiūlymų būsenos |
| [`docs/LEARNING.md`](docs/LEARNING.md) | mokymosi užrašai |
