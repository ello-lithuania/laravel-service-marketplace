<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\ProviderProfile;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Numatytoji būsena – aktyvi prenumerata, kreditai už einamąjį laikotarpį jau suteikti.
 *
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
            // Etapas 7: kaip seed'uose – kreditai suteikti iki apmokėto laikotarpio pabaigos
            'credits_granted_until' => fn (array $attributes) => $attributes['ends_at'],
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

    // --- Etapas 7 ---

    /**
     * Laikotarpis baigėsi, pratęsimas neapmokėtas – malonės laikotarpis.
     */
    public function pastDue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::PastDue,
            'starts_at' => now()->subDays(31),
            'ends_at' => now()->subDay(),
        ]);
    }

    /**
     * Ką tik nupirkta: kreditai dar nesuteikti nė už vieną laikotarpį.
     */
    public function withoutGrantedCredits(): static
    {
        return $this->state(fn (array $attributes) => ['credits_granted_until' => null]);
    }
}
