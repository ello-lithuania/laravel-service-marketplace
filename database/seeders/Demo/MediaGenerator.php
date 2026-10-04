<?php

namespace Database\Seeders\Demo;

use App\Enums\ProviderStatus;
use App\Models\PortfolioItem;
use App\Models\ProviderProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Demo paveikslėliai (SEED_MEDIA=true, docs/SEEDING.md 7 sk.): logotipai, viršeliai ir portfolio nuotraukos
 * aktyviems teikėjams. Paveikslėliai abstraktūs ir sugeneruojami vietoje (ImagePainter), prisegami per
 * medialibrary – lygiai taip, kaip teikėjo įkelti failai (media eilutė, failas diske, miniatiūros).
 *
 * Trys fazės:
 * 1. Planas: kam ir koks paveikslėlis (vienas atsitiktinumo srautas, todėl planas atkartojamas).
 * 2. Piešimas: tik reikalingi paveikslėliai, kiekvienas su savo sėkla (turinys nepriklauso nuo SEED_SCALE).
 *    Portfolio ir viršeliai – nedidelis bendras rinkinys (≤ 72 failai), logotipai – pagal inicialus.
 * 3. Prisegimas: addMedia()->preservingOriginal() – tas pats failas kopijuojamas daug kartų, o ne piešiamas iš naujo.
 */
final class MediaGenerator
{
    /** Kiekiai, kai SEED_SCALE = 1 (mažesniam masteliui – proporcingai, bet ne mažiau kaip MIN). */
    private const PROFILES = 500;

    private const PORTFOLIO_ITEMS = 1_000;

    private const MIN = 3;

    /** Kokia dalis profilių su logotipu turi ir viršelį. */
    private const COVER_SHARE = 0.4;

    /**
     * Portfolio paveikslėlių variantų kiekvienai 1 lygio kategorijai (12 × 5 = 60) ir viršelių (12 × 1).
     * Su 4 variantais gretimi tos pačios srities darbai per dažnai atrodė vienodai.
     */
    private const PORTFOLIO_VARIANTS = 5;

    private const LOGO_SHAPES = 5;

    /**
     * Spalvų paletės pagal 1 lygio kategoriją: [tamsi, pagrindinė, šviesi, akcentas].
     * Kategorijai be paletės (pvz. pridėtai vėliau) parenkama viena iš šių pagal slug'o maišos reikšmę.
     */
    private const PALETTES = [
        'statyba-ir-remontas' => ['#7c2d12', '#ea580c', '#fed7aa', '#facc15'],
        'santechnika-ir-sildymas' => ['#0c4a6e', '#0284c7', '#bae6fd', '#f97316'],
        'elektra-ir-apsvietimas' => ['#1e1b4b', '#4338ca', '#c7d2fe', '#fde047'],
        'namu-ukis-ir-valymas' => ['#134e4a', '#0d9488', '#ccfbf1', '#f0abfc'],
        'kraustymas-ir-transportas' => ['#1f2937', '#475569', '#e2e8f0', '#f59e0b'],
        'aplinka-ir-sodas' => ['#14532d', '#16a34a', '#dcfce7', '#a3e635'],
        'baldai-ir-interjeras' => ['#451a03', '#a16207', '#fef3c7', '#0f766e'],
        'automobiliu-paslaugos' => ['#18181b', '#b91c1c', '#e4e4e7', '#f87171'],
        'grozis-ir-sveikata' => ['#831843', '#db2777', '#fce7f3', '#c084fc'],
        'it-ir-kompiuteriai' => ['#2e1065', '#7c3aed', '#ede9fe', '#22d3ee'],
        'renginiai-ir-sventes' => ['#701a75', '#e11d48', '#ffe4e6', '#fbbf24'],
        'mokymai-ir-korepetitoriai' => ['#172554', '#2563eb', '#dbeafe', '#34d399'],
    ];

    /**
     * Ką piešti: failo raktas => [tipas, 1 lygio slug, logotipo inicialai, logotipo forma].
     *
     * @var array<string, array{0: 'portfolio'|'cover'|'logo', 1: string, 2: string, 3: int}>
     */
    private array $images = [];

    /**
     * Ką prisegti (morph map vardas => modelio id => prisegimai).
     *
     * @var array<string, array<int, list<array{collection: string, image: string, uuid: string, order: int}>>>
     */
    private array $attachments = [];

    public function __construct(private readonly DemoContext $ctx) {}

