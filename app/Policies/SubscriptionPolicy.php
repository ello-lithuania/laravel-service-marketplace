<?php

namespace App\Policies;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;

/**
 * Prenumeratos: sąrašas – administratoriui (Filament), atšaukti – savininkui teikėjui arba administratoriui.
 */
class SubscriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Subscription $subscription): bool
    {
        return $user->isAdmin() || $this->owns($user, $subscription);
    }

    /**
     * Atšaukti galima aktyvią (galios iki pabaigos) arba nesumokėtą (baigsis iškart). Būseną dar kartą,
     * užrakinusi eilutę, patikrina CancelSubscription.
     */
    public function cancel(User $user, Subscription $subscription): bool
    {
        return ($user->isAdmin() || $this->owns($user, $subscription))
            && in_array($subscription->status, [SubscriptionStatus::Active, SubscriptionStatus::PastDue], true);
    }

    private function owns(User $user, Subscription $subscription): bool
    {
        return $user->isProvider()
            && $user->providerProfile !== null
            && $subscription->provider_profile_id === $user->providerProfile->id;
    }
}
