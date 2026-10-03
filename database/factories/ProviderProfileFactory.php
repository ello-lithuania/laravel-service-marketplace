<?php

namespace Database\Factories;

use App\Enums\ProviderStatus;
use App\Enums\ProviderType;
use App\Models\City;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Numatytoji būsena – aktyvus fizinio asmens profilis.
 *
 * @extends Factory<ProviderProfile>
 */
class ProviderProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->name();

        return [
            'user_id' => User::factory()->provider(),
            'type' => ProviderType::Individual,
            'display_name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 10_000_000),
            'headline' => fake()->randomElement([
                'Kokybiškai ir laiku', 'Daugiau nei 10 metų patirtis', 'Dirbu savaitgaliais', 'Suteikiu garantiją',
            ]),
            'description' => 'Atlieku darbus kruopščiai ir laikausi sutartų terminų.',
            'city_id' => City::factory(),
            'company_code' => null,
            'vat_code' => null,
            'website' => null,
            'years_experience' => fake()->numberBetween(1, 30),
            'serves_whole_country' => false,
            'status' => ProviderStatus::Active,
            'verified_at' => null,
            'credits_balance' => 0,
            'rating_avg' => 0,
            'reviews_count' => 0,
            'completed_jobs_count' => 0,
        ];
    }

    public function company(): static
    {
        return $this->state(function (array $attributes) {
            $name = fake()->company();

            return [
                'type' => ProviderType::Company,
                'display_name' => $name,
                'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 10_000_000),
                // 999… – akivaizdžiai netikri kodai (docs/SEEDING.md 6 sk.)
                'company_code' => '999'.fake()->numerify('######'),
                'vat_code' => 'LT999'.fake()->numerify('#######'),
            ];
        });
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ProviderStatus::Pending]);
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ProviderStatus::Hidden]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ProviderStatus::Suspended]);
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => ['verified_at' => now()]);
    }

    public function wholeCountry(): static
    {
        return $this->state(fn (array $attributes) => ['serves_whole_country' => true]);
    }

    public function withCredits(int $credits): static
    {
        return $this->state(fn (array $attributes) => ['credits_balance' => $credits]);
    }
}
