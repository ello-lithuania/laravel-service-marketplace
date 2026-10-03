<?php

namespace Database\Seeders\Demo;

use App\Models\Category;
use App\Models\City;
use App\Models\User;
use Closure;
use Database\Seeders\Support\ReferenceData;
use Faker\Factory as FakerFactory;
use Faker\Generator as Faker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Bendra demo generatorių būsena ir pagalbinės funkcijos (docs/SEEDING.md).
 *
 * Kad tilptų į atmintį, duomenys laikomi kaip skaičių masyvai („stulpeliai"),
 * o ne Eloquent objektai. ID priskiriami patys (DB tuščia po migrate:fresh):
 * teikėjo profilio id = p + 1, užklausos id = r + 1, pasiūlymo id = o + 1 ir t.t.
 */
final class DemoContext
{
    public const DAY = 86_400;

    public const HOUR = 3_600;

    public const MINUTE = 60;

    public readonly Faker $faker;

    public readonly int $now;

    /** Platformos „pradžia" – prieš 36 mėn. */
    public readonly int $start;

    public readonly string $passwordHash;

    public readonly int $chunk;

    /** @var array<string, int> tiksliniai kiekiai (padauginti iš scale) */
    public array $counts = [];

    // --- Žinyniniai duomenys -------------------------------------------------

    /** @var list<int> */
    public array $cityIds = [];

    /** @var array<int, int> city id => region id */
    public array $cityRegion = [];

    /** @var array<int, string> city id => vietininkas */
    public array $cityLocative = [];

    /** @var array<int, list<int>> region id => city ids */
    public array $regionCities = [];

    public WeightedPicker $cityPicker;

    /** @var array<int, string> visų kategorijų pavadinimai */
    public array $categoryName = [];

    /** @var array<int, list<int>> category id => vaikų id */
    public array $childrenOf = [];

    /** @var list<int> */
    public array $rootIds = [];

    /** @var array<int, float> */
    public array $categoryWeight = [];

    /** @var list<int> */
    public array $leafIds = [];

    /** @var array<int, int> leaf id => 1 lygio id */
    public array $leafRoot = [];

    /** @var array<int, int> leaf id => pasiūlymo kaina kreditais */
    public array $leafCost = [];

    /** @var array<int, string> 1 lygio id => slug */
    public array $rootSlug = [];

    /** @var array<int, array{0: int, 1: int}> 1 lygio id => biudžetas eurais */
    public array $rootBudget = [];

    /** @var list<int> */
    public array $adminIds = [];

    // --- Vartotojų ID ---------------------------------------------------------

    /** Kliento c user id = clientBase + c */
    public int $clientBase = 1;

    /** Teikėjo p user id = providerUserBase + p */
    public int $providerUserBase = 1;

    // --- Teikėjai (indeksas p) ------------------------------------------------

    /** @var array<int, string> ProviderStatus reikšmė */
    public array $provStatus = [];

    /** @var array<int, bool> */
    public array $provCompany = [];

    /** @var array<int, string> */
    public array $provDisplayName = [];

    /** @var array<int, int> */
    public array $provCity = [];

    /** @var array<int, bool> */
    public array $provWhole = [];

    /** @var array<int, list<int>> aptarnaujamos savivaldybės (be „visos Lietuvos") */
    public array $provZones = [];

    /** @var array<int, list<int>> pivot'e įrašytos kategorijos (3 lygio arba visa 2 lygio) */
    public array $provCategories = [];

    /** @var array<int, list<int>> efektyvūs 3 lygio lapai */
    public array $provLeaves = [];

    /** @var array<int, float> */
    public array $provActivity = [];

    /** @var array<int, int> */
    public array $provCreated = [];

    /** @var array<int, int> */
    public array $provLastActive = [];

    /** @var array<int, list<int>> leaf id => aktyvūs teikėjai */
    public array $leafProviders = [];

    /** @var array<int, array<int, true>> city id => [aktyvus teikėjas => true] */
    public array $cityProviders = [];

    /** @var array<string, WeightedPicker|null> „leaf:city" => kandidatai */
    private array $candidateCache = [];

    // --- Klientai (indeksas c) ------------------------------------------------

    /** @var array<int, int> */
    public array $clientCity = [];

    /** @var array<int, int> */
    public array $clientCreated = [];

    // --- Užklausos (indeksas r) -----------------------------------------------

    /** @var array<int, int> */
    public array $reqClient = [];

    /** @var array<int, int> */
    public array $reqLeaf = [];

    /** @var array<int, int> */
    public array $reqCity = [];

    /** @var array<int, string> ServiceRequestStatus reikšmė */
    public array $reqStatus = [];

    /** @var array<int, int> */
    public array $reqCreated = [];

    /** @var array<int, int> 0 = NULL */
    public array $reqPublished = [];

    /** @var array<int, int> */
    public array $reqExpires = [];

    /** @var array<int, int> */
    public array $reqCancelled = [];

    /** @var array<int, int> */
    public array $reqCompleted = [];

    /** @var array<int, int> priimto pasiūlymo indeksas o (-1 = nėra) */
    public array $reqAccepted = [];

    /** @var array<int, int> */
    public array $reqAcceptTime = [];

    /** @var array<int, int> pirmo pasiūlymo indeksas */
    public array $reqOfferStart = [];

    /** @var array<int, int> */
    public array $reqOfferCount = [];

    /** @var array<int, string> */
    public array $reqTitle = [];

    /** @var array<int, string> */
    public array $reqSlug = [];

    // --- Pasiūlymai (indeksas o) ----------------------------------------------

    /** @var array<int, int> */
    public array $offRequest = [];

    /** @var array<int, int> teikėjo indeksas p */
    public array $offProvider = [];

    /** @var array<int, int> */
    public array $offCreated = [];

    /** @var array<int, string> OfferStatus reikšmė */
    public array $offStatus = [];

    /** @var array<int, int> */
    public array $offViewed = [];

    /** @var array<int, int> */
    public array $offResponded = [];

    /** @var array<int, int> kreditų grąžinimo laikas (0 = negrąžinama) */
    public array $offRefund = [];

    // --- Pokalbiai ir žinutės (pokalbio id = i + 1, žinutės id = m + 1) -------

    /** @var array<int, int> pasiūlymo indeksas */
    public array $convOffer = [];

    /** @var array<int, int> */
    public array $convClientUser = [];

    /** @var array<int, int> */
    public array $convProviderUser = [];

    /** @var array<int, int> */
    public array $msgConv = [];

    /** @var array<int, int> */
    public array $msgSender = [];

    /** @var array<int, int> */
    public array $msgTime = [];

    // --- Atsiliepimai (id = i + 1) --------------------------------------------

    /** @var array<int, int> */
    public array $revProvider = [];

    /** @var array<int, int> */
    public array $revCreated = [];

    /** @var array<int, bool> */
    public array $revPublished = [];

    /** @var array<string, array<mixed>> */
    private array $texts = [];

    /**
     * @param  Closure(string): void  $log
     */
    public function __construct(public readonly float $scale, private readonly Closure $log)
    {
        $seed = (int) config('seeding.faker_seed');
        mt_srand($seed);
        $this->faker = FakerFactory::create('lt_LT');
        $this->faker->seed($seed);

        // Sveikos minutės – kad du paleidimai tą pačią minutę duotų lygiai tuos pačius duomenis
        $this->now = intdiv(now()->getTimestamp(), 60) * 60;
        $this->start = $this->now - 36 * 30 * self::DAY;
        // bcrypt lėtas (~70 ms), todėl hash'as skaičiuojamas vieną kartą visiems vartotojams
        $this->passwordHash = Hash::make('password');
        $this->chunk = max(1, (int) config('seeding.chunk'));

        /** @var array<string, int> $base */
        $base = config('seeding.counts');

        foreach ($base as $key => $count) {
            $this->counts[$key] = max(1, (int) round($count * $scale));
        }

        $this->loadReferenceData();
    }

    public function log(string $message): void
    {
        ($this->log)($message);
    }

    // --- Atsitiktinumas ---------------------------------------------------------

    public function rand01(): float
    {
        return mt_rand() / mt_getrandmax();
    }

    public function chance(float $probability): bool
    {
        return $this->rand01() < $probability;
    }

    public function between(int $min, int $max): int
    {
        return $max <= $min ? $min : mt_rand($min, $max);
    }

    /**
     * Skaičius [min, max], mažesni dažnesni (kuo didesnis $skew, tuo labiau).
     */
    public function skewed(int $min, int $max, float $skew = 2.0): int
    {
        return min($max, $min + (int) floor(($max - $min + 1) * ($this->rand01() ** $skew)));
    }

    /**
     * Laikas tarp $from ir $to, naujesni dažnesni (platforma „auga").
     */
    public function growthTime(int $from, int $to): int
    {
        return $to <= $from ? $from : $from + (int) (($to - $from) * sqrt($this->rand01()));
    }

    /**
     * @template T
     *
     * @param  array<array-key, T>  $items
     * @return T
     */
    public function pickOne(array $items): mixed
    {
        return $items[array_rand($items)];
    }

    /**
     * Indeksas pagal tikimybių sąrašą, pvz. [0.92, 0.04, 0.03, 0.01].
     *
     * @param  list<float>  $probabilities
     */
    public function pickIndex(array $probabilities): int
    {
        $roll = $this->rand01();
        $sum = 0.0;

        foreach ($probabilities as $index => $probability) {
            $sum += $probability;

            if ($roll < $sum) {
                return $index;
            }
        }

        return count($probabilities) - 1;
    }

    /**
     * Tikslūs kiekiai pagal dalis (pvz. 62 % / 6 % …), suma visada lygi $total.
     *
     * @param  array<string, float>  $shares
     * @return list<string> sumaišytas sąrašas
     */
    public function exactShares(int $total, array $shares): array
    {
        $list = [];

        foreach ($shares as $value => $share) {
            $list = [...$list, ...array_fill(0, (int) round($total * $share), (string) $value)];
        }

        $first = (string) array_key_first($shares);

        while (count($list) < $total) {
            $list[] = $first;
        }

        $list = array_slice($list, 0, $total);
        shuffle($list);

        return $list;
    }

    /**
     * Deterministinis UUID v4 (Str::uuid() naudoja kriptografinį atsitiktinumą – jo „užsėti" negalima).
     */
    public function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-4%03x-%04x-%04x%04x%04x',
            mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF), mt_rand(0, 0xFFF),
            mt_rand(0x8000, 0xBFFF), mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF),
        );
    }

    // --- Datos ------------------------------------------------------------------

    public function date(int $timestamp): string
    {
        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    public function nullableDate(int $timestamp): ?string
    {
        return $timestamp > 0 ? $this->date($timestamp) : null;
    }

    /**
     * Perkelia laiką į 8–21 val. Lietuvos laiku (žinutės nerašomos naktį).
     * Vasaros laikas apytikslis (balandis–spalis +3 val.), seed'ui to pakanka.
     */
    public function workingHours(int $timestamp): int
    {
        $month = (int) gmdate('n', $timestamp);
        $offset = ($month >= 4 && $month <= 10 ? 3 : 2) * self::HOUR;
        $local = $timestamp + $offset;
        $hour = (int) gmdate('G', $local);

        if ($hour >= 8 && $hour < 21) {
            return $timestamp;
        }

        $midnight = $local - ($local % self::DAY) + ($hour >= 21 ? self::DAY : 0);

        return $midnight + 8 * self::HOUR + mt_rand(0, 90 * self::MINUTE) - $offset;
    }

    // --- Tekstai ----------------------------------------------------------------

    /**
     * @return array<mixed>
     */
    public function texts(string $bank): array
    {
        if (! isset($this->texts[$bank])) {
            /** @var array<mixed> $data */
            $data = require database_path("data/texts/{$bank}.php");
            $this->texts[$bank] = $data;
        }

        return $this->texts[$bank];
    }

    /**
     * Atsitiktinis elementas iš teksto banko, pvz. text('requests', 'titles').
     */
    public function text(string $bank, string $key, string|int|null $subKey = null): string
    {
        /** @var array<array-key, mixed> $pool */
        $pool = $this->texts($bank)[$key];

        if ($subKey !== null) {
            /** @var array<array-key, mixed> $pool */
            $pool = $pool[$subKey];
        }

        return (string) $pool[array_rand($pool)];
    }

    /**
     * $count skirtingų sakinių iš banko, sujungtų tarpu.
     */
    public function sentences(string $bank, string $key, int $count, string|int|null $subKey = null): string
    {
        /** @var list<string> $pool */
        $pool = $subKey === null ? $this->texts($bank)[$key] : $this->texts($bank)[$key][$subKey];
        $keys = (array) array_rand($pool, min($count, count($pool)));
        shuffle($keys);

        return implode(' ', array_map(fn ($k) => $pool[$k], $keys));
    }

    /**
     * Įstato {reikšmes}. Banko „placeholders" (pvz. {patalpa}) parenkami atsitiktinai.
     *
     * @param  array<string, string|int>  $vars
     */
    public function render(string $template, array $vars, ?string $bank = null): string
    {
        if (! str_contains($template, '{')) {
            return $template;
        }

        $replacements = [];

        foreach ($vars as $key => $value) {
            $replacements['{'.$key.'}'] = (string) $value;
        }

        if ($bank !== null) {
            /** @var array<string, list<string>> $placeholders */
            $placeholders = $this->texts($bank)['placeholders'] ?? [];

            foreach ($placeholders as $key => $options) {
                if (str_contains($template, '{'.$key.'}')) {
                    $replacements['{'.$key.'}'] = $options[array_rand($options)];
                }
            }
        }

        return strtr($template, $replacements);
    }

    /**
     * Sakinių sujungimas: po kreipinio su kableliu („Sveiki,") – nauja eilutė, kaip laiške.
     *
     * @param  list<string>  $parts
     */
    public function joinSentences(array $parts): string
    {
        $text = '';

        foreach ($parts as $part) {
            $text .= $text === '' ? $part : (str_ends_with($text, ',') ? "\n" : ' ').$part;
        }

        return $text;
    }

    /**
     * Pirma raidė mažoji („Plytelių klijavimas" → „plytelių klijavimas"), bet ne santrumpos („LED…", „IT…").
     */
    public function lowerFirst(string $text): string
    {
        $second = mb_substr($text, 1, 1);

        if ($second !== '' && mb_strtoupper($second) === $second && mb_strtolower($second) !== $second) {
            return $text;
        }

        return mb_strtolower(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }

    /**
     * Unikalus slug: pavadinimas + id 36-tainėje sistemoje („plyteliu-klijavimas-kaune-2s").
     */
    public function slug(string $title, int $id, int $limit = 160): string
    {
        return rtrim(Str::limit(Str::slug($title), $limit, ''), '-').'-'.base_convert((string) $id, 10, 36);
    }

    // --- Atitikimas: kurie teikėjai tinka užklausai -----------------------------

    /**
     * Aktyvūs teikėjai, aptarnaujantys lapą (su tėvais) ir savivaldybę (arba visą Lietuvą).
     * Svoris – teikėjo aktyvumas.
     */
    public function candidates(int $leafId, int $cityId): ?WeightedPicker
    {
        $key = $leafId.':'.$cityId;

        if (! array_key_exists($key, $this->candidateCache)) {
            $values = [];
            $weights = [];
            $inCity = $this->cityProviders[$cityId] ?? [];

            foreach ($this->leafProviders[$leafId] ?? [] as $p) {
                if ($this->provWhole[$p] || isset($inCity[$p])) {
                    $values[] = $p;
                    $weights[] = $this->provActivity[$p];
                }
            }

            $this->candidateCache[$key] = $values === [] ? null : new WeightedPicker($values, $weights);
        }

        return $this->candidateCache[$key];
    }

    // --- Įrašymas į DB --------------------------------------------------------------

    /**
     * Masinis įterpimas dalimis vienoje transakcijoje (SQLite be transakcijos – labai lėtas).
     *
     * @param  iterable<array<string, mixed>>  $rows
     */
    public function insert(string $table, iterable $rows): int
    {
        $total = 0;
        $started = microtime(true);

        DB::transaction(function () use ($table, $rows, &$total) {
            $buffer = [];

            foreach ($rows as $row) {
                $buffer[] = $row;

                if (count($buffer) >= $this->chunk) {
                    DB::table($table)->insert($buffer);
                    $total += count($buffer);
                    $buffer = [];
                }
            }

            if ($buffer !== []) {
                DB::table($table)->insert($buffer);
                $total += count($buffer);
            }
        });

        $this->log(sprintf('  %-28s %10s  %5.1f s', $table, number_format($total, 0, ',', ' '), microtime(true) - $started));

        return $total;
    }

    /**
     * Masinis UPDATE: id => reikšmė, vienu CASE sakiniu kas 500 eilučių.
     *
     * @param  array<int, int|string|null>  $values
     */
    public function updateColumn(string $table, string $column, array $values): void
    {
        $started = microtime(true);

        DB::transaction(function () use ($table, $column, $values) {
            foreach (array_chunk($values, 500, true) as $chunk) {
                $cases = [];
                $bindings = [];

                foreach ($chunk as $id => $value) {
                    $cases[] = 'WHEN ? THEN ?';
                    $bindings[] = $id;
                    $bindings[] = $value;
                }

                $ids = implode(',', array_map('intval', array_keys($chunk)));
                DB::update("UPDATE {$table} SET {$column} = CASE id ".implode(' ', $cases)." END WHERE id IN ({$ids})", $bindings);
            }
        });

        $this->log(sprintf('  %-28s %10s  %5.1f s', $table.'.'.$column, number_format(count($values), 0, ',', ' '), microtime(true) - $started));
    }

    // --- Žinyninių duomenų užkrovimas --------------------------------------------

    private function loadReferenceData(): void
    {
        $population = [];

        foreach (ReferenceData::cities() as $cities) {
            foreach ($cities as $city) {
                $population[$city[0]] = $city[4];
            }
        }

        $weights = [];

        foreach (City::query()->orderBy('id')->get(['id', 'region_id', 'name', 'name_locative']) as $city) {
            $this->cityIds[] = $city->id;
            $this->cityRegion[$city->id] = $city->region_id;
            $this->cityLocative[$city->id] = $city->name_locative;
            $this->regionCities[$city->region_id][] = $city->id;
            $weights[] = $population[$city->name] ?? 10_000;
        }

        $this->cityPicker = new WeightedPicker($this->cityIds, $weights);

        // Populiarumo svoriai ir biudžetai yra tik duomenų faile, ne DB
        $treeWeights = [];
        $budgets = [];

        foreach (ReferenceData::categories() as $root) {
            $treeWeights[Str::slug($root['name'])] = $root['weight'];
            $budgets[Str::slug($root['name'])] = $root['budget'];

            foreach ($root['children'] as $child) {
                $treeWeights[Str::slug($child['name'])] = $child['weight'];
            }
        }

        $categories = Category::query()->orderBy('depth')->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'parent_id', 'depth', 'name', 'slug', 'offer_cost_credits'])
            ->keyBy('id');

        foreach ($categories as $category) {
            $this->categoryName[$category->id] = $category->name;

            if ($category->parent_id === null) {
                $this->rootIds[] = $category->id;
                $this->rootSlug[$category->id] = $category->slug;
                $this->rootBudget[$category->id] = $budgets[$category->slug] ?? [50, 2000];
                $this->categoryWeight[$category->id] = (float) ($treeWeights[$category->slug] ?? 1);

                continue;
            }

            $this->childrenOf[$category->parent_id][] = $category->id;

            if ($category->depth === 2) {
                $this->categoryWeight[$category->id] = (float) ($treeWeights[$category->slug] ?? 1);

                continue;
            }

            $rootId = (int) $categories->get($category->parent_id)?->parent_id;
            $this->leafIds[] = $category->id;
            $this->leafRoot[$category->id] = $rootId;
            $this->leafCost[$category->id] = $category->offer_cost_credits;
        }

        // Lapo svoris = 1 lygio svoris × 2 lygio svoris / lapų skaičius tame 2 lygyje
        foreach ($this->leafIds as $leafId) {
            $parentId = (int) $categories->get($leafId)?->parent_id;
            $this->categoryWeight[$leafId] = $this->categoryWeight[$this->leafRoot[$leafId]]
                * $this->categoryWeight[$parentId] / max(1, count($this->childrenOf[$parentId] ?? []));
        }

        $this->adminIds = array_values(User::query()->where('role', 'admin')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all());
        $this->clientBase = ((int) User::query()->max('id')) + 1;
        $this->providerUserBase = $this->clientBase + $this->counts['clients'];
    }
}
