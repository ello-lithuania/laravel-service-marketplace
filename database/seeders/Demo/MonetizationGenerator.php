<?php

namespace Database\Seeders\Demo;

use App\Enums\CreditTransactionType as TxType;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\SubscriptionStatus;
use App\Models\CreditPackage;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;

/**
 * Prenumeratos, mokėjimai ir kreditų ledger (docs/SEEDING.md 4 sk. „Kreditai, mokėjimai, prenumeratos").
 *
 * Ledger kuriamas chronologiškai kiekvienam teikėjui: dovana → prenumeratų laikotarpiai, pasiūlymai, grąžinimai.
 * Jei balanso pasiūlymui neužtenka, prieš jį „nuperkamas" paketas, todėl balance_after niekada nebūna neigiamas.
 */
final class MonetizationGenerator
{
    private const PERIOD = 30 * DemoContext::DAY;

    private const BONUS_CREDITS = 5;

    /** @var array<string, list<array<string, mixed>>> */
    private array $buffers = [];

    /** @var array<string, int> */
    private array $totals = [];

    private int $paymentId = 0;

    private int $transactionId = 0;

    private int $subscriptionId = 0;

    /** @var list<array{id: int, name: string, price: int, credits: int}> */
    private array $plans = [];

    /** @var list<array{id: int, name: string, price: int, credits: int}> */
    private array $packages = [];

    public function __construct(private readonly DemoContext $ctx)
    {
        foreach (SubscriptionPlan::query()->orderBy('sort_order')->get() as $plan) {
            $this->plans[] = ['id' => $plan->id, 'name' => $plan->name, 'price' => $plan->price_cents, 'credits' => $plan->credits_per_period];
        }

        foreach (CreditPackage::query()->orderBy('sort_order')->get() as $package) {
            $this->packages[] = ['id' => $package->id, 'name' => $package->name, 'price' => $package->price_cents, 'credits' => $package->credits + $package->bonus_credits];
        }
    }

    public function run(): void
    {
        $c = $this->ctx;
        $offersOf = [];
        $refundsOf = [];

        foreach ($c->offProvider as $o => $p) {
            $offersOf[$p][] = $o;

            if ($c->offRefund[$o] > 0) {
                $refundsOf[$p][] = $o;
            }
        }

        DB::transaction(function () use ($c, $offersOf, $refundsOf) {
            foreach ($c->provStatus as $p => $status) {
                $offers = $offersOf[$p] ?? [];
                $refunds = $refundsOf[$p] ?? [];
                usort($offers, fn (int $a, int $b) => $c->offCreated[$a] <=> $c->offCreated[$b]);
                usort($refunds, fn (int $a, int $b) => $c->offRefund[$a] <=> $c->offRefund[$b]);

                $periods = $status !== 'pending' && $c->chance(0.15) ? $this->subscription($p) : [];
                $this->ledger($p, $periods, $offers, $refunds);
            }

            foreach (array_keys($this->buffers) as $table) {
                $this->flush($table);
            }
        });

        foreach (['subscriptions', 'payments', 'credit_transactions'] as $table) {
            $c->log(sprintf('  %-28s %10s', $table, number_format($this->totals[$table] ?? 0, 0, ',', ' ')));
        }
    }

