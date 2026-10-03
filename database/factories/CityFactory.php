<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<City>
 */
class CityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->city();

        return [
            'region_id' => Region::factory(),
            'name' => $name,
            'name_locative' => $name,
            // Faker miestų sąrašas trumpas, todėl slug unikalumui pridedam skaičių
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 1_000_000),
            'latitude' => fake()->latitude(53.9, 56.4),
            'longitude' => fake()->longitude(21.0, 26.8),
            'sort_order' => 0,
        ];
    }
}
