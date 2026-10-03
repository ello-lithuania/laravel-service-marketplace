<?php

namespace Database\Seeders\Demo;

use App\Enums\OfferPriceType;
use App\Enums\OfferStatus;
use App\Enums\ServiceRequestStatus as Status;
use Generator;

/**
 * Pasiūlymai ir užklausų eiga: kas, kada ir kokiu statusu (docs/SEEDING.md 4 sk., docs/STATES.md).
 */
final class OfferGenerator
{
    public function __construct(private readonly DemoContext $ctx) {}

    public function plan(): void
    {
        $counts = $this->offerCounts();
        $c = $this->ctx;

        foreach ($c->reqStatus as $r => $status) {
            $c->reqOfferStart[$r] = count($c->offStatus);
            $c->reqOfferCount[$r] = $counts[$r];

            if ($counts[$r] > 0) {
                $this->planRequest($r, $status, $counts[$r]);
            }
        }

        $this->assignProviderDates();
    }

    /**
     * Pasiūlymų skaičius kiekvienai užklausai; bendra suma – lygiai tiek, kiek nurodyta (jei įmanoma).
     *
     * @return array<int, int>
     */
    private function offerCounts(): array
    {
        $c = $this->ctx;
        $counts = [];
        $mins = [];
        $maxs = [];

        foreach ($c->reqStatus as $r => $status) {
            [$min, $max] = match ($status) {
                Status::Pending->value => [0, 0],
                Status::Open->value => [0, 8],
                Status::InProgress->value, Status::Completed->value => [1, 10],
                default => [0, 6],
            };
            $available = $c->candidates($c->reqLeaf[$r], $c->reqCity[$r])?->count() ?? 0;
            $mins[$r] = min($min, $available);
            $maxs[$r] = min($max, $available);
            // Mažesni skaičiai dažnesni (vidurkis ≈ 3 pasiūlymai užklausai)
            $counts[$r] = min($maxs[$r], max($mins[$r], $c->skewed($min, $max)));
        }

        $target = $c->counts['offers'];
        $sum = array_sum($counts);
        $total = count($counts);
        $guard = 0;

        // Atsitiktinai pridedam arba atimam po vieną, kol suma lygi tikslui (ribos nepažeidžiamos)
        while ($sum !== $target && $guard++ < $target * 50) {
            $r = $c->between(0, $total - 1);

            if ($sum < $target && $counts[$r] < $maxs[$r]) {
                $counts[$r]++;
                $sum++;
            } elseif ($sum > $target && $counts[$r] > $mins[$r]) {
                $counts[$r]--;
                $sum--;
            }
        }

        if ($sum !== $target) {
            // Labai mažas SEED_SCALE: tinkamų teikėjų per mažai, kad būtų pasiektas tikslas
            $c->log(sprintf('  <comment>Pasiūlymų %d vietoj %d – per mažai tinkamų teikėjų (didink SEED_SCALE)</comment>', $sum, $target));
        }

        return $counts;
    }

    private function planRequest(int $r, string $status, int $count): void
    {
        $c = $this->ctx;
        $published = $c->reqPublished[$r];
        $day = DemoContext::DAY;

        // Iki kada dar galėjo ateiti pasiūlymai
        $windowEnd = match ($status) {
            Status::Open->value => $c->now,
            Status::InProgress->value, Status::Completed->value => $published + (int) min(5 * $day, ($c->now - $published) * 0.5),
            Status::Expired->value => $c->reqExpires[$r],
            default => $c->reqCancelled[$r],
        };
        $window = max(120, $windowEnd - $published - 60);

        $providers = $c->candidates($c->reqLeaf[$r], $c->reqCity[$r])?->pickUnique($count) ?? [];
        $times = [];

        foreach ($providers as $p) {
            // 10 min – 5 d. po publikavimo, dauguma per pirmą parą
            $delay = 600 + (int) ((5 * $day - 600) * ($c->rand01() ** 3));

            if ($delay > $window) {
                $delay = $c->between(60, $window);
            }

            $times[] = $published + $delay;
        }

        array_multisort($times, $providers);
        $first = count($c->offStatus);

        foreach ($providers as $i => $p) {
            $c->offRequest[] = $r;
            $c->offProvider[] = $p;
            $c->offCreated[] = $times[$i];
            $c->offStatus[] = OfferStatus::Pending->value;
            $c->offViewed[] = 0;
            $c->offResponded[] = 0;
            $c->offRefund[] = 0;
        }

        $last = $first + count($providers) - 1;
        $lastCreated = $times[count($times) - 1];

        match ($status) {
            Status::Open->value => $this->open($first, $last),
            Status::InProgress->value, Status::Completed->value => $this->accepted($r, $status, $first, $last, $lastCreated),
            Status::Expired->value => $this->closed($first, $last, $c->reqExpires[$r], viewedShare: 0.6, refundUnviewedOnly: true),
            default => $this->closed($first, $last, $c->reqCancelled[$r], viewedShare: 0.5, refundUnviewedOnly: false),
        };
    }

