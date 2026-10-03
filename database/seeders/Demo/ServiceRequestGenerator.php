<?php

namespace Database\Seeders\Demo;

use App\Enums\ServiceRequestStatus as Status;
use App\Enums\StartPreference;
use Generator;

/**
 * Užklausos: statusai, datos, kategorija ir miestas (docs/SEEDING.md 4 sk. „Užklausos").
 */
final class ServiceRequestGenerator
{
    private WeightedPicker $leafPicker;

    public function __construct(private readonly DemoContext $ctx)
    {
        $this->leafPicker = new WeightedPicker($ctx->leafIds, array_map(fn (int $id) => $ctx->categoryWeight[$id], $ctx->leafIds));
    }

    public function plan(): void
    {
        $c = $this->ctx;
        $total = $c->counts['service_requests'];
        $day = DemoContext::DAY;

        $statuses = $c->exactShares($total, [
            Status::Completed->value => 0.62,
            Status::InProgress->value => 0.06,
            Status::Open->value => 0.12,
            Status::Expired->value => 0.11,
            Status::Cancelled->value => 0.08,
            Status::Pending->value => 0.01,
        ]);

        $c->reqClient = $this->assignClients($total);

        foreach ($statuses as $r => $status) {
            $client = $c->reqClient[$r];
            $city = $c->chance(0.85) ? $c->clientCity[$client] : $c->cityPicker->pick();
            [$leaf, $city] = $this->ensureMatch($this->leafPicker->pick(), $city);

            $published = match ($status) {
                Status::Pending->value => 0,
                Status::Open->value => $c->now - $c->between(10 * DemoContext::MINUTE, 29 * $day),
                Status::InProgress->value => $c->now - $c->between(5 * $day, 60 * $day),
                Status::Completed->value => $c->growthTime($c->now - 730 * $day, $c->now - 35 * $day),
                Status::Expired->value => $c->growthTime($c->now - 730 * $day, $c->now - 31 * $day),
                default => $c->growthTime($c->now - 730 * $day, $c->now - DemoContext::HOUR),
            };

            // Prieš publikavimą užklausa laukia moderacijos (pending)
            $created = $published === 0
                ? $c->now - $c->between(5 * DemoContext::MINUTE, 2 * $day)
                : $published - $c->between(5 * DemoContext::MINUTE, 12 * DemoContext::HOUR);

            $cancelled = 0;

            if ($status === Status::Cancelled->value) {
                $cancelled = $published + $c->between(DemoContext::HOUR, min(20 * $day, $c->now - $published - 60));
            }

            $c->reqLeaf[] = $leaf;
            $c->reqCity[] = $city;
            $c->reqStatus[] = $status;
            $c->reqCreated[] = $created;
            $c->reqPublished[] = $published;
            $c->reqExpires[] = $published > 0 ? $published + 30 * $day : 0;
            $c->reqCancelled[] = $cancelled;
            $c->reqCompleted[] = 0;
            $c->reqAccepted[] = -1;
            $c->reqAcceptTime[] = 0;
        }
    }

    /**
     * Užklausų skaičius klientui: 1 (60 %), 2 (25 %), 3 (10 %), 4–6 (5 %); suma lygiai $total.
     *
     * @return list<int> užklausos indeksas => kliento indeksas
     */
    private function assignClients(int $total): array
    {
        $c = $this->ctx;
        $owners = [];
        $clients = $c->counts['clients'];

        for ($client = 0; $client < $clients && count($owners) < $total; $client++) {
            $n = [1, 2, 3, 4][$c->pickIndex([0.60, 0.25, 0.10, 0.05])];

            if ($n === 4) {
                $n = $c->between(4, 6);
            }

            array_push($owners, ...array_fill(0, min($n, $total - count($owners)), $client));
        }

        // Klientų pritrūko – likusias užklausas atiduodam atsitiktiniams klientams
        while (count($owners) < $total) {
            $owners[] = $c->between(0, $clients - 1);
        }

        shuffle($owners);

        return $owners;
    }

