<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Offer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pokalbis dėl pasiūlymo; dalyviai (klientas ir teikėjas) pridedami automatiškai.
 *
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'offer_id' => Offer::factory(),
            // Atributo funkcija gauna jau sukurtą offer_id ir iš jo paima užklausą
            'service_request_id' => fn (array $attributes) => $attributes['offer_id'] === null
                ? null
                : Offer::query()->whereKey($attributes['offer_id'])->value('service_request_id'),
            'last_message_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Conversation $conversation) {
            $offer = $conversation->offer()->with(['serviceRequest', 'providerProfile'])->first();

            if ($offer === null) {
                return;
            }

            $conversation->participants()->syncWithoutDetaching([
                $offer->serviceRequest->client_id,
                $offer->providerProfile->user_id,
            ]);
        });
    }

    /**
     * Palaikymo pokalbis be pasiūlymo.
     */
    public function support(): static
    {
        return $this->state(fn (array $attributes) => [
            'offer_id' => null,
            'service_request_id' => null,
        ]);
    }
}