    /**
     * Viena prenumerata su keliais laikotarpiais; ≈ pusė jų aktyvios dabar.
     *
     * @return list<array{time: int, plan: array{id: int, name: string, price: int, credits: int}, subscription: int}>
     */
    private function subscription(int $p): array
    {
        $c = $this->ctx;
        $created = $c->provCreated[$p] + DemoContext::HOUR;
        $plan = $this->plans[$c->pickIndex([0.5, 0.35, 0.15])];
        $periods = $c->skewed(1, 10, 1.5);
        $latestPastEnd = $c->now - DemoContext::DAY;
        $current = $c->chance(0.5) || $created + self::PERIOD > $latestPastEnd;

        if ($current) {
            $end = $c->now + $c->between(DemoContext::DAY, 29 * DemoContext::DAY);
            $periods = max(1, min($periods, intdiv($end - $created, self::PERIOD)));
            $start = max($created, $end - $periods * self::PERIOD);
            $end = $start + $periods * self::PERIOD;
            $status = $c->chance(0.85) ? SubscriptionStatus::Active : SubscriptionStatus::Cancelled;
        } else {
            $periods = max(1, min($periods, intdiv($latestPastEnd - $created, self::PERIOD)));
            $end = $c->between($created + $periods * self::PERIOD, $latestPastEnd);
            $start = $end - $periods * self::PERIOD;
            $status = $c->chance(0.7) ? SubscriptionStatus::Expired : SubscriptionStatus::Cancelled;
        }

        $cancelled = $status === SubscriptionStatus::Cancelled ? $c->between($start, min($end, $c->now)) : 0;
        $id = ++$this->subscriptionId;

        $this->push('subscriptions', [
            'id' => $id,
            'provider_profile_id' => $p + 1,
            'subscription_plan_id' => $plan['id'],
            'status' => $status->value,
            'starts_at' => $c->date($start),
            'ends_at' => $c->date($end),
            'cancelled_at' => $c->nullableDate($cancelled),
            'auto_renew' => $status === SubscriptionStatus::Active,
            'created_at' => $c->date($start),
            'updated_at' => $c->date(max($start, $cancelled, min($end, $c->now) - self::PERIOD)),
        ]);

        $result = [];

        for ($k = 0; $k < $periods; $k++) {
            $result[] = ['time' => $start + $k * self::PERIOD, 'plan' => $plan, 'subscription' => $id];
        }

        return $result;
    }

    /**
     * @param  list<array{time: int, plan: array{id: int, name: string, price: int, credits: int}, subscription: int}>  $periods
     * @param  list<int>  $offers
     * @param  list<int>  $refunds
     */
    private function ledger(int $p, array $periods, array $offers, array $refunds): void
    {
        $c = $this->ctx;
        $balance = self::BONUS_CREDITS;
        $last = $c->provCreated[$p] + 60;
        $this->transaction($p, self::BONUS_CREDITS, $balance, TxType::Bonus, null, null, 'Dovana naujam teikėjui', $last);

        $i = $j = $k = 0;

        while ($i < count($periods) || $j < count($offers) || $k < count($refunds)) {
            $periodTime = $periods[$i]['time'] ?? PHP_INT_MAX;
            $offerTime = isset($offers[$j]) ? $c->offCreated[$offers[$j]] : PHP_INT_MAX;
            $refundTime = isset($refunds[$k]) ? $c->offRefund[$refunds[$k]] : PHP_INT_MAX;

            if ($periodTime <= $offerTime && $periodTime <= $refundTime) {
                $period = $periods[$i++];
                $paidAt = $this->payment($p, 'subscription_plan', $period['plan']['id'], $period['plan']['price'], max($last, $period['time']));
                $balance += $period['plan']['credits'];
                $last = $paidAt;
                $this->transaction($p, $period['plan']['credits'], $balance, TxType::Subscription, 'subscription', $period['subscription'], 'Prenumerata „'.$period['plan']['name'].'"', $last);
            } elseif ($offerTime <= $refundTime) {
                $o = $offers[$j++];
                $r = $c->offRequest[$o];
                $cost = $c->leafCost[$c->reqLeaf[$r]];

                if ($balance < $cost) {
                    $package = $this->packages[$c->pickIndex([0.45, 0.30, 0.17, 0.08])];
                    $paidAt = $this->payment($p, 'credit_package', $package['id'], $package['price'], max($last, $offerTime - $c->between(5 * DemoContext::MINUTE, DemoContext::HOUR)));
                    $balance += $package['credits'];
                    $last = $paidAt;
                    $this->transaction($p, $package['credits'], $balance, TxType::Purchase, 'payment', $this->paymentId, 'Kreditų paketas „'.$package['name'].'"', $last);
                }

                $balance -= $cost;
                $last = max($last, $offerTime);
                $this->transaction($p, -$cost, $balance, TxType::Offer, 'offer', $o + 1, 'Pasiūlymas: '.$c->reqTitle[$r], $last);
            } else {
                $o = $refunds[$k++];
                $r = $c->offRequest[$o];
                $cost = $c->leafCost[$c->reqLeaf[$r]];
                $balance += $cost;
                $last = max($last, $refundTime);
                $reason = $c->reqStatus[$r] === ServiceRequestStatus::Cancelled->value ? 'užklausa atšaukta' : 'pasiūlymas neperžiūrėtas';
                $this->transaction($p, $cost, $balance, TxType::Refund, 'offer', $o + 1, 'Grąžinimas: '.$reason, $last);
            }
        }
    }

