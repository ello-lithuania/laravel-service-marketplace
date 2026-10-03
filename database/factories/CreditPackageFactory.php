<?php

namespace Database\Factories;

use App\Models\CreditPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditPackage>
 */
class CreditPackageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $credits = fake()->randomElement([10, 30, 60, 120]);

        return [
            'name' => $credits.' kreditų',
            'credits' => $credits,
            'bonus_credits' => 0,
            'price_cents' => $credits * 95,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
