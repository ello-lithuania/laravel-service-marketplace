<?php

/*
|--------------------------------------------------------------------------
| Katalogo testų pagalbininkai (Etapas 4)
|--------------------------------------------------------------------------
|
| Pest pats užkrauna visus tests/Helpers failus, todėl funkcijas galima naudoti bet kuriame teste.
|
*/

use App\Models\Category;
use App\Models\City;
use App\Models\ProviderProfile;

/**
 * 3 lygių šaka: [1 lygis, 2 lygis, 3 lygis].
 *
 * @param  array<string, mixed>  $leafAttributes
 * @return array{0: Category, 1: Category, 2: Category}
 */
function categoryBranch(array $leafAttributes = []): array
{
    $root = Category::factory()->create();
    $group = Category::factory()->childOf($root)->create();
    $leaf = Category::factory()->childOf($group)->create($leafAttributes);

    return [$root, $group, $leaf];
}

/**
 * Aktyvus teikėjas su kategorijomis (kaina „nuo 15 €/val.") ir aptarnavimo zonomis.
 *
 * @param  list<Category>  $categories
 * @param  list<City>  $zones
 * @param  array<string, mixed>  $attributes
 */
function catalogProvider(array $categories = [], array $zones = [], array $attributes = []): ProviderProfile
{
    $provider = ProviderProfile::factory()->create($attributes);

    foreach ($categories as $category) {
        $provider->categories()->attach($category->id, ['price_from_cents' => 1500, 'price_unit' => 'hour']);
    }

    $provider->serviceAreas()->attach(array_map(fn (City $city) => $city->id, $zones));

    return $provider;
}
