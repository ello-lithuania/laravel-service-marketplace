<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Teikėjas perka prenumeratos planą: sukuriamas laukiantis mokėjimas. Prenumerata sukuriama tik apmokėjus
 * (CompletePayment → ActivateSubscription).
 *
 * Plano keitimas paprastas: naujas planas prasideda, kai baigiasi dabartinis (docs/DB_SCHEMA.md → subscriptions).
 * Todėl draudžiam tik tai, kas būtų beprasmiška: pirkti tą patį automatiškai pratęsiamą planą dar kartą
 * arba trečią planą, kai vienas jau laukia savo eilės.
 */
class PurchaseSubscriptionPlan
{
    public function __construct(private readonly CreatePayment $createPayment) {}

    /**
     * @throws ValidationException kai planas nebeparduodamas arba toks pirkimas beprasmiškas
     */
    public function handle(User $user, SubscriptionPlan $plan): Payment
    {
        if (! $plan->is_active) {
            throw ValidationException::withMessages(['purchase' => __('billing.errors.plan_inactive')]);
        }

        $subscriptions = $user->providerProfile()->firstOrFail()
            ->subscriptions()->live()->with('plan')->get();

        $scheduled = $subscriptions->first(fn (Subscription $subscription): bool => $subscription->isScheduled());

        if ($scheduled !== null) {
            throw ValidationException::withMessages(['purchase' => __('billing.errors.plan_scheduled', [
                'plan' => $scheduled->plan->name,
                'date' => $scheduled->starts_at->timezone('Europe/Vilnius')->format('Y-m-d'),
            ])]);
        }

        $current = $subscriptions->first(fn (Subscription $subscription): bool => $subscription->isCurrent());

        if ($current !== null && $current->auto_renew && $current->subscription_plan_id === $plan->id) {
            throw ValidationException::withMessages(['purchase' => __('billing.errors.plan_already_active')]);
        }

        return $this->createPayment->handle($user, $plan);
    }
}
