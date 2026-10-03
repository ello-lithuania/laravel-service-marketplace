# Diegimas (deploy), priežiūra ir atsarginės kopijos

> **Etapas 8.** Kaip paleisti platformą produkcijos serveryje ir ją prižiūrėti: reikalavimai, pirmas diegimas,
> atnaujinimai be prastovos, eilių darbuotojai (Supervisor), Scheduler (cron), Nginx, `.env` sąrašas, saugumas,
> logai ir klaidų stebėsena, atsarginės kopijos, CI/CD. Sąvokos paaiškintos `docs/drafts/etapas-8.md`.
> Laravel dokumentacija: https://laravel.com/docs/13.x/deployment

## Turinys

1. [Architektūra](#1-architektūra)
2. [Serverio reikalavimai](#2-serverio-reikalavimai)
3. [Pirmas diegimas](#3-pirmas-diegimas)
4. [Atnaujinimas (kiekvienas deploy)](#4-atnaujinimas-kiekvienas-deploy)
5. [Diegimas be prastovos (zero-downtime)](#5-diegimas-be-prastovos-zero-downtime)
6. [Eilių darbuotojai – Supervisor](#6-eilių-darbuotojai--supervisor)
7. [Scheduler – cron](#7-scheduler--cron)
8. [Nginx](#8-nginx)
9. [.env produkcijai](#9-env-produkcijai)
10. [Saugumas](#10-saugumas)
11. [Logai, klaidų stebėsena, sveikatos patikra](#11-logai-klaidų-stebėsena-sveikatos-patikra)
12. [Atsarginės kopijos](#12-atsarginės-kopijos)
13. [CI/CD su GitHub Actions](#13-cicd-su-github-actions)
14. [Patikra po diegimo](#14-patikra-po-diegimo)

---

## 1. Architektūra

```
Naršyklė ──HTTPS──▶ Nginx ──FastCGI──▶ PHP-FPM 8.4 (Laravel)
                     │                    │  │  │
                     │ /build, /storage   │  │  └──▶ Redis 7: cache, sesijos, eilės, užraktai
                     │ (statiniai failai) │  └─────▶ MySQL 8.4 LTS
                     ▼                    ▼
                 public/ katalogas     storage/app (nuotraukos, BDAR archyvai) arba S3

Supervisor ──▶ php artisan queue:work (2+ procesai): laiškai, pranešimai, miniatiūros, BDAR eksportas
cron ───────▶ php artisan schedule:run (kas minutę): pasibaigusios užklausos, archyvų valymas, prenumeratos (Etapas 7)
```

Vienam serveriui (pradžiai) viskas gali būti vienoje mašinoje (4 vCPU, 8 GB RAM). Augant – MySQL ir Redis į atskirus
(valdomus) serverius, o web serverių – keli už apkrovos balansuotojo. Tam kodas jau paruoštas: sesijos ir cache
Redis'e, failai gali būti S3 (`MEDIA_DISK`), Scheduler užduotys – `onOneServer()`.

_Alternatyvos rankiniam serveriui:_ Laravel Cloud, Laravel Forge ar Ploi (serverį sukonfigūruoja patys: Nginx,
Supervisor, cron, SSL, deploy scenarijus). Mokymosi tikslais žemiau aprašyta, ką jie daro „po gaubtu".

## 2. Serverio reikalavimai

| Kas      | Versija     | Pastabos                                                                                      |
| -------- | ----------- | --------------------------------------------------------------------------------------------- |
| PHP      | 8.4 (≥ 8.3) | FPM produkcijai, CLI – artisan, eilėms ir cron                                                |
| MySQL    | 8.4 LTS     | `utf8mb4` / `utf8mb4_unicode_ci`; FULLTEXT paieškai (`innodb_ft_min_token_size=3` – numatyta) |
| Redis    | 7.x         | cache, sesijos, eilės; PHP plėtinys `redis` (phpredis, `REDIS_CLIENT=phpredis`)               |
| Node.js  | 22 LTS      | tik `npm run build` metu (gali būti ir CI, tada serveryje Node nereikia)                      |
| Composer | 2.x         |                                                                                               |
| Nginx    | 1.24+       | + certbot (Let's Encrypt) HTTPS sertifikatui                                                  |

**PHP plėtiniai** ir kam jie reikalingi:

| Plėtinys                                                                                             | Kam                                                             |
| ---------------------------------------------------------------------------------------------------- | --------------------------------------------------------------- |
| `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `fileinfo`, `bcmath`, `curl`, `dom` | Laravel branduolys                                              |
| `intl`                                                                                               | `Collator('lt_LT')` – lietuviška abėcėlė (savivaldybių sąrašas) |
| `gd` (arba `imagick`)                                                                                | medialibrary miniatiūros                                        |
| `exif`                                                                                               | nuotraukų pasukimas pagal telefono orientaciją (medialibrary)   |
| `zip`                                                                                                | BDAR duomenų archyvas (`GenerateUserDataExport`)                |
| `xmlwriter`                                                                                          | `sitemap.xml`                                                   |
| `redis`                                                                                              | Redis cache / sesijos / eilės                                   |
| `pcntl`                                                                                              | `queue:work` korektiškai sustoja gavęs signalą (deploy metu)    |
| `opcache`                                                                                            | **privaloma**: PHP kodas laikomas atmintyje sukompiliuotas      |

**php.ini (FPM):**

```ini
memory_limit = 256M
upload_max_filesize = 8M        ; nuotrauka iki 5 MB (ImageValidationRules) + atsarga
post_max_size = 64M             ; iki 10 portfolio nuotraukų vienu kartu
max_execution_time = 60
opcache.enable = 1
opcache.memory_consumption = 256
opcache.validate_timestamps = 0 ; failų pakeitimų netikrina – po deploy būtina perkrauti PHP-FPM
expose_php = Off
```

CLI (eilėms) `memory_limit = 512M`: miniatiūrų kūrimas 8000×8000 px nuotraukai GD užima ~256 MB.

## 3. Pirmas diegimas

```bash
# 1. Vartotojas ir katalogas (Nginx ir PHP-FPM veikia kaip www-data)
sudo mkdir -p /var/www/paslaugos && sudo chown deploy:www-data /var/www/paslaugos
git clone git@github.com:ORGANIZACIJA/laravel-service-marketplace.git /var/www/paslaugos
cd /var/www/paslaugos

# 2. Aplinka
cp .env.example .env            # ir užpildyti pagal 9 skyrių
php artisan key:generate        # APP_KEY – šifravimo raktas; pakeitus, senos sesijos ir užšifruoti duomenys negalioja

# 3. Priklausomybės ir frontend'as
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build         # public/build + Vite manifestas

# 4. Duomenų bazė (demo seed'ų produkcijoje NEleisti: SEED_DEMO=false)
php artisan migrate --force     # --force: produkcijoje artisan kitaip klaustų patvirtinimo
php artisan db:seed --force     # tik žinyniniai duomenys: savivaldybės, kategorijos, paketai, planai, administratoriai

# 5. Failai ir teisės
php artisan storage:link        # public/storage → storage/app/public (nuotraukos)
sudo chown -R deploy:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache

# 6. Cache produkcijai (žr. 4 sk.)
php artisan optimize
php artisan filament:optimize

# 7. Supervisor (6 sk.), cron (7 sk.), Nginx (8 sk.), HTTPS
sudo certbot --nginx -d www.example.lt -d example.lt
```

Administratorių slaptažodžius po pirmo seed'o **iš karto pakeisti** (AdminSeeder sukuria `admin1@example.test` su
slaptažodžiu `password` – tai tik dev'ui; produkcijoje sukurti tikrus administratorius per `php artisan tinker`).

## 4. Atnaujinimas (kiekvienas deploy)

```bash
cd /var/www/paslaugos
php artisan down --retry=60          # 503 su „Retry-After"; galima ir be to – žr. 5 sk.

git pull --ff-only origin main
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build
php artisan migrate --force

php artisan optimize                 # config:cache + route:cache + view:cache + event:cache
php artisan filament:optimize        # Filament komponentų ir ikonų cache
php artisan storage:link             # jei dar nėra (komanda idempotentiška)
php artisan queue:restart            # darbuotojai baigs dabartinį job'ą ir pasileis su nauju kodu
sudo systemctl reload php8.4-fpm     # išvalo OPcache (validate_timestamps=0)

php artisan up
```

**Kodėl cache komandos:** `config:cache` sujungia visus `config/*.php` į vieną failą (nereikia kiekvieną kartą skaityti
`.env` ir dešimčių failų), `route:cache` – maršrutų sąrašą, `view:cache` – sukompiliuotus Blade šablonus,
`event:cache` – rastus listener'ius (event discovery). Po `config:cache` funkcija `env()` už `config/` ribų grąžina
`null` – todėl kode `env()` naudojamas tik konfigūracijos failuose. → https://laravel.com/docs/13.x/deployment#optimization

**Kodėl `queue:restart`:** darbuotojas (`queue:work`) – ilgai veikiantis procesas, jis kodą užkrauna vieną kartą.
Be perkrovimo jis toliau vykdytų seną kodą. `queue:restart` per cache praneša „baik ir išeik", o Supervisor jį paleidžia
iš naujo.

## 5. Diegimas be prastovos (zero-downtime)

`git pull` vietoje turi trūkumą: kelias sekundes serveryje būna pusiau naujas kodas (nauji PHP failai, bet seni
`vendor/` ar `public/build`). Profesionalus būdas – **releases + symlink** (taip veikia Envoyer, Deployer, Forge):

```
/var/www/paslaugos/
  releases/20261003-120000/   ← kiekvienas deploy – naujas katalogas (git clone + composer + npm build)
  releases/20261004-090000/
  shared/.env                 ← bendri visiems releases (symlink'ai į kiekvieną release)
  shared/storage/
  current -> releases/20261004-090000   ← Nginx root: /var/www/paslaugos/current/public
```

1. Naujame `releases/…` kataloge paruošiama viskas (priklausomybės, build, `optimize`), `shared/` susiejama symlink'ais.
2. `php artisan migrate --force` (iš naujo release).
3. `ln -sfn releases/… current` – akimirksniu perjungiama (atominis symlink pakeitimas).
4. `php artisan queue:restart`, PHP-FPM reload. Senus releases (paliekant 5) ištrinti – grįžimas atgal = symlink į seną.

**Migracijos be prastovos (expand → contract):** kol perjungiama, kelias sekundes veikia ir senas, ir naujas kodas,
todėl migracija neturi „sulaužyti" seno kodo. Stulpelio pervadinimas – trimis deploy'ais: (1) pridėti naują
stulpelį ir rašyti į abu, (2) perjungti skaitymą į naują, (3) ištrinti seną. Indeksų pridėjimas (kaip
`tune_indexes_after_explain`) MySQL 8 vyksta `ALGORITHM=INPLACE` – lentelė lieka prieinama.

## 6. Eilių darbuotojai – Supervisor

Eilėje vykdoma: pranešimai (mail + database), `NotifyMatchingProviders`, portfolio miniatiūros, BDAR eksportas.
Supervisor – procesų prižiūrėtojas: paleidžia darbuotojus paleidžiant serverį ir iš naujo, jei kuris nukrenta.

`/etc/supervisor/conf.d/paslaugos-worker.conf`:

```ini
[program:paslaugos-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/paslaugos/current/artisan queue:work redis --queue=default --sleep=3 --tries=3 --backoff=10 --max-time=3600 --memory=512
directory=/var/www/paslaugos/current
user=www-data
numprocs=2
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
; leisti baigti ilgiausią job'ą (BDAR eksportas) prieš priverstinai sustabdant
stopwaitsecs=600
redirect_stderr=true
stdout_logfile=/var/www/paslaugos/shared/storage/logs/worker.log
stdout_logfile_maxbytes=20MB
stdout_logfile_backups=5
```

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start "paslaugos-worker:*"
sudo supervisorctl status
```

- `--max-time=3600` – darbuotojas kas valandą išeina ir pasileidžia iš naujo (apsauga nuo atminties „nutekėjimo").
- `--tries=3 --backoff=10` – nepavykęs job'as bandomas 3 kartus kas 10 s, paskui patenka į `failed_jobs`
  (`php artisan queue:failed`, `queue:retry all`).
- **Laravel Horizon** – oficialus Redis eilių skydelis (darbuotojų skaičius pagal apkrovą, statistika, nepavykę job'ai).
  Verta, kai eilių daug ir norisi matyti, kas vyksta; tada Supervisor paleidžia vieną `php artisan horizon` procesą.
  → https://laravel.com/docs/13.x/horizon

## 7. Scheduler – cron

Vienintelė cron eilutė (vartotojui `www-data`, `sudo crontab -u www-data -e`):

```cron
* * * * * cd /var/www/paslaugos/current && php artisan schedule:run >> /dev/null 2>&1
```

Laravel kas minutę pats sprendžia, kurias užduotis vykdyti (`routes/console.php`). Sąrašas: `php artisan schedule:list`.

| Užduotis                          | Kada          | Kas tai                                                |
| --------------------------------- | ------------- | ------------------------------------------------------ |
| `service-requests:expire`         | kas valandą   | pasibaigusios užklausos, kreditų grąžinimas (Etapas 5) |
| `privacy:prune-exports`           | kasdien 03:15 | BDAR archyvų, senesnių nei 7 d., ištrynimas (Etapas 8) |
| prenumeratų pratęsimas (Etapas 7) | kasdien       | žr. Etapo 7 dokumentaciją                              |

`onOneServer()` – kai web serverių keli, užduotis vykdoma tik viename (užraktas laikomas cache – todėl visi serveriai
turi naudoti **tą patį** Redis). `withoutOverlapping()` – nauja nepradedama, kol nesibaigė ankstesnė.
WordPress analogas – `wp_cron`, tik patikimesnis: nepriklauso nuo lankytojų apsilankymų.

## 8. Nginx

`/etc/nginx/sites-available/paslaugos.conf` (certbot pats papildo sertifikato eilutes):

```nginx
server {
    listen 80;
    server_name example.lt www.example.lt;
    return 301 https://www.example.lt$request_uri;      # visada HTTPS ir vienas domenas (SEO: vienas canonical)
}

server {
    listen 443 ssl http2;
    server_name www.example.lt;
    root /var/www/paslaugos/current/public;
    index index.php;
    charset utf-8;

    client_max_body_size 64M;                           # = post_max_size

    # Vite build failai turi hash'ą pavadinime – juos galima laikyti metus
    location /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        add_header X-Content-Type-Options nosniff;
        try_files $uri =404;
    }

    # Vartotojų įkelti failai: jokio skriptų vykdymo ir „spėliojimo", net jei kas nors įkeltų HTML
    location /storage/ {
        expires 30d;
        add_header X-Content-Type-Options nosniff;
        add_header Content-Security-Policy "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox";
        try_files $uri =404;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;     # $realpath_root – svarbu su „current" symlink'u
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 60;
    }

    # Joks kitas .php failas ir paslėpti failai (.env, .git) nepasiekiami
    location ~ \.php$ { return 404; }
    location ~ /\.(?!well-known).* { deny all; }

    gzip on;
    gzip_types text/plain text/css application/json application/javascript application/xml image/svg+xml;
}
```

Saugos antraštes (CSP, HSTS, X-Frame-Options…) dinaminiams atsakymams prideda Laravel (`SecurityHeaders`), todėl
Nginx jų nedubliuoja. `robots.txt` generuoja Laravel – `public/robots.txt` failo nėra (jį Nginx atiduotų pirmą).

## 9. .env produkcijai

| Kintamasis                                      | Reikšmė                         | Kodėl                                                                    |
| ----------------------------------------------- | ------------------------------- | ------------------------------------------------------------------------ |
| `APP_ENV`                                       | `production`                    | įjungia HSTS, griežtus slaptažodžius, draudžia destruktyvias DB komandas |
| `APP_DEBUG`                                     | `false`                         | **būtina**: kitaip klaidos puslapis parodytų kodą, užklausas ir `.env`   |
| `APP_URL`                                       | `https://www.example.lt`        | nuorodos laiškuose, sitemap, canonical, medialibrary URL                 |
| `APP_KEY`                                       | `php artisan key:generate`      | slaptas; niekada į Git                                                   |
| `LOG_STACK` / `LOG_LEVEL`                       | `daily,slack` / `info`          | 11 sk.                                                                   |
| `DB_CONNECTION` …                               | `mysql`, atskiras DB vartotojas | DB vartotojas – tik šiai DB (`GRANT … ON paslaugos.*`), ne `root`        |
| `CACHE_STORE`                                   | `redis`                         | greitas, bendras visiems serveriams, palaiko užraktus (`onOneServer`)    |
| `SESSION_DRIVER`                                | `redis`                         | robotų sesijos neapkrauna DB                                             |
| `SESSION_SECURE_COOKIE`                         | `true`                          | slapukas tik per HTTPS                                                   |
| `SESSION_ENCRYPT`                               | `true`                          | sesijos turinys šifruojamas Redis'e                                      |
| `SESSION_SAME_SITE`                             | `lax`                           | CSRF apsauga; `strict` sulaužytų atėjimą iš el. laiško nuorodos          |
| `QUEUE_CONNECTION`                              | `redis`                         | `database` eilė prie apkrovos – lėta ir apkrauna DB                      |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_FROM_ADDRESS` | SMTP / Postmark / SES           | `log` – tik dev'e; nustatyti SPF, DKIM, DMARC domenui                    |
| `FILESYSTEM_DISK` / `MEDIA_DISK`                | `local` / `public` (arba `s3`)  | kelių serverių atveju nuotraukos – S3 suderinamoje saugykloje            |
| `SEED_DEMO`                                     | `false`                         | demo duomenų produkcijoje nereikia                                       |
| `CSP_REPORT_ONLY`                               | pirmą savaitę `true`            | 10 sk.                                                                   |
| `BCRYPT_ROUNDS`                                 | `12`                            | slaptažodžių hash'o „kaina"                                              |

Patikrinti: `php artisan about` (aplinka, cache būsena, tvarkyklės), `php artisan config:show session`.

## 10. Saugumas

- **HTTPS visur** + HSTS (įjungiama automatiškai `APP_ENV=production`). Sertifikatą atnaujina certbot (`systemctl list-timers`).
- **Už apkrovos balansuotojo ar Cloudflare** Laravel turi žinoti, kad prisijungta per HTTPS (kitaip `url()` generuos
  `http://`, o `SESSION_SECURE_COOKIE` slapukai nebus siunčiami). `bootstrap/app.php`:
  `$middleware->trustProxies(at: ['10.0.0.0/8']);` (balansuotojo adresai; `'*'` – tik jei serveris nepasiekiamas tiesiogiai).
  → https://laravel.com/docs/13.x/requests#configuring-trusted-proxies
- **CSP** (`config/security.php`): pirmą savaitę `CSP_REPORT_ONLY=true` + `CSP_REPORT_URI` (pvz. Sentry) – matysim
  pažeidimus nieko neblokuodami; paskui `false`. Pridėjus išorinį servisą (CDN, Stripe, Google Analytics), jo domeną
  įrašyti `CSP_EXTRA_*`.
- **Failai:** vieši tik avatarai, logotipai, portfolio (`public` diskas). Žinučių priedai ir skundų įrodymai (Etapas 6)
  turi būti **privačiame** diske (`local`) ir atiduodami per controller'į su Policy patikra (kaip BDAR archyvai).
- **Teisės:** kodas priklauso `deploy` vartotojui, `www-data` gali rašyti tik į `storage/` ir `bootstrap/cache/`.
- **Paslaptys** – tik `.env` (ne Git); atsarginės kopijos šifruojamos (12 sk.).
- **Administratoriai:** stiprūs slaptažodžiai; ateityje – Filament dviejų faktorių autentifikacija (MFA).
- **Priklausomybės:** `composer audit` ir `npm audit` CI'e arba kas savaitę; saugumo atnaujinimai – iš karto.
- **Autorizacija:** `tests/Feature/Security/RouteAuthorizationTest.php` nepraleis naujo maršruto be `auth`.

## 11. Logai, klaidų stebėsena, sveikatos patikra

**Logai** (`config/logging.php`): produkcijoje `LOG_CHANNEL=stack`, `LOG_STACK=daily,slack`:

- `daily` – `storage/logs/laravel-2026-10-03.log`, kas dieną naujas failas, laikoma `LOG_DAILY_DAYS=14` dienų
  (seni ištrinami automatiškai – logrotate nereikia);
- `slack` – tik `LOG_SLACK_LEVEL=critical` ir aukštesni įrašai į Slack kanalą (`LOG_SLACK_WEBHOOK_URL`), kad
  pranešimai būtų reti ir svarbūs;
- `LOG_LEVEL=info` (ne `debug`): debug lygis produkcijoje – per daug įrašų ir gali patekti asmens duomenų;
- tiesioginė peržiūra: `tail -f storage/logs/laravel-*.log` (dev'e – `php artisan pail`; paketas `laravel/pail` yra
  `require-dev`, todėl po `composer install --no-dev` produkcijoje jo nėra).

**Klaidų stebėsena** (rekomenduojama, kai bus tikrų vartotojų):

| Įrankis                | Kas tai                                                                          | Kaip įjungti                                                                                                                |
| ---------------------- | -------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| **Sentry**             | klaidos su kontekstu (vartotojas, užklausa, stack trace), grupavimas, pranešimai | `composer require sentry/sentry-laravel`, `SENTRY_LARAVEL_DSN=…`, `bootstrap/app.php` → `Integration::handles($exceptions)` |
| **Laravel Nightwatch** | oficiali Laravel stebėsena: lėtos užklausos, job'ai, klaidos                     | `composer require laravel/nightwatch`, agentas serveryje                                                                    |

Šiame etape paketas **neįdiegtas**: jam reikia paskyros ir DSN, o dev'e užtenka logų. Prieš paleidžiant – Sentry
(nemokamas planas mažam srautui). Asmens duomenys į Sentry siunčiami tik tiek, kiek reikia (`send_default_pii=false`).

**Sveikatos patikra:** `GET /up` – Laravel maršrutas; Etapas 8 papildė jį DB ir cache patikra (`CheckApplicationHealth`).
Jei kuri neveikia – 500. Jį naudoja:

- apkrovos balansuotojas (neveikiantį serverį išima iš rotacijos);
- išorinė stebėsena (UptimeRobot, Better Stack) – SMS / el. laiškas, kai svetainė nepasiekiama.

**Eilių stebėsena:** užsikimšusi eilė svetainės „nenumuša", todėl ji tikrinama atskirai. `routes/console.php`
galima pridėti `Schedule::command('queue:monitor redis:default --max=500')->everyFiveMinutes();` – viršijus ribą
Laravel paleidžia įvykį `QueueBusy` (jo listener'is gali siųsti pranešimą į Slack). Nepavykę job'ai:
`php artisan queue:failed`.

## 12. Atsarginės kopijos

**Ką kopijuoti:** (1) MySQL duomenų bazę, (2) `storage/app/public` (nuotraukos) ir `storage/app/private`
(BDAR archyvus galima praleisti – jie laikini), (3) `.env` (atskirai, saugiai – slaptažodžių tvarkyklėje).
Kodo kopijuoti nereikia – jis Git'e.

**Tikslai:** RPO (kiek duomenų galim prarasti) ≤ 24 val., RTO (per kiek atstatom) ≤ 2 val. Mažesniam RPO – valdomos
MySQL paslaugos „point-in-time recovery" (binlog) – iki kelių minučių.

**Scenarijus** (`/usr/local/bin/paslaugos-backup.sh`, cron kasdien 02:30 vartotojui, kuris gali skaityti `storage/`):

```bash
#!/usr/bin/env bash
set -euo pipefail
STAMP=$(date +%Y%m%d-%H%M)
DIR=/var/backups/paslaugos
mkdir -p "$DIR"

# --single-transaction: nuoseklus vaizdas be lentelių užrakinimo (InnoDB), svetainė toliau veikia
mysqldump --defaults-extra-file=/etc/paslaugos/mysql-backup.cnf --single-transaction --quick \
  --routines --triggers paslaugos | zstd -q -T0 > "$DIR/db-$STAMP.sql.zst"

# Nuotraukos – tik pasikeitę failai (restic: šifruotos, deduplikuotos kopijos su istorija)
restic -r s3:https://s3.example-storage.eu/paslaugos-backup backup /var/www/paslaugos/shared/storage/app/public "$DIR/db-$STAMP.sql.zst"

# Saugojimas: 7 dienos, 4 savaitės, 6 mėnesiai
restic -r s3:https://s3.example-storage.eu/paslaugos-backup forget --keep-daily 7 --keep-weekly 4 --keep-monthly 6 --prune
find "$DIR" -name 'db-*.sql.zst' -mtime +2 -delete
```

- Kopijos laikomos **kitur** nei serveris (kitas tiekėjas ar regionas) ir **šifruojamos** (restic – automatiškai).
- **Atkūrimo bandymas kas mėnesį** į atskirą DB: kopija, kurios niekada nebandyta atkurti, nėra kopija.
  `zstd -dc db-….sql.zst | mysql paslaugos_restore_test` ir keli `SELECT COUNT(*)`.
- BDAR: ištrintų (anonimizuotų) vartotojų duomenys išlieka senose kopijose, kol šios pasensta (≤ 6 mėn.). Atkūrus
  kopiją, anonimizavimą reikia pakartoti (ištrynimų žurnalas – `users.deleted_at`).

**Sprendimas dėl `spatie/laravel-backup`:** kol kas **neįdiegtas**. Jis daro tą patį (DB dump + failai į ZIP, siuntimas
į bet kurį Laravel diską, senų kopijų valymas, `backup:monitor` – pranešimas, jei kopija per sena) ir valdomas iš
Laravel (`php artisan backup:run`). Verta, kai norisi visko viename projekte (be serverio scenarijų) ir pranešimų apie
nepavykusias kopijas. Mūsų atveju: valdoma DB paslauga dažniausiai daro savo kopijas, o failams restic efektyvesnis
(inkrementinis, nekuria vis naujo didelio ZIP). Jei serveris bus valdomas rankiniu būdu be valdomos DB – įdiegti
`spatie/laravel-backup` ir Scheduler'yje paleisti `backup:clean`, `backup:run`, `backup:monitor`.
→ https://spatie.be/docs/laravel-backup

## 13. CI/CD su GitHub Actions

**CI** (`.github/workflows/tests.yml`, jau veikia): kiekvienam push į `main` ir pull request – frontend lint ir tipai,
Pint, PHPStan, Pest (SQLite) ir atskiras darbas su **MySQL 8.4** (Etapas 8).

**CD** – pasiūlymas (dar neįjungtas: reikia serverio ir paslapčių). Deploy vyksta tik kai CI praėjo, o produkcijos
„environment" reikalauja rankinio patvirtinimo:

```yaml
# .github/workflows/deploy.yml
name: deploy
on:
    workflow_run:
        workflows: [tests]
        types: [completed]
        branches: [main]

jobs:
    deploy:
        if: ${{ github.event.workflow_run.conclusion == 'success' }}
        runs-on: ubuntu-latest
        environment: production # Settings → Environments: „Required reviewers"
        steps:
            - uses: actions/checkout@v4
            - uses: shivammathur/setup-php@v2
              with: { php-version: '8.4' }
            - uses: actions/setup-node@v4
              with: { node-version: '22' }
            - run: composer install --no-dev --optimize-autoloader --no-interaction
            - run: npm ci && npm run build # build CI'e – serveryje Node nereikia
            - name: Įkelti release ir perjungti
              env:
                  SSH_KEY: ${{ secrets.DEPLOY_SSH_KEY }}
                  HOST: ${{ secrets.DEPLOY_HOST }}
              run: |
                  # releases + symlink (5 sk.): rsync į naują releases/… katalogą, tada per ssh:
                  # migrate --force, optimize, filament:optimize, ln -sfn, queue:restart, php-fpm reload
                  ./scripts/deploy.sh "$HOST"
```

Paprastesnė alternatyva – Laravel Forge / Ploi / Envoyer „deploy hook" URL: GitHub Actions po sėkmingo CI tik iškviečia
`curl -X POST $FORGE_DEPLOY_URL`, o serveris pats daro 4–5 skyriaus žingsnius.

## 14. Patikra po diegimo

```bash
curl -fsS https://www.example.lt/up                         # 200
curl -sI https://www.example.lt/ | grep -iE 'strict-transport|content-security|x-frame'
curl -s https://www.example.lt/robots.txt                   # Disallow sąrašas + Sitemap
curl -s https://www.example.lt/sitemap.xml | head           # sitemapindex
php artisan about                                           # production, debug off, cache: CACHED
php artisan schedule:list                                   # užduotys ir kitas paleidimas
sudo supervisorctl status                                   # darbuotojai RUNNING
php artisan queue:failed                                    # tuščias
```

Rankiniu būdu: prisijungimas, registracija (ar atėjo laiškas), užklausos sukūrimas, pasiūlymas, `/admin`, naršyklės
konsolėje – jokių CSP klaidų. Inertia SSR neįjungtas (`bootstrap/ssr` nėra – Inertia SSR serverio nekviečia):
SEO žymos ir JSON-LD jau pirmame HTML (`app.blade.php`), todėl SSR – tik vėlesnis greičio patobulinimas.
