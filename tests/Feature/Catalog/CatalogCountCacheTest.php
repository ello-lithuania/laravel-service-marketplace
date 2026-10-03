<?php

use App\Models\ProviderProfile;
use App\Services\Catalog\ProviderFilters;
use App\Services\Catalog\ProviderListQuery;
use App\Services\Catalog\SearchTerms;
use Illuminate\Support\Facades\DB;

/**
 * Etapas 8: puslapiavimo COUNT laikomas cache (ProviderListQuery::cachedTotal) – docs/PERFORMANCE.md.
 */
test('bendras skaičius laikomas cache: naujas teikėjas į total patenka tik pasibaigus cache', function () {
    ProviderProfile::factory()->count(2)->create();
    $query = app(ProviderListQuery::class);

    expect($query->paginate(new ProviderFilters)->total())->toBe(2);

    ProviderProfile::factory()->create();
    expect($query->paginate(new ProviderFilters)->total())->toBe(2);

    $this->travel(ProviderListQuery::COUNT_TTL_MINUTES + 1)->minutes();
    expect($query->paginate(new ProviderFilters)->total())->toBe(3);
});

test('skirtingi filtrai – skirtingi skaičiai (raktas – SQL su parametrais)', function () {
    ProviderProfile::factory()->create();
    ProviderProfile::factory()->verified()->create();
    $query = app(ProviderListQuery::class);

    expect($query->paginate(new ProviderFilters)->total())->toBe(2)
        ->and($query->paginate(new ProviderFilters(verifiedOnly: true))->total())->toBe(1);
});

test('paieškos tekstu skaičius necache\'inamas', function () {
    ProviderProfile::factory()->create(['display_name' => 'Stogdengys Petras']);
    $filters = new ProviderFilters(search: SearchTerms::parse('stogdengys'));
    $query = app(ProviderListQuery::class);

    expect($query->paginate($filters)->total())->toBe(1);

    ProviderProfile::factory()->create(['display_name' => 'Stogdengys Jonas']);
    expect($query->paginate($filters)->total())->toBe(2);
})->skip(fn () => DB::getDriverName() === 'mysql', 'InnoDB FULLTEXT nemato nepatvirtintų RefreshDatabase eilučių');
