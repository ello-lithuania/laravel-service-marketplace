<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Lytis – kad lt_LT Faker parinktų tinkamą pavardės formą (-ienė, -ytė…)
        $gender = fake()->randomElement(['male', 'female']);

        return [
            'role' => UserRole::Client,
            'first_name' => fake()->firstName($gender),
            'last_name' => fake()->lastName($gender),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            // +3700… – tokio Lietuvos numerio būti negali, todėl niekada nepaskambinsim tikram žmogui
            'phone' => '+3700'.fake()->numerify('#######'),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function provider(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Provider]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Admin]);
    }

    public function banned(): static
    {
        return $this->state(fn (array $attributes) => [
            'banned_at' => now(),
            'ban_reason' => 'Taisyklių pažeidimas',
        ]);
    }
}
