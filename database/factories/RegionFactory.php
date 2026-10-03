<?php

namespace Database\Factories;

use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Region>
 */
class RegionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Apskritis '.fake()->unique()->numberBetween(1, 1_000_000);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'sort_order' => 0,
        ];
    }
}
