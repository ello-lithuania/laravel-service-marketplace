<?php

namespace App\Actions\Subscriptions;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

/**
 * Apmokėtas laikotarpis baigėsi (ends_at praėjo) – kokia būsena dabar:
 *   - active + auto_renew → past_due (malonės laikotarpis, dar galima apmokėti pratęsimą);
 *   - active be auto_renew, cancelled → expired;
 *   - past_due, kai praėjo ir malonės laikotarpis (config payments.subscriptions.grace_days) → expired.
 * Pasibaigus – laukiantys pratęsimo mokėjimai atšaukiami.
 */
class EndSubscriptionPeriod
{
    public function __construct(private readonly CancelPendingRenewalPayments $cancelRenewals) {}

    /**
     * @return SubscriptionStatus|null nauja būsena arba null, jei nieko keisti nereikėjo
     */
    public function handle(Subscription $subscription): ?SubscriptionStatus
    {
        return DB::transaction(function () use ($subscription): ?SubscriptionStatus {
            $locked = Subscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();

            if ($locked->ends_at->isFuture()) {
                return null;
            }

            $graceEnds = $locked->ends_at->addDays((int) config('payments.subscriptions.grace_days'));

            $next = match ($locked->status) {
                SubscriptionStatus::Active => $locked->auto_renew ? SubscriptionStatus::PastDue : SubscriptionStatus::Expired,
                SubscriptionStatus::Cancelled => SubscriptionStatus::Expired,
                SubscriptionStatus::PastDue => $graceEnds->isPast() ? SubscriptionStatus::Expired : null,
                SubscriptionStatus::Expired => null,
            };

            if ($next === null) {
                return null;
            }

            $locked->forceFill(['status' => $next])->save();

            if ($next === SubscriptionStatus::Expired) {
                $this->cancelRenewals->handle($locked);
            }

            $subscription->setRawAttributes($locked->getAttributes(), true);

            return $next;
        });
    }
}
