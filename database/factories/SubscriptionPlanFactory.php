<?php

namespace Database\Factories;

use App\Enums\BillingPeriod;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Planas '.fake()->unique()->numberBetween(1, 1_000_000);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => null,
            'price_cents' => 1900,
            'billing_period' => BillingPeriod::Month,
            'credits_per_period' => 25,
            'features' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
