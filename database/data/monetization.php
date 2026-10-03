<?php

/**
 * Kreditų paketai ir prenumeratų planai (kainos sugalvotos, sveikais centais – docs/DB_SCHEMA.md 2.7).
 */

return [
    'credit_packages' => [
        ['name' => '10 kreditų', 'credits' => 10, 'bonus_credits' => 0, 'price_cents' => 990],
        ['name' => '30 kreditų', 'credits' => 30, 'bonus_credits' => 0, 'price_cents' => 2690],
        ['name' => '60 kreditų', 'credits' => 60, 'bonus_credits' => 5, 'price_cents' => 4990],
        ['name' => '120 kreditų', 'credits' => 120, 'bonus_credits' => 15, 'price_cents' => 8990],
    ],
    'subscription_plans' => [
        [
            'name' => 'Startas',
            'slug' => 'startas',
            'description' => 'Pradedantiems: kas mėnesį 25 kreditai.',
            'price_cents' => 1900,
            'credits_per_period' => 25,
            'features' => ['max_categories' => 10, 'badge' => false],
        ],
        [
            'name' => 'Profesionalas',
            'slug' => 'profesionalas',
            'description' => 'Aktyviems teikėjams: 60 kreditų ir ženklelis profilyje.',
            'price_cents' => 3900,
            'credits_per_period' => 60,
            'features' => ['max_categories' => 30, 'badge' => true],
        ],
        [
            'name' => 'Verslas',
            'slug' => 'verslas',
            'description' => 'Įmonėms: 140 kreditų, neribotos kategorijos, prioritetinė pagalba.',
            'price_cents' => 7900,
            'credits_per_period' => 140,
            'features' => ['max_categories' => 100, 'badge' => true, 'priority_support' => true],
        ],
    ],
];
