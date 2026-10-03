<?php

namespace Database\Factories;

use App\Models\PortfolioItem;
use App\Models\ProviderProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PortfolioItem>
 */
class PortfolioItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider_profile_id' => ProviderProfile::factory(),
            'category_id' => null,
            'city_id' => null,
            'title' => fake()->randomElement([
                'Vonios kambario renovacija', 'Virtuvės baldų montavimas', 'Fasado šiltinimas', 'Kiemo trinkelės',
            ]),
            'description' => 'Darbai atlikti per sutartą laiką.',
            'completed_date' => fake()->dateTimeBetween('-3 years', '-1 week'),
            'sort_order' => 0,
        ];
    }
}
