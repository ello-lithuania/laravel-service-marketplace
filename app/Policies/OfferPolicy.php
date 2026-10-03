<?php

namespace App\Policies;

use App\Enums\OfferStatus;
use App\Enums\ProviderStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\Offer;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Matching\ProviderMatcher;
use Illuminate\Auth\Access\Response;

/**
 * Kas gali siųsti, matyti, atšaukti, priimti ir atmesti pasiūlymus (docs/STATES.md 2 sk.).
 * Response::deny('…') – atsisakymas su priežastimi, kurią parodom vartotojui.
 */
class OfferPolicy
{
    public function __construct(private readonly ProviderMatcher $matcher) {}

    /**
     * Ar teikėjas gali siųsti pasiūlymą šiai užklausai. Kreditų čia netikrinam: tai ne teisė, o likutis –
     * apie trūkumą pasako SendOffer aiškiu pranešimu (ir pasiūlo jų įsigyti).
     */
    public function create(User $user, ServiceRequest $serviceRequest): Response
    {
        $provider = $user->isProvider() ? $user->providerProfile : null;

        if ($provider === null) {
            return Response::deny(__('offers.errors.not_provider'));
        }

        if ($provider->status !== ProviderStatus::Active) {
            return Response::deny(__('offers.errors.provider_inactive'));
        }

        if ($serviceRequest->status !== ServiceRequestStatus::Open) {
            return Response::deny(__('offers.errors.request_not_open'));
        }

        if ($serviceRequest->offers()->where('provider_profile_id', $provider->id)->exists()) {
            return Response::deny(__('offers.errors.duplicate'));
        }

        return $this->matcher->isEligible($provider, $serviceRequest)
            ? Response::allow()
            : Response::deny(__('offers.errors.not_eligible'));
    }

    /**
     * Pasiūlymą mato užklausos klientas, jį išsiuntęs teikėjas ir administratorius.
     */
    public function view(User $user, Offer $offer): bool
    {
        return $user->isAdmin() || $this->isClientOf($user, $offer) || $this->isAuthorOf($user, $offer);
    }

    public function withdraw(User $user, Offer $offer): bool
    {
        return $this->isAuthorOf($user, $offer) && $this->isOpenForAnswer($offer);
    }

    public function accept(User $user, Offer $offer): bool
    {
        return $this->isClientOf($user, $offer) && $this->isOpenForAnswer($offer);
    }

    public function decline(User $user, Offer $offer): bool
    {
        return $this->isClientOf($user, $offer) && $this->isOpenForAnswer($offer);
    }

    private function isClientOf(User $user, Offer $offer): bool
    {
        return $offer->serviceRequest->client_id === $user->id;
    }

    private function isAuthorOf(User $user, Offer $offer): bool
    {
        return $user->isProvider() && $user->providerProfile?->id === $offer->provider_profile_id;
    }

    /**
     * Pasiūlymas laukia atsakymo, o užklausa dar renkasi.
     */
    private function isOpenForAnswer(Offer $offer): bool
    {
        return $offer->status === OfferStatus::Pending
            && $offer->serviceRequest->status === ServiceRequestStatus::Open;
    }
}
