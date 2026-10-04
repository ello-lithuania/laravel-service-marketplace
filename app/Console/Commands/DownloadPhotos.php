<?php

namespace App\Console\Commands;

use App\Actions\Photos\AttachLibraryPhoto;
use App\Enums\SitePhotoKey;
use App\Models\Category;
use App\Models\SitePhoto;
use App\Services\Photos\PhotoFetcher;
use App\Services\Photos\PhotoQueries;
use App\Services\Photos\PhotoSources;
use App\Services\Photos\PhotoSpec;
use App\Services\Photos\StockPhotoLibrary;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Spatie\MediaLibrary\HasMedia;
use Throwable;

/**
 * Nemokamos nuotraukos svetainei (Etapas 10): kategorijoms (1 ir 2 lygis), svetainės dizaino vietoms (SitePhotoKey)
 * ir demo portfolio rinkiniui (MediaGenerator, SEED_MEDIA=true).
 *
 * Kiekvienai vietai: jau turi nuotrauką – praleidžiam; yra vietinėje bibliotekoje – prisegam iš jos (be interneto);
 * kitaip – ieškom (Pexels → Openverse), atsisiunčiam, tikrinam, įrašom į biblioteką ir prisegam su autoriumi.
 * Viena nesėkmė komandos nesustabdo; išėjimo kodas 1 – tik jei nepavyko niekas.
 *
 *     php artisan photos:download                         # viskas, šaltinis parenkamas automatiškai
 *     php artisan photos:download --only=site --force     # svetainės nuotraukas pakeisti kitomis
 *     php artisan photos:download --source=openverse --only=portfolio --per-category=5
 */
#[Signature('photos:download
    {--source=auto : Šaltinis: auto (Pexels, jei yra PEXELS_API_KEY, kitaip Openverse), pexels arba openverse}
    {--only=categories,site,portfolio : Ką atsisiųsti (kableliais): categories, site, portfolio}
    {--force : Pakeisti jau atsisiųstas nuotraukas kitomis (administratoriaus įkeltų nekeičia)}
    {--per-category=8 : Kiek demo portfolio nuotraukų kiekvienai 1 lygio sričiai (1–40)}')]
#[Description('Atsisiunčia nemokamas nuotraukas (Pexels / Openverse) kategorijoms, svetainės dizainui ir demo portfolio')]
class DownloadPhotos extends Command
{
    private const MAX_PER_CATEGORY = 40;

    /** Kiek problemų parodyti prie nepavykusio įrašo (visos – su -v). */
    private const PROBLEMS_SHOWN = 3;

    private PhotoFetcher $fetcher;

    private StockPhotoLibrary $library;

    private AttachLibraryPhoto $attach;

    private PhotoQueries $queries;

    /** @var array<string, true> jau naudojamų nuotraukų stock_id (biblioteka + šis paleidimas) */
    private array $used = [];

    /** @var array{downloaded: int, restored: int, skipped: int, failed: int} */
    private array $stats = ['downloaded' => 0, 'restored' => 0, 'skipped' => 0, 'failed' => 0];

    private bool $sslHintShown = false;

