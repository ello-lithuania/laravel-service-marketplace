<?php

namespace Database\Factories;

use App\Enums\ReviewStatus;
use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Numatytoji būsena – paskelbtas atsiliepimas pagal pakvietimą (be užklausos).
 * Patvirtintam – ->verified().
 *
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_request_id' => null,
            'provider_profile_id' => ProviderProfile::factory(),
            'author_id' => User::factory(),
            'rating' => fake()->numberBetween(1, 5),
            'comment' => fake()->randomElement([
                'Puikiai atliktas darbas, rekomenduoju!', 'Viskas gerai, tik šiek tiek vėlavo.',
                'Darbas atliktas kokybiškai ir laiku.',
            ]),
            'status' => ReviewStatus::Published,
            'published_at' => now(),
        ];
    }

    /**
     * Patvirtintas: atlikta užklausa, autorius – jos klientas, teikėjas – priimto pasiūlymo teikėjas.
     */
    public function verified(): static
    {
        return $this->state(function (array $attributes) {
            $request = ServiceRequest::factory()->completed()->create();
            $request->load('acceptedOffer');

            return [
                'service_request_id' => $request->id,
                'provider_profile_id' => $request->acceptedOffer?->provider_profile_id,
                'author_id' => $request->client_id,
            ];
        });
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ReviewStatus::Hidden]);
    }

    public function withReply(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider_reply' => 'Ačiū už atsiliepimą!',
            'provider_replied_at' => now(),
        ]);
    }
}
