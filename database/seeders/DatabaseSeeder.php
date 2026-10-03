<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // Seed'o metu nevykdomi model events (observeriai, pranešimai) – žr. docs/SEEDING.md 7 sk.
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Žinyniniai duomenys – visada pilni, nepriklausomai nuo SEED_SCALE
        $this->call([
            GeographySeeder::class,
            CategorySeeder::class,
            CreditPackageSeeder::class,
            SubscriptionPlanSeeder::class,
            AdminSeeder::class,
        ]);
    }
}
