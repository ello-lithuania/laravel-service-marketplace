<?php

namespace App\Services\Payments;

use App\Enums\SubscriptionStatus;
use App\Models\CreditTransaction;
use App\Models\Payment;
use App\Models\ProviderProfile;
use App\Models\Subscription;
use Carbon\CarbonImmutable;

/**
 * Apskaičiuoja, ką padarys mokėjimo grąžinimas – nieko nekeisdamas (taisyklės: docs/DB_SCHEMA.md → refunds).
 *
 * Kodėl atskirai nuo RefundPayment: tą patį skaičiavimą reikia parodyti administratoriui PRIEŠ patvirtinant
 * („bus atimta 12 kreditų iš 30, prenumerata baigsis iškart"), o vykdant – pakartoti su užrakintomis eilutėmis.
 * „Sprendimas" (gryna funkcija) atskirtas nuo „vykdymo" (įrašai DB) – jį lengva ištestuoti ir panaudoti dukart.
 *
 * Kreditai:
 *   - kreditų paketas – tiek, kiek šis mokėjimas suteikė (ledger eilučių su source = payment suma);
 *   - prenumerata – credits_per_period, jei kreditai už atimamą (paskutinį apmokėtą) laikotarpį jau suteikti;
 *   - atimama ne daugiau nei balansas, likutis – „shortfall".
 * Prenumerata: „vienas mokėjimas = vienas laikotarpis" – sutrumpinama paskutiniu apmokėtu laikotarpiu.
 */
class RefundCalculator
{
    /**
     * Peržiūrai (Filament patvirtinimo langas): teikėjas ir prenumerata užkraunami be užraktų.
     */
    public function preview(Payment $payment): RefundCalculation
    {
        $provider = ProviderProfile::withTrashed()->where('user_id', $payment->user_id)->first();
        $subscription = $payment->subscription_id === null
            ? null
            : Subscription::query()->with('plan')->whereKey($payment->subscription_id)->first();

        return $this->calculate($payment, $provider, $subscription);
    }

    /**
     * @param  Subscription|null  $subscription  su užkrautu plan ryšiu
     */
    public function calculate(Payment $payment, ?ProviderProfile $provider, ?Subscription $subscription): RefundCalculation
    {
        $balance = $provider === null ? 0 : $provider->credits_balance;

        if ($subscription === null) {
            return $this->credits($this->packageCredits($payment), $balance);
        }

        return $this->forSubscription($subscription, $balance);
    }

    /**
     * Grynasis šio mokėjimo kreditų pokytis ledger'yje (pirkimas +30 → 30). Jei kas nors jau atėmė dalį
     * (pvz. administratorius koregavo su source = payment), atimsim tik likusius.
     */
    private function packageCredits(Payment $payment): int
    {
        $net = (int) CreditTransaction::query()->whereMorphedTo('source', $payment)->sum('amount');

        return max(0, $net);
    }

    private function forSubscription(Subscription $subscription, int $balance): RefundCalculation
    {
        $now = CarbonImmutable::now();
        $startsAt = CarbonImmutable::instance($subscription->starts_at);
        $endsAt = CarbonImmutable::instance($subscription->ends_at);
        $grantedUntil = $subscription->credits_granted_until === null ? null : CarbonImmutable::instance($subscription->credits_granted_until);

        $periodStart = $this->lastPeriodStart($subscription);

        // Kreditai už laikotarpį suteikiami jam prasidėjus (GrantSubscriptionCredits) – ar jau suteikti?
        $creditsGranted = $grantedUntil !== null && $grantedUntil->greaterThan($periodStart);
        $credits = $creditsGranted ? $subscription->plan->credits_per_period : 0;

        if (! $periodStart->isAfter($now)) {
            // Atimamas laikotarpis jau prasidėjo → prenumerata baigiasi dabar. credits_granted_until = pabaiga,
            // kad Scheduler nesuteiktų kreditų už grąžintą laikotarpį (net jei dar nespėjo jų suteikti)
            $newEndsAt = $endsAt->min($now);

            return $this->credits($credits, $balance, SubscriptionStatus::Expired, $newEndsAt, $newEndsAt);
        }

        if (! $periodStart->isAfter($startsAt)) {
            // Suplanuota (dar neprasidėjusi) prenumerata su vieninteliu laikotarpiu – ji niekada neprasidės
            return $this->credits($credits, $balance, SubscriptionStatus::Expired, $startsAt, $grantedUntil);
        }

        // Iš anksto apmokėtas pratęsimas: ankstesni laikotarpiai lieka, tik nebepratęsiama
        $status = $subscription->status === SubscriptionStatus::Active ? SubscriptionStatus::Cancelled : $subscription->status;

        return $this->credits($credits, $balance, $status, $periodStart, $grantedUntil);
    }

    /**
     * Paskutinio apmokėto laikotarpio pradžia. Ribos skaičiuojamos nuo starts_at tuo pačiu BillingPeriod::addTo(),
     * kaip jas skaičiuoja ActivateSubscription, RenewSubscription ir GrantSubscriptionCredits, todėl sutampa tiksliai
     * (atimti mėnesį nuo ends_at nebūtų tas pats: sausio 31 + 1 mėn. = vasario 28, o vasario 28 − 1 mėn. = sausio 28).
     */
    private function lastPeriodStart(Subscription $subscription): CarbonImmutable
    {
        $period = $subscription->plan->billing_period;
        $endsAt = CarbonImmutable::instance($subscription->ends_at);
        $start = CarbonImmutable::instance($subscription->starts_at);

        // Saugiklis nuo begalinio ciklo sugadintiems duomenims: 1200 laikotarpių = 100 metų mėnesiais
        for ($i = 0; $i < 1200; $i++) {
            $next = $period->addTo($start);

            if (! $next->lessThan($endsAt)) {
                break;
            }

            $start = $next;
        }

        return $start;
    }

    private function credits(
        int $toReverse,
        int $balance,
        ?SubscriptionStatus $status = null,
        ?CarbonImmutable $endsAt = null,
        ?CarbonImmutable $grantedUntil = null,
    ): RefundCalculation {
        $reversed = min($toReverse, max(0, $balance));

        return new RefundCalculation(
            creditsToReverse: $toReverse,
            creditsReversed: $reversed,
            creditsShortfall: $toReverse - $reversed,
            balance: $balance,
            subscriptionStatus: $status,
            subscriptionEndsAt: $endsAt,
            subscriptionCreditsGrantedUntil: $grantedUntil,
        );
    }
}
