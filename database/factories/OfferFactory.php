<?php

namespace Database\Factories;

use App\Enums\OfferPriceType;
use App\Enums\OfferStatus;
use App\Models\Offer;
use App\Models\ProviderProfile;
use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_request_id' => ServiceRequest::factory(),
            'provider_profile_id' => ProviderProfile::factory(),
            'message' => 'Laba diena, galiu atvykti apžiūrėti jau šią savaitę.',
            'price_cents' => fake()->numberBetween(20, 2000) * 100,
            'price_type' => OfferPriceType::Fixed,
            'duration_text' => fake()->randomElement(['1 diena', '2–3 darbo dienos', 'Savaitė']),
            'start_date' => null,
            'status' => OfferStatus::Pending,
            'credits_spent' => 1,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OfferStatus::Accepted,
            'viewed_at' => now(),
            'responded_at' => now(),
        ]);
    }

    public function declined(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OfferStatus::Declined,
            'responded_at' => now(),
        ]);
    }

    public function withdrawn(): static
    {
        return $this->state(fn (array $attributes) => ['status' => OfferStatus::Withdrawn]);
    }
}
