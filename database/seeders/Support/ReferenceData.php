<?php

namespace Database\Seeders\Support;

/**
 * Žinyniniai duomenys iš database/data/*.php su aprašyta struktūra (kad PHPStan žinotų tipus).
 */
class ReferenceData
{
    /**
     * Apskritis => [[pavadinimas, vietininkas, platuma, ilguma, gyventojai], …].
     *
     * @return array<string, list<array{0: string, 1: string, 2: float, 3: float, 4: int}>>
     */
    public static function cities(): array
    {
        return require database_path('data/cities.php');
    }

    /**
     * @return list<array{name: string, icon: string, weight: int, budget: array{0: int, 1: int}, cost: int, children: list<array{name: string, weight: int, cost?: int, children: list<string|array{name: string, cost?: int}>}>}>
     */
    public static function categories(): array
    {
        return require database_path('data/categories.php');
    }

    /**
     * @return array{
     *     credit_packages: list<array{name: string, credits: int, bonus_credits: int, price_cents: int}>,
     *     subscription_plans: list<array{name: string, slug: string, description: string, price_cents: int, credits_per_period: int, features: array<string, bool|int>}>
     * }
     */
    public static function monetization(): array
    {
        return require database_path('data/monetization.php');
    }
}