    public function run(): void
    {
        $started = microtime(true);
        $seed = (int) config('seeding.faker_seed');
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'seed-media-'.$seed.'-'.getmypid();
        $queueConnection = config('media-library.queue_connection_name');

        // Sėkla iš naujo: planas nepriklauso nuo to, kiek atsitiktinių skaičių sunaudojo ankstesni žingsniai
        mt_srand($seed);
        $this->plan();
        $this->clearOrphanedFiles();

        try {
            $paths = $this->paint($directory, $seed);
            // Miniatiūros seed'o metu daromos iškart (sync), net jei QUEUE_CONNECTION=database (žr. docs/SEEDING.md 7 sk.)
            config(['media-library.queue_connection_name' => 'sync']);
            $total = $this->attach($paths);
        } finally {
            config(['media-library.queue_connection_name' => $queueConnection]);
            File::deleteDirectory($directory);
        }

        $this->ctx->log(sprintf('  %-28s %10s  %5.1f s', 'media', number_format($total, 0, ',', ' '), microtime(true) - $started));
    }

    // --- 1. Planas -----------------------------------------------------------------------------

    /**
     * Einama per aktyvius teikėjus „populiarumo" tvarka: pirmiems PROFILES – logotipas (40 % – ir viršelis),
     * jų portfolio darbams – nuotraukos, kol surenkama PORTFOLIO_ITEMS darbų.
     */
    private function plan(): void
    {
        $c = $this->ctx;
        $profileTarget = max(self::MIN, (int) round(self::PROFILES * $c->scale));
        $itemTarget = max(self::MIN, (int) round(self::PORTFOLIO_ITEMS * $c->scale));

        $items = [];

        foreach (DB::table('portfolio_items')->orderBy('id')->get(['id', 'provider_profile_id', 'category_id']) as $item) {
            $items[(int) $item->provider_profile_id][] = [(int) $item->id, (int) $item->category_id];
        }

        $profiles = 0;
        $portfolio = 0;

        foreach ($this->providersByPopularity() as $profileId) {
            if ($profiles >= $profileTarget && $portfolio >= $itemTarget) {
                break;
            }

            $p = $profileId - 1;
            $root = $this->rootSlug($c->provLeaves[$p][0] ?? 0);

            if ($profiles < $profileTarget) {
                $profiles++;
                $this->planLogo($profileId, $root, $this->initials($c->provDisplayName[$p] ?? ''));

                if ($c->chance(self::COVER_SHARE)) {
                    $this->add('provider_profile', $profileId, 'cover', $this->image('cover', $root));
                }
            }

            foreach ($items[$profileId] ?? [] as [$itemId, $categoryId]) {
                if ($portfolio >= $itemTarget) {
                    break;
                }

                $portfolio++;
                $itemRoot = $this->rootSlug($categoryId);
                $variants = range(0, self::PORTFOLIO_VARIANTS - 1);
                shuffle($variants);

                foreach (array_slice($variants, 0, $c->between(1, 3)) as $variant) {
                    $this->add('portfolio_item', $itemId, 'images', $this->image('portfolio', $itemRoot, $variant));
                }
            }
        }
    }

    /**
     * Aktyvūs teikėjai „populiarumo" tvarka: kuo daugiau atsiliepimų, tuo didesnė tikimybė būti priekyje
     * (ir turėti logotipą – kaip tikrovėje: veiklūs teikėjai labiau rūpinasi profiliu). Svertinis atsitiktinis
     * rikiavimas be pasikartojimų (Efraimidis–Spirakis): raktas = ln(u) / svoris, rikiuojama mažėjančiai.
     * Svoris (1 + atsiliepimai)² – kad katalogo pirmame puslapyje (populiariausi) paveikslėlių būtų matyti.
     *
     * @return list<int> provider_profiles.id
     */
    private function providersByPopularity(): array
    {
        $keys = [];

        $rows = DB::table('provider_profiles')
            ->where('status', ProviderStatus::Active->value)
            ->orderBy('id')
            ->pluck('reviews_count', 'id');

        foreach ($rows as $id => $reviews) {
            $keys[(int) $id] = log(max($this->ctx->rand01(), 1e-12)) / (1 + (int) $reviews) ** 2;
        }

        arsort($keys);

        return array_keys($keys);
    }

    /**
     * 1 lygio kategorijos slug'as pagal 3 lygio kategoriją (nuo jo priklauso paletė).
     */
    private function rootSlug(int $leafId): string
    {
        $c = $this->ctx;

        return $c->rootSlug[$c->leafRoot[$leafId] ?? $c->rootIds[0]];
    }

    private function planLogo(int $profileId, string $root, string $initials): void
    {
        $shape = mt_rand(0, self::LOGO_SHAPES - 1);
        // Tas pats inicialų, paletės ir formos derinys – tas pats failas (piešiamas vieną kartą)
        $key = 'logo-'.$root.'-'.$shape.'-'.Str::slug($initials);
        $this->images[$key] ??= ['logo', $root, $initials, $shape];
        $this->add('provider_profile', $profileId, 'logo', $key);
    }

    private function image(string $type, string $root, int $variant = 0): string
    {
        /** @var 'portfolio'|'cover' $type */
        $key = $type.'-'.$root.'-'.$variant;
        $this->images[$key] ??= [$type, $root, '', 0];

        return $key;
    }