    /**
     * Sėkmingas mokėjimas (3 % atvejų prieš jį – nepavykęs ar atšauktas). Grąžina apmokėjimo laiką.
     */
    private function payment(int $p, string $type, int $purchasableId, int $amount, int $time): int
    {
        $c = $this->ctx;

        if ($c->chance(0.03)) {
            $this->paymentRow($p, $type, $purchasableId, $amount, $c->chance(0.5) ? PaymentStatus::Failed : PaymentStatus::Cancelled, $time - $c->between(60, 600), 0);
        }

        $paidAt = min($c->now, $time + $c->between(10, 300));
        $this->paymentRow($p, $type, $purchasableId, $amount, PaymentStatus::Paid, $time, $paidAt);

        return $paidAt;
    }

    private function paymentRow(int $p, string $type, int $purchasableId, int $amount, PaymentStatus $status, int $time, int $paidAt): void
    {
        $c = $this->ctx;
        $id = ++$this->paymentId;
        $gateway = $c->chance(0.95) ? PaymentGateway::Paysera : PaymentGateway::Manual;

        $this->push('payments', [
            'id' => $id,
            'uuid' => $c->uuid(),
            'user_id' => $c->providerUserBase + $p,
            'gateway' => $gateway->value,
            'gateway_reference' => $gateway === PaymentGateway::Paysera ? sprintf('PS%010d', $id) : null,
            'purchasable_type' => $type,
            'purchasable_id' => $purchasableId,
            'amount_cents' => $amount,
            'currency' => 'EUR',
            'status' => $status->value,
            'paid_at' => $c->nullableDate($paidAt),
            'invoice_number' => $paidAt > 0 ? sprintf('SF-%s-%06d', gmdate('Y', $paidAt), $id) : null,
            'meta' => null,
            'created_at' => $c->date($time),
            'updated_at' => $c->date(max($time, $paidAt)),
        ]);
    }

    private function transaction(int $p, int $amount, int $balance, TxType $type, ?string $sourceType, ?int $sourceId, string $description, int $time): void
    {
        $this->push('credit_transactions', [
            'id' => ++$this->transactionId,
            'provider_profile_id' => $p + 1,
            'amount' => $amount,
            'balance_after' => $balance,
            'type' => $type->value,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'description' => mb_substr($description, 0, 255),
            'created_at' => $this->ctx->date(min($time, $this->ctx->now)),
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function push(string $table, array $row): void
    {
        $this->buffers[$table][] = $row;

        if (count($this->buffers[$table]) >= $this->ctx->chunk) {
            $this->flush($table);
        }
    }

    private function flush(string $table): void
    {
        if (($this->buffers[$table] ?? []) === []) {
            return;
        }

        DB::table($table)->insert($this->buffers[$table]);
        $this->totals[$table] = ($this->totals[$table] ?? 0) + count($this->buffers[$table]);
        $this->buffers[$table] = [];
    }
}
