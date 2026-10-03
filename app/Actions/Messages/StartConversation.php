<?php

namespace App\Actions\Messages;

use App\Models\Conversation;
use App\Models\Offer;

/**
 * Suranda arba sukuria pasiūlymo pokalbį ir įrašo dalyvius (klientą ir teikėją).
 *
 * createOrFirst(): pirma bando INSERT, o jei UNIQUE(offer_id) jau užimtas (pvz. dvigubas paspaudimas ar
 * abu dalyviai vienu metu), paima esamą eilutę. firstOrCreate() darytų atvirkščiai (SELECT, tada INSERT) ir
 * tarp jų lygiagreti užklausa galėtų spėti įterpti – tada gautume DB klaidą.
 * https://laravel.com/docs/13.x/eloquent#retrieving-or-creating-models
 *
 * Tuščias pokalbis (be žinučių) sąraše nerodomas (last_message_at = NULL), todėl kita pusė jo nemato.
 */
class StartConversation
{
    public function handle(Offer $offer): Conversation
    {
        $offer->loadMissing(['serviceRequest', 'providerProfile']);

        $conversation = Conversation::query()->createOrFirst(
            ['offer_id' => $offer->id],
            ['service_request_id' => $offer->service_request_id],
        );

        // syncWithoutDetaching – idempotentiška: kartojant dalyviai nesidubliuoja, o last_read_message_id nepasikeičia
        $conversation->participants()->syncWithoutDetaching([
            $offer->serviceRequest->client_id,
            $offer->providerProfile->user_id,
        ]);

        return $conversation;
    }
}
