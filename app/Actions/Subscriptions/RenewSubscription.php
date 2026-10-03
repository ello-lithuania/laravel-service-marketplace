<?php

namespace App\Actions\Subscriptions;

use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\ProviderProfile;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

/**
 * Apmokėtas pratęsimo mokėjimas → ends_at pastumiamas vienu laikotarpiu, prenumerata vėl aktyvi.
 *
 * - Apmokėta iš anksto (prieš pabaigą) → kreditus už naują laikotarpį suteiks Scheduler, kai jis prasidės.
 * - Apmokėta per malonės laikotarpį (past_due) → naujas laikotarpis jau prasidėjo, kreditai suteikiami iškart.
 *   Laikotarpis skaičiuojamas nuo senos pabaigos (ne nuo apmokėjimo), kaip ir Stripe – vėlavimas „nedovanojamas".
 * - Prenumerata jau pasibaigė arba po jos suplanuotas kitas planas → pratęsti nebėra ko, todėl mokėjimas laikomas
 *   nauju plano pirkimu (ActivateSubscription) – sumokėti pinigai nepradingsta.
 */
class RenewSubscription
{
    public function __construct(
        private readonly ActivateSubscription $activate,
        private readonly GrantSubscriptionCredits $grantCredits,
    ) {}

    public function handle(Payment $payment, Subscription $subscription): Subscription
    {
        return DB::transaction(function () use ($payment, $subscription): Subscription {
            ProviderProfile::withTrashed()->whereKey($subscription->provider_profile_id)->lockForUpdate()->firstOrFail();
            $locked = Subscription::query()->with('plan')->whereKey($subscription->id)->lockForUpdate()->firstOrFail();

            $hasSuccessor = Subscription::query()
                ->where('provider_profile_id', $locked->provider_profile_id)
                ->live()
                ->whereKeyNot($locked->id)
                ->where('starts_at', '>=', $locked->ends_at)
                ->exists();

            if ($locked->status === SubscriptionStatus::Expired || $hasSuccessor) {
                return $this->activate->handle($payment, $locked->plan);
            }

            $locked->forceFill([
                'status' => SubscriptionStatus::Active,
                'ends_at' => $locked->plan->billing_period->addTo($locked->ends_at),
                'auto_renew' => true,
                'cancelled_at' => null,
            ])->save();

            $this->grantCredits->handle($locked);

            return $locked;
        });
    }
}
