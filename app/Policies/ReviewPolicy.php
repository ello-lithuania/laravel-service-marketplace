<?php

namespace App\Policies;

use App\Enums\ProviderStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Kas gali rašyti, atsakyti ir moderuoti atsiliepimus (Etapas 6).
 *
 * Du atsiliepimų tipai (docs/DB_SCHEMA.md → reviews):
 *  - patvirtintas – po per platformą atlikto darbo (service_request_id užpildytas), tik užklausos klientas,
 *    vieną kartą, per Review::VERIFIED_WINDOW_DAYS d.;
 *  - pagal pakvietimą – buvęs klientas ne iš platformos atidaro teikėjo pasirašytą nuorodą (service_request_id NULL).
 *    Piktnaudžiavimo saugikliai: tik klientai, ne savo profiliui, vienas tam pačiam teikėjui per 12 mėn.,
 *    o paskelbia administratorius (būsena „pending").
 */
class ReviewPolicy
{
    // --- Administratorius (Filament ReviewResource) ---

    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Review $review): bool
    {
        return $user->isAdmin();
    }

    /**
     * Paskelbti / paslėpti.
     */
    public function moderate(User $user, Review $review): bool
    {
        return $user->isAdmin();
    }

    // --- Klientas ---

    public function createVerified(User $user, ServiceRequest $serviceRequest): Response
    {
        if ($serviceRequest->client_id !== $user->id) {
            return Response::deny(__('reviews.errors.not_owner'));
        }

        if ($serviceRequest->status !== ServiceRequestStatus::Completed || $serviceRequest->accepted_offer_id === null) {
            return Response::deny(__('reviews.errors.not_completed'));
        }

        if ($serviceRequest->completed_at === null || $serviceRequest->completed_at->lt(now()->subDays(Review::VERIFIED_WINDOW_DAYS))) {
            return Response::deny(__('reviews.errors.window_passed', ['days' => Review::VERIFIED_WINDOW_DAYS]));
        }

        return $serviceRequest->review()->exists()
            ? Response::deny(__('reviews.errors.already_reviewed'))
            : Response::allow();
    }

    public function createFromInvitation(User $user, ProviderProfile $provider): Response
    {
        if ($provider->user_id === $user->id) {
            return Response::deny(__('reviews.errors.own_profile'));
        }

        if (! $user->isClient()) {
            return Response::deny(__('reviews.errors.clients_only'));
        }

        if ($user->banned_at !== null) {
            return Response::deny(__('reviews.errors.banned'));
        }

        if ($provider->status !== ProviderStatus::Active) {
            return Response::deny(__('reviews.errors.provider_inactive'));
        }

        // Bet koks (ir patvirtintas) to paties autoriaus atsiliepimas šiam teikėjui per pastaruosius 12 mėn.
        $recent = $provider->reviews()
            ->where('author_id', $user->id)
            ->where('created_at', '>=', now()->subDays(Review::INVITATION_COOLDOWN_DAYS))
            ->exists();

        return $recent ? Response::deny(__('reviews.errors.recently_reviewed')) : Response::allow();
    }

    // --- Teikėjas ---

    /**
     * Atsakyti galima vieną kartą ir tik į paskelbtą atsiliepimą apie save.
     */
    public function reply(User $user, Review $review): Response
    {
        $isOwner = $user->isProvider() && $user->providerProfile?->id === $review->provider_profile_id;

        return $isOwner && $review->isPublished() && $review->provider_reply === null
            ? Response::allow()
            : Response::deny(__('reviews.errors.reply_not_allowed'));
    }
}
