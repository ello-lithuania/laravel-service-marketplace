<?php

namespace App\Support;

use App\Models\Conversation;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * „Rašyti žinutę" mygtukas pasiūlymo vietose (Etapas 6): ar pokalbis jau yra ir ar jį galima pradėti.
 * Viena vieta, kurią naudoja kliento užklausos ir pasiūlymo puslapiai bei teikėjo užklausos puslapis.
 *
 * Pasiūlymui iš anksto užkrauk ryšį conversation (->with('conversation')), kad sąraše nebūtų N+1.
 */
final class OfferMessaging
{
    /**
     * @return array{conversation_id: int|null, can_start: bool}
     */
    public static function for(User $user, Offer $offer): array
    {
        /** @var Conversation|null $conversation */
        $conversation = $offer->relationLoaded('conversation') ? $offer->conversation : $offer->conversation()->first();
        $canStart = Gate::forUser($user)->allows('start', [Conversation::class, $offer]);

        // Tuščią pokalbį (klientas atidarė, bet neparašė) teikėjas mato tik tada, kai pats gali rašyti.
        // Dalyvių atskirai netikrinam: šią klasę kviečia tik pasiūlymo klientas, teikėjas ar administratorius
        $visible = $conversation !== null && ($conversation->last_message_at !== null || $canStart);

        return [
            'conversation_id' => $visible ? $conversation->id : null,
            'can_start' => $canStart,
        ];
    }
}
