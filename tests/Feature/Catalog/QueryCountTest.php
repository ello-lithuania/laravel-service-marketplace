<?php

use App\Models\Category;
use App\Models\City;
use App\Models\PortfolioItem;
use App\Models\ProviderProfile;
use App\Models\Review;
use Illuminate\Support\Facades\DB;

/**
 * N+1 apsauga: SQL užklausų skaičius neturi augti didėjant įrašų skaičiui puslapyje.
 * (preventLazyLoading jau meta klaidą dėl neužkrauto ryšio, o šie testai saugo ir nuo užklausų cikle.)
 */
function countQueries(Closure $request): int
{
    // Pirmas kartas „apšildo" katalogo cache: kuriant teikėjus factory sukuria naujus miestus,
    // o tai (teisingai) išvalo geografijos cache. Skaičiuojam antrą, įprastą užklausą.
    $request();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $request();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

/**
 * @param  list<Category>  $categories
 * @param  list<City>  $zones
 */
function seedListedProviders(int $count, array $categories, array $zones): void
{
    foreach (range(1, $count) as $i) {
        catalogProvider($categories, $zones, ['verified_at' => now(), 'rating_avg' => 4, 'reviews_count' => 3 + $i]);
    }
}

test('kategorijos puslapio užklausų skaičius nepriklauso nuo teikėjų skaičiaus', function () {
    [$root, $group, $leaf] = categoryBranch();
    $city = City::factory()->create();

    seedListedProviders(2, [$leaf, $group], [$city]);
    $few = countQueries(fn () => $this->get(route('categories.city', ['category' => $leaf, 'city' => $city]))->assertOk());

    seedListedProviders(15, [$leaf, $group, $root], [$city]);
    $many = countQueries(fn () => $this->get(route('categories.city', ['category' => $leaf, 'city' => $city]))->assertOk());

    expect($many)->toBe($few)->toBeLessThanOrEqual(8);
});

test('/meistrai, paieškos ir pradžios puslapių užklausų skaičius pastovus', function (Closure $url) {
    [, , $leaf] = categoryBranch();
    $city = City::factory()->create();

    seedListedProviders(2, [$leaf], [$city]);
    $few = countQueries(fn () => $this->get($url($city))->assertOk());

    seedListedProviders(15, [$leaf], [$city]);
    $many = countQueries(fn () => $this->get($url($city))->assertOk());

    expect($many)->toBe($few)->toBeLessThanOrEqual(8);
})->with([
    'meistrai' => [fn (City $city) => route('providers.index', ['miestas' => $city->slug])],
    'paieška' => [fn (City $city) => route('search', ['q' => 'darbus', 'miestas' => $city->slug])],
    'pradžia' => [fn (City $city) => route('home')],
]);

test('profilio užklausų skaičius nepriklauso nuo paslaugų, zonų, darbų ir atsiliepimų skaičiaus', function () {
    $small = ProviderProfile::factory()->create();
    $large = ProviderProfile::factory()->create();

    $fill = function (ProviderProfile $provider, int $count): void {
        foreach (Category::factory()->count($count)->create() as $category) {
            $provider->categories()->attach($category->id, ['price_from_cents' => 1000, 'price_unit' => 'job']);
        }
        $provider->serviceAreas()->attach(City::factory()->count($count)->create()->modelKeys());
        PortfolioItem::factory()->for($provider)->count($count)->create([
            'category_id' => Category::factory(),
            'city_id' => City::factory(),
        ]);
        Review::factory()->for($provider)->withReply()->count($count)->create();
    };

    $fill($small, 1);
    $fill($large, 12);

    $few = countQueries(fn () => $this->get(route('providers.show', $small))->assertOk());
    $many = countQueries(fn () => $this->get(route('providers.show', $large))->assertOk());

    expect($many)->toBe($few)->toBeLessThanOrEqual(12);
});
