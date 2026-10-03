<?php

namespace App\Actions\Subscriptions;

use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\ProviderProfile;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Apmokėtas plano pirkimas → nauja prenumerata (docs/DB_SCHEMA.md → subscriptions).
 *
 * Teikėjas vienu metu turi ne daugiau kaip vieną galiojančią prenumeratą:
 *   - nėra galiojančios → nauja prasideda dabar, kreditai už pirmą laikotarpį suteikiami iškart;
 *   - yra galiojanti (plano keitimas) → nauja prasideda, kai baigiasi dabartinė, o dabartinė atšaukiama
 *     (nebepratęsiama). Kreditus už naują laikotarpį jam prasidėjus suteiks Scheduler (subscriptions:grant-credits);
 *   - yra pasibaigusi, bet dar neapmokėta (past_due) → ji baigiama (expired), nauja prasideda dabar.
 * Teikėjo eilutė užrakinama, todėl du vienu metu apmokėti planai nesukurs dviejų „dabartinių" prenumeratų.
 */
class ActivateSubscription
{
    public function __construct(
        private readonly GrantSubscriptionCredits $grantCredits,
        private readonly CancelPendingRenewalPayments $cancelRenewals,
    ) {}

    public function handle(Payment $payment, SubscriptionPlan $plan): Subscription
    {
        return DB::transaction(function () use ($payment, $plan): Subscription {
            $provider = ProviderProfile::withTrashed()->where('user_id', $payment->user_id)->lockForUpdate()->first()
                ?? throw new LogicException("Apmokėtam planui (mokėjimas {$payment->uuid}) nerastas teikėjo profilis.");

            $now = now();
            $startsAt = $now;

            $live = Subscription::query()
                ->whereBelongsTo($provider)
                ->live()
                ->orderBy('starts_at')
                ->lockForUpdate()
                ->get();

            foreach ($live as $current) {
                if ($current->ends_at->lessThanOrEqualTo($now)) {
                    // Laikotarpis jau baigėsi (past_due arba Scheduler dar nespėjo pažymėti) – naujas planas ją pakeičia
                    $current->forceFill(['status' => SubscriptionStatus::Expired, 'auto_renew' => false])->save();
                    $this->cancelRenewals->handle($current, $payment);

                    continue;
                }

                // Dar galioja – nauja prasidės po jos, o ji pati nebebus pratęsiama
                $startsAt = $current->ends_at->max($startsAt);

                if ($current->status === SubscriptionStatus::Active) {
                    $current->forceFill([
                        'status' => SubscriptionStatus::Cancelled,
                        'auto_renew' => false,
                        'cancelled_at' => $now,
                    ])->save();
                    $this->cancelRenewals->handle($current, $payment);
                }
            }

            $subscription = new Subscription([
                'subscription_plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'starts_at' => $startsAt,
                'ends_at' => $plan->billing_period->addTo($startsAt),
                'auto_renew' => true,
            ]);
            $subscription->providerProfile()->associate($provider);
            $subscription->save();

            $payment->subscription()->associate($subscription)->save();

            // Prasidėjo dabar → kreditai iškart; prasidės vėliau → GrantSubscriptionCredits nieko nedarys (dar ne laikas)
            $this->grantCredits->handle($subscription);

            return $subscription;
        });
    }
}
