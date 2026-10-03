<?php

namespace App\Actions\Subscriptions;

use App\Enums\SubscriptionStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

/**
 * Prenumeratos atšaukimas (teikėjas arba administratorius).
 *
 * Pinigai už einamąjį laikotarpį negrąžinami, todėl aktyvi prenumerata galioja iki ends_at (būsena cancelled,
 * auto_renew = false), o tada Scheduler ją pažymi expired. Nesumokėta (past_due) baigiama iškart.
 * Laukiantys pratęsimo mokėjimai atšaukiami.
 */
class CancelSubscription
{
    public function __construct(private readonly CancelPendingRenewalPayments $cancelRenewals) {}

    /**
     * @throws InvalidStateTransitionException kai prenumerata jau atšaukta ar pasibaigusi
     */
    public function handle(Subscription $subscription): Subscription
    {
        return DB::transaction(function () use ($subscription): Subscription {
            $locked = Subscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();

            $next = match ($locked->status) {
                SubscriptionStatus::Active => SubscriptionStatus::Cancelled,
                SubscriptionStatus::PastDue => SubscriptionStatus::Expired,
                default => throw InvalidStateTransitionException::for($locked->status, SubscriptionStatus::Cancelled, 'Prenumeratos'),
            };

            $locked->forceFill(['status' => $next, 'auto_renew' => false, 'cancelled_at' => now()])->save();
            $this->cancelRenewals->handle($locked);

            $subscription->setRawAttributes($locked->getAttributes(), true);

            return $subscription;
        });
    }
}