    /**
     * Atvira užklausa: pasiūlymai laukia (5 % teikėjų atšaukė savo pasiūlymą).
     */
    private function open(int $first, int $last): void
    {
        $c = $this->ctx;

        for ($o = $first; $o <= $last; $o++) {
            $c->offStatus[$o] = $c->chance(0.05) ? OfferStatus::Withdrawn->value : OfferStatus::Pending->value;

            if ($c->chance(0.5)) {
                $c->offViewed[$o] = $c->between($c->offCreated[$o] + 60, $c->now);
            }
        }
    }

    /**
     * Užklausa, kurioje pasiūlymas priimtas: vienas accepted, kiti declined (sistema) arba withdrawn.
     */
    private function accepted(int $r, string $status, int $first, int $last, int $lastCreated): void
    {
        $c = $this->ctx;
        $latest = $status === Status::Completed->value ? $c->now - 3 * DemoContext::DAY : $c->now - DemoContext::HOUR;
        $accept = max($lastCreated + 60, min($latest, $lastCreated + $c->between(DemoContext::HOUR, 3 * DemoContext::DAY)));
        $chosen = $c->between($first, $last);

        for ($o = $first; $o <= $last; $o++) {
            if ($o === $chosen) {
                $c->offStatus[$o] = OfferStatus::Accepted->value;
                $c->offViewed[$o] = $c->between($c->offCreated[$o] + 60, $accept);
                $c->offResponded[$o] = $accept;

                continue;
            }

            if ($c->chance(0.05)) {
                $c->offStatus[$o] = OfferStatus::Withdrawn->value;

                continue;
            }

            $c->offStatus[$o] = OfferStatus::Declined->value;

            if ($c->chance(0.8)) {
                $c->offViewed[$o] = $c->between($c->offCreated[$o] + 60, $accept);

                // 30 % klientas atmetė pats, kiti atmesti automatiškai priėmus kitą pasiūlymą
                if ($c->chance(0.3)) {
                    $c->offResponded[$o] = $c->between($c->offViewed[$o], $accept);
                }
            }
        }

        $c->reqAccepted[$r] = $chosen;
        $c->reqAcceptTime[$r] = $accept;

        if ($status === Status::Completed->value) {
            $c->reqCompleted[$r] = min(
                $accept + $c->between(DemoContext::DAY, 30 * DemoContext::DAY),
                $c->now - $c->between(DemoContext::HOUR, 2 * DemoContext::DAY),
            );
        }
    }

    /**
     * Pasibaigusi arba atšaukta užklausa: laukę pasiūlymai tampa declined.
     * Kreditai grąžinami pagal docs/STATES.md 3 sk.
     */
    private function closed(int $first, int $last, int $closedAt, float $viewedShare, bool $refundUnviewedOnly): void
    {
        $c = $this->ctx;

        for ($o = $first; $o <= $last; $o++) {
            if ($c->chance(0.05)) {
                $c->offStatus[$o] = OfferStatus::Withdrawn->value;

                continue;
            }

            $c->offStatus[$o] = OfferStatus::Declined->value;

            if ($c->chance($viewedShare)) {
                $c->offViewed[$o] = $c->between($c->offCreated[$o] + 60, $closedAt);

                if ($c->chance(0.15)) {
                    $c->offResponded[$o] = $c->between($c->offViewed[$o], $closedAt);
                }
            }

            // Klientas pats atmetė – tai įprasta konkurencija, negrąžinama
            if ($c->offResponded[$o] > 0) {
                continue;
            }

            if (! $refundUnviewedOnly || $c->offViewed[$o] === 0) {
                $c->offRefund[$o] = $closedAt;
            }
        }
    }