    /**
     * Užklausa turi turėti tinkamų teikėjų, kitaip jai negalėtų būti pasiūlymų (ir priimto pasiūlymo).
     * Ieškom poros (kategorija, savivaldybė), kur kandidatų bent $want; jei nerandam – imam geriausią rastą.
     *
     * @return array{0: int, 1: int} [lapas, savivaldybė]
     */
    private function ensureMatch(int $leaf, int $city, int $want = 3): array
    {
        $c = $this->ctx;
        $best = [$leaf, $city];
        $bestCount = 0;

        for ($try = 0; $try < 60; $try++) {
            $count = $c->candidates($leaf, $city)?->count() ?? 0;

            if ($count >= $want) {
                return [$leaf, $city];
            }

            if ($count > $bestCount) {
                [$best, $bestCount] = [[$leaf, $city], $count];
            }

            // Pirma keičiam kategoriją (miestas dažniausiai kliento), vėliau ir miestą
            $leaf = $this->leafPicker->pick();

            if ($try >= 30) {
                $city = $c->cityPicker->pick();
            }
        }

        if ($bestCount > 0) {
            return $best;
        }

        // Retas atvejis (labai mažas SEED_SCALE): imam atsitiktinį aktyvų teikėją ir jo kategoriją bei zoną
        $active = array_keys(array_filter($c->provStatus, fn (string $status) => $status === 'active'));
        $p = $c->pickOne($active);
        $leaf = $c->pickOne($c->provLeaves[$p]);
        $city = $c->provZones[$p] === [] ? $city : $c->pickOne($c->provZones[$p]);

        return [$leaf, $city];
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function rows(): Generator
    {
        $c = $this->ctx;
        $preferences = [StartPreference::Asap, StartPreference::ThisWeek, StartPreference::ThisMonth, StartPreference::Flexible, StartPreference::Date];

        foreach ($c->reqStatus as $r => $status) {
            $id = $r + 1;
            $leaf = $c->reqLeaf[$r];
            $root = $c->leafRoot[$leaf];
            $vars = [
                'paslauga' => $c->categoryName[$leaf],
                'paslauga_m' => $c->lowerFirst($c->categoryName[$leaf]),
                'miestas' => $c->cityLocative[$c->reqCity[$r]],
                'plotas' => $c->between(2, 30) * 5,
                'kiekis' => $c->between(2, 12),
            ];

            $title = mb_substr($c->render($c->text('requests', 'titles'), $vars, 'requests'), 0, 150);
            $description = $this->description($root, $vars);

            $budgetMin = null;
            $budgetMax = null;

            if ($c->chance(0.6)) {
                [$min, $max] = $c->rootBudget[$root];
                $budgetMin = $this->roundMoney($c->between($min, (int) max($min, $max * 0.5)));
                $budgetMax = $this->roundMoney((int) ($budgetMin * $c->between(130, 250) / 100));
            }

            $base = $c->reqPublished[$r] ?: $c->reqCreated[$r];
            $preference = $preferences[$c->pickIndex([0.25, 0.20, 0.25, 0.20, 0.10])];
            $updated = max($c->reqCreated[$r], $c->reqPublished[$r], $c->reqAcceptTime[$r], $c->reqCompleted[$r], $c->reqCancelled[$r]);

            $c->reqTitle[$r] = $title;
            $c->reqSlug[$r] = $c->slug($title, $id);

            yield [
                'id' => $id,
                'client_id' => $c->clientBase + $c->reqClient[$r],
                'category_id' => $leaf,
                'city_id' => $c->reqCity[$r],
                'slug' => $c->reqSlug[$r],
                'title' => $title,
                'description' => $description,
                'address' => $c->chance(0.3) ? $c->faker->streetAddress() : null,
                'budget_min_cents' => $budgetMin === null ? null : $budgetMin * 100,
                'budget_max_cents' => $budgetMax === null ? null : $budgetMax * 100,
                'start_preference' => $preference->value,
                'start_date' => $preference === StartPreference::Date
                    ? gmdate('Y-m-d', $base + $c->between(3, 40) * DemoContext::DAY)
                    : null,
                'status' => $status,
                'accepted_offer_id' => null,
                'offers_count' => 0,
                'views_count' => $c->reqPublished[$r] > 0 ? $c->reqOfferCount[$r] * $c->between(3, 15) + $c->between(0, 60) : 0,
                'published_at' => $c->nullableDate($c->reqPublished[$r]),
                'expires_at' => $c->nullableDate($c->reqExpires[$r]),
                'completed_at' => $c->nullableDate($c->reqCompleted[$r]),
                'cancelled_at' => $c->nullableDate($c->reqCancelled[$r]),
                'created_at' => $c->date($c->reqCreated[$r]),
                'updated_at' => $c->date($updated),
            ];
        }
    }

    /**
     * Aprašymas iš kelių dalių: pradžia + 1–2 detalės pagal sritį + medžiagos + terminas + pabaiga.
     *
     * @param  array<string, string|int>  $vars
     */
    private function description(int $root, array $vars): string
    {
        $c = $this->ctx;
        $parts = [$c->text('requests', 'openings')];
        /** @var list<string> $details */
        $details = $c->texts('requests')['details'][$c->rootSlug[$root]] ?? [];

        foreach ((array) array_rand($details, min(count($details), $c->between(1, 2))) as $key) {
            $parts[] = $c->render($details[$key], $vars, 'requests');
        }

        if ($c->chance(0.5)) {
            $parts[] = $c->text('requests', 'materials');
        }

        if ($c->chance(0.6)) {
            $parts[] = $c->render($c->text('requests', 'timing'), $vars, 'requests');
        }

        $parts[] = $c->text('requests', 'closings');

        return $c->joinSentences($parts);
    }

    /**
     * Biudžetas apvalinamas kaip žmonės rašo: iki 100 € – po 5, iki 1000 € – po 50, toliau – po 100.
     */
    private function roundMoney(int $euros): int
    {
        $step = $euros < 100 ? 5 : ($euros < 1000 ? 50 : 100);

        return max($step, (int) round($euros / $step) * $step);
    }
}
