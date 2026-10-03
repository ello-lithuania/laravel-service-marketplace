<?php

namespace Database\Seeders\Demo;

use App\Enums\UserRole;
use Generator;

/**
 * Klientų paskyros. Registracijos data paskaičiuojama pagal pirmą užklausą (klientas užsiregistruoja anksčiau).
 */
final class ClientGenerator
{
    public function __construct(private readonly DemoContext $ctx) {}

    public function plan(): void
    {
        for ($i = 0; $i < $this->ctx->counts['clients']; $i++) {
            $this->ctx->clientCity[] = $this->ctx->cityPicker->pick();
        }
    }

    /**
     * Registracijos datos: prieš pirmą užklausą; klientams be užklausų – bet kada per 36 mėn.
     */
    public function assignDates(): void
    {
        $c = $this->ctx;
        $first = array_fill(0, $c->counts['clients'], PHP_INT_MAX);

        foreach ($c->reqClient as $r => $client) {
            $first[$client] = min($first[$client], $c->reqCreated[$r]);
        }

        foreach ($first as $client => $firstRequest) {
            $c->clientCreated[$client] = $firstRequest === PHP_INT_MAX
                ? $c->growthTime($c->start, $c->now - DemoContext::HOUR)
                : $c->growthTime($c->start, $firstRequest - 10 * DemoContext::MINUTE);
        }
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function users(): Generator
    {
        $c = $this->ctx;

        foreach ($c->clientCity as $client => $city) {
            $id = $c->clientBase + $client;
            $gender = $c->chance(0.5) ? 'male' : 'female';
            $created = $c->clientCreated[$client];
            // 3 % dar nepatvirtino el. pašto
            $verified = $c->chance(0.97) ? $created + $c->between(60, DemoContext::DAY) : 0;

            yield [
                'id' => $id,
                'role' => UserRole::Client->value,
                'first_name' => $c->faker->firstName($gender),
                'last_name' => $c->faker->lastName($gender),
                'email' => 'klientas'.($client + 1).'@example.test',
                'email_verified_at' => $c->nullableDate(min($verified, $c->now)),
                'phone' => $c->chance(0.6) ? sprintf('+3700%07d', $id) : null,
                'password' => $c->passwordHash,
                'city_id' => $city,
                'last_seen_at' => $c->date($c->growthTime($created, $c->now)),
                'created_at' => $c->date($created),
                'updated_at' => $c->date($created),
            ];
        }
    }
}
