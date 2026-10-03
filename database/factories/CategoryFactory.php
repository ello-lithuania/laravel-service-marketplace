<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Numatytoji būsena – 1 lygio kategorija. Užklausoms naudok ->leaf() (3 lygis).
 *
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement(['Remontas', 'Valymas', 'Santechnika', 'Elektra', 'Apdaila'])
            .' '.fake()->unique()->numberBetween(1, 1_000_000);

        return [
            'parent_id' => null,
            'depth' => 1,
            'name' => $name,
            'slug' => Str::slug($name),
            'offer_cost_credits' => 1,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function childOf(Category $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->id,
            'depth' => $parent->depth + 1,
        ]);
    }

    /**
     * 3 lygio kategorija kartu su tėvu (2 lygis) ir seneliu (1 lygis).
     */
    public function leaf(): static
    {
        return $this->state(fn (array $attributes) => [
            'depth' => 3,
            'parent_id' => Category::factory()->state([
                'depth' => 2,
                'parent_id' => Category::factory(),
            ]),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
