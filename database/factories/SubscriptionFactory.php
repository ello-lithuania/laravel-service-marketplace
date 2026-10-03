<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\ProviderProfile;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider_profile_id' => ProviderProfile::factory(),
            'subscription_plan_id' => SubscriptionPlan::factory(),
            'status' => SubscriptionStatus::Active,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->addDays(20),
            'cancelled_at' => null,
            'auto_renew' => true,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::Expired,
            'starts_at' => now()->subDays(70),
            'ends_at' => now()->subDays(10),
            'auto_renew' => false,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
            'auto_renew' => false,
        ]);
    }
}
