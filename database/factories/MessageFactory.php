<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'sender_id' => User::factory(),
            'body' => fake()->randomElement([
                'Ar galėtumėte atvykti rytoj po 17 val.?', 'Taip, tinka. Iki pasimatymo!',
                'Kiek užtruktų darbai?', 'Ačiū, susitarta.',
            ]),
        ];
    }

    /**
     * Sisteminė žinutė („Pasiūlymas priimtas") – be siuntėjo.
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => ['sender_id' => null]);
    }
}
