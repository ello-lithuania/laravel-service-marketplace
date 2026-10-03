<?php

namespace Database\Factories;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\ProviderProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditTransaction>
 */
class CreditTransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider_profile_id' => ProviderProfile::factory(),
            'amount' => 5,
            'balance_after' => 5,
            'type' => CreditTransactionType::Bonus,
            'source_type' => null,
            'source_id' => null,
            'description' => 'Dovana naujam teikėjui',
        ];
    }
}
