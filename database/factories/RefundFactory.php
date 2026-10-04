<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Grąžinimas su kreditine sąskaita (Etapas 9). Tikrai eigai (kreditai, prenumerata, numeracija) testuose
 * naudok RefundPayment – factory tik paruošia duomenis PDF ir puslapių testams.
 *
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory()->state([
                'status' => PaymentStatus::Refunded,
                'invoice_number' => fn () => 'SF-2026-'.fake()->unique()->numerify('9#####'),
            ]),
            'refunded_by_id' => null,
            'amount_cents' => fn (array $attributes) => Payment::query()->whereKey($attributes['payment_id'])->value('amount_cents') ?? 2690,
            'reason' => 'Teikėjas paprašė grąžinti per klaidą nupirktą paketą.',
            'credits_reversed' => 30,
            'credits_shortfall' => 0,
            'credit_note_number' => fn () => 'KS-2026-'.fake()->unique()->numerify('9#####'),
            'billing_details' => [
                'seller' => ['name' => 'Platforma', 'company_code' => '000000000', 'vat_code' => null, 'address' => 'Gatvė 1, LT-00000 Vilnius', 'email' => null, 'bank_account' => null],
                'buyer' => ['name' => 'Petras Petraitis', 'company_code' => null, 'vat_code' => null, 'address' => 'Vilnius', 'email' => 'petras@example.test'],
                'vat_payer' => false,
                'vat_rate' => 21,
            ],
        ];
    }
}
