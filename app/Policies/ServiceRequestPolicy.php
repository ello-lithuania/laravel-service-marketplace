<?php

namespace App\Policies;

use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Matching\ProviderMatcher;

/**
 * Kas ką gali daryti su užklausa. Policy – vieta autorizacijai („ar šis vartotojas gali…"),
 * o Action – verslo logikai („kas nutinka, kai…"). Laravel Policy randa pagal pavadinimą (ServiceRequest → ServiceRequestPolicy).
 * https://laravel.com/docs/13.x/authorization#creating-policies
 *
 * Būsenos sąlygos čia – tam, kad UI rodytų tik galimus mygtukus; galutinai jas dar kartą tikrina Action,
 * užrakinusi eilutę (būsena galėjo pasikeisti tarp puslapio atidarymo ir paspaudimo).
 */
class ServiceRequestPolicy
{
    public function __construct(private readonly ProviderMatcher $matcher) {}

    /**
     * Užklausų sąrašas Filament panelėje.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Klientas – savo; administratorius – visas; teikėjas – atviras jam tinkančias ir tas, kurioms siuntė pasiūlymą.
     */
    public function view(User $user, ServiceRequest $serviceRequest): bool
    {
        if ($user->isAdmin() || $serviceRequest->client_id === $user->id) {
            return true;
        }

        $provider = $user->isProvider() ? $user->providerProfile : null;

        if ($provider === null) {
            return false;
        }

        if ($serviceRequest->offers()->where('provider_profile_id', $provider->id)->exists()) {
            return true;
        }

        return $serviceRequest->status === ServiceRequestStatus::Open
            && $this->matcher->isEligible($provider, $serviceRequest);
    }

    public function create(User $user): bool
    {
        return $user->isClient() && $user->banned_at === null;
    }

    /**
     * Atšaukti: savininkas arba administratorius, kol būsena leidžia (pending, open, in_progress).
     */
    public function cancel(User $user, ServiceRequest $serviceRequest): bool
    {
        return ($serviceRequest->client_id === $user->id || $user->isAdmin())
            && $serviceRequest->status->canTransitionTo(ServiceRequestStatus::Cancelled);
    }

    /**
     * „Darbas atliktas" – tik klientas (docs/STATES.md 1 sk. papildomos taisyklės).
     */
    public function complete(User $user, ServiceRequest $serviceRequest): bool
    {
        return $serviceRequest->client_id === $user->id
            && $serviceRequest->status->canTransitionTo(ServiceRequestStatus::Completed);
    }

    /**
     * Patvirtinti (pending → open) – tik administratorius.
     */
    public function publish(User $user, ServiceRequest $serviceRequest): bool
    {
        return $user->isAdmin() && $serviceRequest->status === ServiceRequestStatus::Pending;
    }
}