    /**
     * Teikėjas užsiregistravo anksčiau nei išsiuntė pirmą pasiūlymą; „pending" teikėjai – naujai užsiregistravę.
     */
    private function assignProviderDates(): void
    {
        $c = $this->ctx;
        $count = count($c->provStatus);
        $first = array_fill(0, $count, PHP_INT_MAX);
        $last = array_fill(0, $count, 0);

        foreach ($c->offProvider as $o => $p) {
            $first[$p] = min($first[$p], $c->offCreated[$o]);
            $last[$p] = max($last[$p], $c->offCreated[$o]);
        }

        foreach ($c->provStatus as $p => $status) {
            if ($status === 'pending') {
                $created = $c->now - $c->between(DemoContext::HOUR, 14 * DemoContext::DAY);
            } else {
                $created = $c->growthTime($c->start, min($first[$p], $c->now) - DemoContext::HOUR);
            }

            $c->provCreated[$p] = $created;
            $c->provLastActive[$p] = max($created, $last[$p], $c->growthTime($created, $c->now));
        }
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function rows(): Generator
    {
        $c = $this->ctx;
        $types = [OfferPriceType::Fixed, OfferPriceType::Hourly, OfferPriceType::PerUnit, OfferPriceType::AfterInspection];

        foreach ($c->offStatus as $o => $status) {
            $r = $c->offRequest[$o];
            $leaf = $c->reqLeaf[$r];
            $created = $c->offCreated[$o];
            $type = $types[$c->pickIndex([0.55, 0.15, 0.10, 0.20])];
            [$min, $max] = $c->rootBudget[$c->leafRoot[$leaf]];

            $price = match ($type) {
                OfferPriceType::Fixed => $c->between($min, (int) max($min, $max * 0.4)) * 100,
                OfferPriceType::Hourly => $c->between(10, 50) * 100,
                OfferPriceType::PerUnit => $c->between(4, 80) * 50,
                OfferPriceType::AfterInspection => null,
            };

            $vars = [
                'metai' => $c->between(2, 20),
                'paslauga_m' => $c->lowerFirst($c->categoryName[$leaf]),
            ];
            $message = $c->joinSentences([
                $c->text('offers', 'openings'),
                $c->render($c->sentences('offers', 'bodies', $c->between(2, 3)), $vars, 'offers'),
                $c->text('offers', 'closings'),
            ]);

            yield [
                'id' => $o + 1,
                'service_request_id' => $r + 1,
                'provider_profile_id' => $c->offProvider[$o] + 1,
                'message' => $message,
                'price_cents' => $price,
                'price_type' => $type->value,
                'duration_text' => $c->chance(0.7) ? $c->text('offers', 'durations') : null,
                'start_date' => $c->chance(0.4) ? gmdate('Y-m-d', $created + $c->between(1, 20) * DemoContext::DAY) : null,
                'status' => $status,
                'credits_spent' => $c->leafCost[$leaf],
                'viewed_at' => $c->nullableDate($c->offViewed[$o]),
                'responded_at' => $c->nullableDate($c->offResponded[$o]),
                'created_at' => $c->date($created),
                'updated_at' => $c->date(max($created, $c->offViewed[$o], $c->offResponded[$o], $c->offRefund[$o])),
            ];
        }
    }

    /**
     * service_requests.accepted_offer_id – atskiru UPDATE, nes pasiūlymai įterpiami po užklausų (žiedinis FK).
     */
    public function linkAccepted(): void
    {
        $values = [];

        foreach ($this->ctx->reqAccepted as $r => $o) {
            if ($o >= 0) {
                $values[$r + 1] = $o + 1;
            }
        }

        $this->ctx->updateColumn('service_requests', 'accepted_offer_id', $values);
    }
}
