<?php

namespace Tests\Support;

use App\Actions\Messages\SendMessage;
use App\Actions\Messages\StartConversation;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Offer;
use App\Models\ServiceRequest;
use App\Models\User;

/**
 * Etapo 6 testų pagalbininkai: pasiūlymas su pokalbiu, žinutės.
 */
final class Messaging
{
    /**
     * Atviros užklausos laukiantis pasiūlymas. $state – pvz. 'accepted' (užklausa tampa vykdoma).
     */
    public static function offer(string $state = 'pending'): Offer
    {
        $request = ServiceRequest::factory()->create();
        $provider = Marketplace::eligibleProvider($request);
        $offer = Offer::factory()->for($request)->for($provider)->create();

        if ($state === 'accepted') {
            $offer->forceFill(['status' => 'accepted', 'responded_at' => now()])->save();
            $request->forceFill(['status' => 'in_progress', 'accepted_offer_id' => $offer->id])->save();
        }

        return $offer->load(['serviceRequest.client', 'providerProfile.user']);
    }

    public static function client(Offer $offer): User
    {
        return $offer->serviceRequest->client;
    }

    public static function provider(Offer $offer): User
    {
        return $offer->providerProfile->user;
    }

    public static function conversation(Offer $offer): Conversation
    {
        return app(StartConversation::class)->handle($offer);
    }

    public static function send(Conversation $conversation, User $sender, string $body = 'Laba diena, ar galite atvykti rytoj?'): Message
    {
        return app(SendMessage::class)->handle($conversation, $sender, $body);
    }
}
