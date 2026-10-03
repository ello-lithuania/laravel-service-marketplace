<?php

namespace App\Actions\Subscriptions;

use App\Enums\CreditTransactionType;
use App\Models\ProviderProfile;
use App\Models\Subscription;
use App\Services\Credits\CreditLedger;
use Illuminate\Support\Facades\DB;

/**
 * Suteikia prenumeratos kreditus už prasidėjusius ir apmokėtus laikotarpius – IDEMPOTENTIŠKAI.
 *
 * subscriptions.credits_granted_until – iki kada kreditai jau suteikti (docs/DB_SCHEMA.md → subscriptions).
 * Kitas laikotarpis prasideda ties credits_granted_until (arba starts_at). Kreditai už jį suteikiami, kai:
 *   - jis jau prasidėjo (pradžia <= dabar) ir
 *   - jis apmokėtas (pradžia < ends_at).
 * Tada įrašoma ledger eilutė IR pastumiamas credits_granted_until – toje pačioje transakcijoje, užrakinus eilutę.
 * Todėl kviečiant kiek nori kartų (Scheduler kas valandą, apmokėjimas, rankinis paleidimas) tas pats laikotarpis
 * kreditų antrą kartą negauna. Ciklas – jei Scheduler kurį laiką neveikė, suteikiami visi praleisti laikotarpiai.
 */
class GrantSubscriptionCredits
{
    public function __construct(private readonly CreditLedger $ledger) {}

    /**
     * @return int kiek laikotarpių kreditai suteikti dabar (0 – nieko nereikėjo)
     */
    public function handle(Subscription $subscription): int
    {
        return DB::transaction(function () use ($subscription): int {
            // Užraktų tvarka kaip CompletePayment: teikėjas → prenumerata
            $provider = ProviderProfile::withTrashed()->whereKey($subscription->provider_profile_id)->lockForUpdate()->firstOrFail();
            $locked = Subscription::query()->with('plan')->whereKey($subscription->id)->lockForUpdate()->firstOrFail();

            $granted = 0;

            while (true) {
                $periodStart = $locked->credits_granted_until ?? $locked->starts_at;

                if ($periodStart->greaterThanOrEqualTo($locked->ends_at) || $periodStart->isFuture()) {
                    break;
                }

                $periodEnd = $locked->plan->billing_period->addTo($periodStart)->min($locked->ends_at);
                $credits = $locked->plan->credits_per_period;

                if ($credits > 0) {
                    $this->ledger->credit($provider, $credits, CreditTransactionType::Subscription, $locked, __('billing.ledger.subscription', [
                        'plan' => $locked->plan->name,
                        'from' => $periodStart->timezone('Europe/Vilnius')->format('Y-m-d'),
                        'to' => $periodEnd->timezone('Europe/Vilnius')->format('Y-m-d'),
                    ]));
                }

                $locked->forceFill(['credits_granted_until' => $periodEnd])->save();
                $granted++;
            }

            $subscription->setRawAttributes($locked->getAttributes(), true);

            return $granted;
        });
    }
}
