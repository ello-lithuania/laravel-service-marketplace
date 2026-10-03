<?php

namespace Database\Factories;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\CreditPackage;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Numatytoji būsena – apmokėtas kreditų paketas.
 *
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->provider(),
            'gateway' => PaymentGateway::Paysera,
            'gateway_reference' => 'TEST-'.fake()->unique()->numerify('##########'),
            // Polimorfinis ryšys: tipas – trumpas vardas iš morph map
            'purchasable_type' => 'credit_package',
            'purchasable_id' => CreditPackage::factory(),
            'amount_cents' => 2690,
            'currency' => 'EUR',
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Pending,
            'gateway_reference' => null,
            'paid_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Failed,
            'paid_at' => null,
        ]);
    }
}