    public function handle(PhotoSources $sources, StockPhotoLibrary $library, AttachLibraryPhoto $attach, PhotoQueries $queries): int
    {
        $only = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('only')))));
        $unknown = array_diff($only, PhotoSpec::TARGETS);
        $perCategory = (int) $this->option('per-category');

        if ($only === [] || $unknown !== []) {
            $this->components->error('Netinkamas --only: '.implode(', ', $unknown ?: ['(tuščia)']).'. Galima: '.implode(', ', PhotoSpec::TARGETS));

            return self::FAILURE;
        }

        if ($perCategory < 1 || $perCategory > self::MAX_PER_CATEGORY) {
            $this->components->error('--per-category turi būti nuo 1 iki '.self::MAX_PER_CATEGORY);

            return self::FAILURE;
        }

        try {
            $providers = $sources->providers((string) $this->option('source'));
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->fetcher = new PhotoFetcher($providers, $sources->downloader());
        $this->library = $library;
        $this->attach = $attach;
        $this->queries = $queries;
        $this->used = $library->stockIds();

        $labels = array_map(fn ($provider) => $provider->label(), $providers);
        $this->components->info('Nuotraukų šaltiniai: '.implode(' → ', $labels).'. Biblioteka: '.$library->path());

        if (in_array('categories', $only, true)) {
            $this->categories();
        }

        if (in_array('site', $only, true)) {
            $this->sitePhotos();
        }

        if (in_array('portfolio', $only, true)) {
            $this->portfolio($perCategory);
        }

        return $this->summary();
    }

    // --- Kategorijos ir svetainės nuotraukos ---------------------------------------------------------

    private function categories(): void
    {
        // media – iš karto (eager loading): kitaip kiekviena kategorija darytų atskirą užklausą (preventLazyLoading)
        $categories = Category::query()
            ->active()
            ->where('depth', '<=', 2)
            ->with('media')
            ->orderBy('depth')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $this->newLine();
        $this->components->twoColumnDetail('<fg=cyan;options=bold>Kategorijos</>', (string) $categories->count());

        foreach ($categories as $category) {
            $this->single(
                model: $category,
                collection: 'image',
                directory: StockPhotoLibrary::CATEGORIES,
                name: $category->slug,
                label: ($category->depth > 1 ? '  ' : '').$category->name,
                queries: $this->queries->category($category->slug),
                spec: PhotoSpec::for('categories'),
            );
        }
    }

    private function sitePhotos(): void
    {
        $this->newLine();
        $this->components->twoColumnDetail('<fg=cyan;options=bold>Svetainės nuotraukos</>', (string) count(SitePhotoKey::cases()));

        foreach (SitePhotoKey::cases() as $key) {
            $sitePhoto = SitePhoto::forKey($key, $this->queries->siteAlt($key));
            $sitePhoto->load('media');

            $this->single(
                model: $sitePhoto,
                collection: 'photo',
                directory: StockPhotoLibrary::SITE,
                name: $key->value,
                label: $key->label(),
                queries: $this->queries->site($key),
                spec: PhotoSpec::for('site'),
            );
        }
    }

    /**
     * Viena vieta (kategorija ar svetainės nuotrauka): praleisti, prisegti iš bibliotekos arba atsisiųsti.
     *
     * @param  list<string>  $queries
     */
    private function single(HasMedia&Model $model, string $collection, string $directory, string $name, string $label, array $queries, PhotoSpec $spec): void
    {
        $current = $model->getFirstMedia($collection);
        $force = (bool) $this->option('force');

        if ($current !== null) {
            // Be stock_id – administratoriaus įkelta nuotrauka: jos niekada nekeičiam
            if (! $current->hasCustomProperty('stock_id')) {
                $this->skip($label, 'sava nuotrauka – nekeičiama');

                return;
            }

            if (! $force) {
                $this->skip($label, 'jau yra');

                return;
            }
        } elseif (! $force && ($stored = $this->library->find($directory, $name)) !== null) {
            $this->attachOrFail($label, fn () => $this->attach->handle($model, $collection, $stored), 'restored', 'iš bibliotekos');

            return;
        }

        if ($queries === []) {
            $this->skip($label, 'nėra paieškos frazės (database/data/photo_queries.php)');

            return;
        }

        if ($this->fetcher->exhausted()) {
            $this->failed($label, 'praleista – šaltiniai nepasiekiami');

            return;
        }

        $fetched = $this->fetcher->first($queries, $spec, $this->used);

        if ($fetched === null) {
            $this->failed($label, 'nerasta tinkamos nuotraukos', $this->fetcher->problems());

            return;
        }

        try {
            $photo = $this->library->store($directory, $name, $fetched, $spec->maxDimension);
        } catch (Throwable $e) {
            // Pvz. GD nesugeba perskaityti neįprasto JPEG ar diske nėra vietos – kitos vietos vis tiek bandomos
            $this->failed($label, 'nepavyko įrašyti: '.mb_strimwidth($e->getMessage(), 0, 120, '…'));

            return;
        }

        $this->used[$photo->stockId] = true;

        $this->attachOrFail($label, fn () => $this->attach->handle($model, $collection, $photo), 'downloaded', $this->creditLine($photo->credit->source, $photo->credit->author));
    }

    /**
     * @param  callable(): mixed  $attach
     * @param  'downloaded'|'restored'  $stat
     */
    private function attachOrFail(string $label, callable $attach, string $stat, string $detail): void
    {
        try {
            $attach();
        } catch (Throwable $e) {
            // Pvz. GD neužtenka atminties miniatiūrai – kitos vietos vis tiek bandomos
            $this->failed($label, 'nepavyko prisegti: '.mb_strimwidth($e->getMessage(), 0, 120, '…'));

            return;
        }

        $this->stats[$stat]++;
        $color = $stat === 'downloaded' ? 'green' : 'blue';
        $this->components->twoColumnDetail($label, "<fg={$color}>{$detail}</>");
    }

    // --- Demo portfolio rinkinys ----------------------------------------------------------------------

    /**
     * storage/app/stock-photos/portfolio/{sritis}/ – po $perCategory nuotraukų kiekvienai 1 lygio sričiai.
     * Jų MediaGenerator ima demo portfolio darbams ir viršeliams (SEED_MEDIA=true).
     */
    private function portfolio(int $perCategory): void
    {
        $roots = Category::query()->roots()->active()->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug']);
        $spec = PhotoSpec::for('portfolio');

        $this->newLine();
        $this->components->twoColumnDetail('<fg=cyan;options=bold>Demo portfolio rinkinys</>', "{$roots->count()} sr. × {$perCategory}");

        foreach ($roots as $root) {
            $directory = StockPhotoLibrary::poolDirectory($root->slug);

            if ($this->option('force')) {
                $this->library->clear($directory);
            }

            $existing = $this->library->all($directory);
            $missing = $perCategory - count($existing);

            if ($missing <= 0) {
                $this->skip($root->name, 'jau yra '.count($existing));

                continue;
            }

            $queries = $this->queries->portfolio($root->slug);

            if ($queries === []) {
                $this->skip($root->name, 'nėra paieškos frazių (database/data/photo_queries.php)');

                continue;
            }

            if ($this->fetcher->exhausted()) {
                $this->failed($root->name, 'praleista – šaltiniai nepasiekiami');

                continue;
            }

            $added = 0;
            $index = 1;
            $storeProblems = [];

            foreach ($this->fetcher->photos($queries, $spec, $this->used) as $fetched) {
                while (isset($existing[$name = sprintf('%s-%02d', $root->slug, $index)])) {
                    $index++;
                }

                try {
                    $existing[$name] = $this->library->store($directory, $name, $fetched, $spec->maxDimension);
                } catch (Throwable $e) {
                    // Vienas sugadintas failas rinkinio nesustabdo – imamas kitas rezultatas
                    $storeProblems[] = $fetched->stock->stockId().': nepavyko įrašyti – '.mb_strimwidth($e->getMessage(), 0, 120, '…');

                    continue;
                }

                $this->used[$fetched->stock->stockId()] = true;

                if (++$added >= $missing) {
                    break;
                }
            }

            $this->stats['downloaded'] += $added;
            $have = count($existing);

            if ($added < $missing) {
                $this->failed($root->name, "{$have}/{$perCategory} – trūksta ".($perCategory - $have), [...$this->fetcher->problems(), ...$storeProblems]);

                continue;
            }

            $this->components->twoColumnDetail($root->name, "<fg=green>{$have}/{$perCategory} (+{$added})</>");
        }
    }

    // --- Išvestis ---------------------------------------------------------------------------------------

    private function skip(string $label, string $reason): void
    {
        $this->stats['skipped']++;
        $this->components->twoColumnDetail($label, "<fg=gray>{$reason}</>");
    }

    /**
     * @param  list<string>  $problems
     */
    private function failed(string $label, string $reason, array $problems = []): void
    {
        $this->stats['failed']++;
        $this->components->twoColumnDetail($label, "<fg=red>{$reason}</>");

        $shown = $this->output->isVerbose() ? $problems : array_slice($problems, 0, self::PROBLEMS_SHOWN);

        foreach ($shown as $problem) {
            $this->line("    <fg=gray>– {$problem}</>");
        }

        if (count($problems) > count($shown)) {
            $this->line('    <fg=gray>– … ir dar '.(count($problems) - count($shown)).' (visos: -v)</>');
        }

        $this->sslHint($problems);
    }

    private function creditLine(string $source, ?string $author): string
    {
        return $source.($author !== null ? ' · '.$author : '');
    }

    /**
     * Dažniausia Windows klaida: PHP neturi sertifikatų sąrašo (cURL error 60) – parodom, kaip pataisyti.
     *
     * @param  list<string>  $problems
     */
    private function sslHint(array $problems): void
    {
        if ($this->sslHintShown || ! preg_grep('/cURL error (60|77)/', $problems)) {
            return;
        }

        $this->sslHintShown = true;
        $this->components->warn('PHP negali patikrinti HTTPS sertifikato (cURL error 60). Atsisiųsk https://curl.se/ca/cacert.pem '
            .'ir php.ini nurodyk: curl.cainfo="C:\\php\\cacert.pem" ir openssl.cafile="C:\\php\\cacert.pem" (kelias – tavo).');
    }

    private function summary(): int
    {
        ['downloaded' => $downloaded, 'restored' => $restored, 'skipped' => $skipped, 'failed' => $failed] = $this->stats;

        $this->newLine();

        foreach ($this->fetcher->unavailable() as $reason) {
            $this->components->warn($reason);
        }

        $line = "Atsisiųsta: {$downloaded} · iš bibliotekos: {$restored} · praleista: {$skipped} · nepavyko: {$failed}";

        // Klaida – tik jei buvo ką daryti ir nepavyko nieko
        if ($failed > 0 && $downloaded === 0 && $restored === 0) {
            $this->components->error($line);

            return self::FAILURE;
        }

        $failed > 0 ? $this->components->warn($line) : $this->components->info($line);

        if ($downloaded + $restored > 0) {
            $this->line('  Autoriai ir licencijos: '.url('nuotrauku-autoriai'));
        }

        return self::SUCCESS;
    }
}
