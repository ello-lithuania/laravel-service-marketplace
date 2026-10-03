<?php

namespace App\Policies;

use App\Enums\OfferStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\Conversation;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Kas gali matyti pokalbį, jį pradėti ir rašyti žinutes (Etapas 6).
 *
 * Taisyklės:
 *  - pokalbis priklauso pasiūlymui: dalyviai – užklausos klientas ir pasiūlymą atsiuntęs teikėjas;
 *  - pradėti gali klientas (bet kuriam savo užklausos pasiūlymui, kol galima rašyti), o teikėjas – tik kai
 *    jo pasiūlymas priimtas. Taip teikėjas negali „užversti" kliento žinutėmis – jo prisistatymas yra pasiūlymas;
 *  - rašyti galima, kol pasiūlymas laukia atsakymo atviroje užklausoje, o priimtam pasiūlymui – visada
 *    (susirašinėjimas tęsiasi ir po priėmimo, pvz. dėl garantijos);
 *  - administratorius pokalbį mato (nagrinėdamas skundą), bet rašyti negali.
 */
class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $user->isAdmin() || $conversation->hasParticipant($user);
    }

    public function sendMessage(User $user, Conversation $conversation): Response
    {
        if (! $conversation->hasParticipant($user)) {
            return Response::deny(__($user->isAdmin() ? 'messages.errors.admin_read_only' : 'messages.errors.not_participant'));
        }

        $offer = $conversation->loadMissing('offer.serviceRequest')->offer;

        return $offer !== null && $this->allowsMessaging($offer)
            ? Response::allow()
            : Response::deny(__('messages.errors.closed'));
    }

    /**
     * Pradėti pokalbį dėl pasiūlymo (arba atidaryti jau esamą – StartConversation jo antrą kartą nekuria).
     */
    public function start(User $user, Offer $offer): Response
    {
        $isClient = $offer->loadMissing('serviceRequest')->serviceRequest->client_id === $user->id;
        $isProvider = $user->isProvider() && $user->providerProfile?->id === $offer->provider_profile_id;

        if (! $isClient && ! $isProvider) {
            return Response::deny(__('messages.errors.not_participant'));
        }

        if ($isProvider && $offer->status !== OfferStatus::Accepted) {
            return Response::deny(__('messages.errors.provider_cannot_start'));
        }

        return $this->allowsMessaging($offer)
            ? Response::allow()
            : Response::deny(__('messages.errors.closed'));
    }

    /**
     * Ar dėl šio pasiūlymo dar galima rašyti.
     */
    private function allowsMessaging(Offer $offer): bool
    {
        return $offer->status === OfferStatus::Accepted
            || ($offer->status === OfferStatus::Pending && $offer->serviceRequest->status === ServiceRequestStatus::Open);
    }
}