    private function add(string $morph, int $id, string $collection, string $image): void
    {
        $list = $this->attachments[$morph][$id] ?? [];

        // UUID ir eilės numerį medialibrary priskiria modelio įvykyje „creating", bet DatabaseSeeder įvykius
        // išjungia (WithoutModelEvents) – todėl nurodom patys. UUID deterministinis (Str::uuid() „užsėti" negalima)
        $list[] = [
            'collection' => $collection,
            'image' => $image,
            'uuid' => $this->ctx->uuid(),
            'order' => count(array_filter($list, fn (array $item) => $item['collection'] === $collection)) + 1,
        ];

        $this->attachments[$morph][$id] = $list;
    }

    /**
     * Inicialai kaip AvatarFallback'e (pirmo ir paskutinio žodžio pirmos raidės), bet be teisinės formos ir
     * jungtuko: „UAB Urbonas ir Sakalauskas" → „US", „MB "Kavaliauskas"" → „K", „Jonas Petraitis" → „JP".
     */
    private function initials(string $name): string
    {
        $name = str_replace(['"', '„', '“'], '', $name);
        $words = array_values(array_filter(
            preg_split('/\s+/u', trim($name)) ?: [],
            fn (string $word) => ! in_array(mb_strtoupper($word), ['UAB', 'AB', 'MB', 'IĮ', 'VŠĮ', 'ŽŪB', 'KB', 'IR', ''], true),
        ));

        if ($words === []) {
            return '?';
        }

        $first = mb_substr($words[0], 0, 1);
        $last = count($words) > 1 ? mb_substr($words[count($words) - 1], 0, 1) : '';

        return mb_strtoupper($first.$last);
    }

    // --- 2. Piešimas -----------------------------------------------------------------------------

    /**
     * @return array<string, string> failo raktas => kelias laikiname kataloge
     */
    private function paint(string $directory, int $seed): array
    {
        $painter = new ImagePainter($directory);
        $paths = [];

        foreach ($this->images as $key => [$type, $root, $initials, $shape]) {
            // Kiekvienas paveikslėlis – su savo sėkla: jo turinys priklauso tik nuo SEED_FAKER_SEED ir rakto
            mt_srand(crc32($seed.':'.$key));
            $palette = $this->palette($root);

            $paths[$key] = match ($type) {
                'portfolio' => $painter->portfolio($palette, $key),
                'cover' => $painter->cover($palette, $key),
                'logo' => $painter->logo($initials, $palette, $shape, $key),
            };
        }

        return $paths;
    }

    /**
     * @return list<string>
     */
    private function palette(string $rootSlug): array
    {
        $palettes = array_values(self::PALETTES);

        return self::PALETTES[$rootSlug] ?? $palettes[crc32($rootSlug) % count($palettes)];
    }

    // --- 3. Prisegimas -----------------------------------------------------------------------------

    /**
     * @param  array<string, string>  $paths
     */
    private function attach(array $paths): int
    {
        $total = 0;
        // Kiekvienai daliai – nauja užklausa (findMany() keičia builder'į, todėl jo pakartotinai naudoti negalima)
        $finders = [
            'provider_profile' => fn (array $ids) => ProviderProfile::query()->findMany($ids),
            'portfolio_item' => fn (array $ids) => PortfolioItem::query()->findMany($ids),
        ];

        foreach ($finders as $morph => $find) {
            $planned = $this->attachments[$morph] ?? [];

            foreach (array_chunk(array_keys($planned), 100) as $ids) {
                // Viena transakcija 100 modelių: SQLite be jos kiekvieną INSERT/UPDATE rašytų į diską atskirai
                DB::transaction(function () use ($find, $ids, $planned, $paths, &$total) {
                    foreach ($find($ids) as $model) {
                        foreach ($planned[$model->getKey()] as $attachment) {
                            $model->addMedia($paths[$attachment['image']])
                                // Originalas lieka laikiname kataloge – tą patį failą prisegsim ir kitiems įrašams
                                ->preservingOriginal()
                                ->setOrder($attachment['order'])
                                ->withAttributes(['uuid' => $attachment['uuid']])
                                ->toMediaCollection($attachment['collection']);
                            $total++;
                        }
                    }
                });
            }
        }

        return $total;
    }

    /**
     * Po migrate:fresh media lentelė tuščia, bet ankstesnio seed'o failai ({media id}/…) liko diske.
     * Jų nebenurodo jokia eilutė, todėl ištrinam – kitaip kiekvienas seed'as pridėtų dar kelis šimtus MB.
     */
    private function clearOrphanedFiles(): void
    {
        if (DB::table('media')->exists()) {
            return;
        }

        $disk = Storage::disk((string) config('media-library.disk_name'));

        foreach ($disk->directories() as $directory) {
            if (ctype_digit($directory)) {
                $disk->deleteDirectory($directory);
            }
        }
    }
}
