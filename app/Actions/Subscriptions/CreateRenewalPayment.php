<?php

namespace App\Actions\Subscriptions;

use App\Actions\Payments\CreatePayment;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

/**
 * Pratęsimo mokėjimas automatiškai pratęsiamai prenumeratai.
 *
 * Paysera (mūsų sąrankoje) kortelės automatiškai nenuskaito, todėl „automatinis pratęsimas" = sukuriam laukiantį
 * mokėjimą ir nusiunčiam teikėjui nuorodą jam apmokėti (SubscriptionExpiring). Idempotentiška: jei laukiantis
 * pratęsimo mokėjimas jau yra, naujas nekuriamas (grąžinamas null).
 */
class CreateRenewalPayment
{
    public function __construct(private readonly CreatePayment $createPayment) {}

    public function handle(Subscription $subscription): ?Payment
    {
        return DB::transaction(function () use ($subscription): ?Payment {
            $locked = Subscription::query()
                ->with(['plan', 'providerProfile.user'])
                ->whereKey($subscription->id)
                ->lockForUpdate()
                ->firstOrFail();

            $renewable = $locked->auto_renew
                && in_array($locked->status, [SubscriptionStatus::Active, SubscriptionStatus::PastDue], true);

            // Profilis „ištrintas" (soft delete) – ryšys grąžina null, pratęsimo nesiūlom
            if (! $renewable || $locked->providerProfile === null) {
                return null;
            }

            if ($locked->payments()->where('status', PaymentStatus::Pending)->exists()) {
                return null;
            }

            return $this->createPayment->handle($locked->providerProfile->user, $locked->plan, $locked);
        });
    }
}
