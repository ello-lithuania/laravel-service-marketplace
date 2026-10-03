<?php

use App\Listeners\FlushCatalogCacheAfterSeeding;
use App\Models\Category;
use App\Models\City;
use App\Models\Region;
use App\Services\Catalog\CatalogCache;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Katalogo cache: kas saugoma, kas matoma ir kada išvaloma (docs/DB_SCHEMA.md 2.2–2.3).
 */
function catalogCache(): CatalogCache
{
    return app(CatalogCache::class);
}

test('kategorijų medis cache saugomas kaip paprasti masyvai', function () {
    $leaf = Category::factory()->leaf()->create();

    catalogCache()->categories();

    $rows = Cache::get(CatalogCache::CATEGORIES_KEY);
    expect($rows)->toBeArray()->toHaveCount(3)
        ->and($rows[2])->toBeArray()->toMatchArray(['id' => $leaf->id, 'depth' => 3, 'slug' => $leaf->slug]);
});

test('medis antrą kartą skaitomas iš cache, be DB užklausų', function () {
    Category::factory()->leaf()->create();
    City::factory()->create();

    catalogCache()->categories();
    catalogCache()->geography();

    // Naujas objektas (kaip kitoje HTTP užklausoje) – duomenys turi ateiti iš cache, ne iš DB
    app()->forgetScopedInstances();
    DB::enableQueryLog();

    $tree = catalogCache()->categories();
    $geography = catalogCache()->geography();

    expect(DB::getQueryLog())->toBeEmpty()
        ->and($tree->all())->toHaveCount(3)
        ->and($geography->citySlugs())->toHaveCount(1);
});

test('išjungta kategorija ir visi jos vaikai medyje nematomi', function () {
    $root = Category::factory()->create();
    $group = Category::factory()->childOf($root)->create(['is_active' => false]);
    $leaf = Category::factory()->childOf($group)->create();
    $visible = Category::factory()->childOf($root)->create();

    $tree = catalogCache()->categories();

    expect($tree->find($group->id))->toBeNull()
        ->and($tree->find($leaf->id))->toBeNull()
        ->and($tree->find($visible->id))->not->toBeNull()
        ->and($tree->findBySlug($leaf->slug))->toBeNull();
});

test('medis randa tėvus, vaikus ir susijusias kategorijas', function () {
    $root = Category::factory()->create();
    $group = Category::factory()->childOf($root)->create();
    $leafA = Category::factory()->childOf($group)->create();
    $leafB = Category::factory()->childOf($group)->create();

    $tree = catalogCache()->categories();

    expect(array_map(fn ($c) => $c->id, $tree->ancestors($leafA->id)))->toBe([$root->id, $group->id])
        ->and($tree->descendantIds($root->id))->toEqualCanonicalizing([$group->id, $leafA->id, $leafB->id])
        // 3 lygis: pati + tėvai; 2 lygis: tėvas + pati + vaikai
        ->and($tree->relatedIds($leafA->id))->toBe([$root->id, $group->id, $leafA->id])
        ->and($tree->relatedIds($group->id))->toEqualCanonicalizing([$root->id, $group->id, $leafA->id, $leafB->id]);
});

test('pakeitus kategoriją cache išvalomas ir medis matomas iš karto', function () {
    $category = Category::factory()->create(['name' => 'Senas pavadinimas']);
    expect(catalogCache()->categories()->find($category->id)?->name)->toBe('Senas pavadinimas');

    $category->update(['name' => 'Naujas pavadinimas']);

    expect(Cache::has(CatalogCache::CATEGORIES_KEY))->toBeFalse()
        ->and(catalogCache()->categories()->find($category->id)?->name)->toBe('Naujas pavadinimas');
});

test('ištrynus kategoriją cache išvalomas', function () {
    $category = Category::factory()->create();
    catalogCache()->categories();

    $category->delete();

    expect(catalogCache()->categories()->find($category->id))->toBeNull();
});

test('pakeitus savivaldybę ar apskritį išvalomas geografijos cache', function () {
    $city = City::factory()->create(['name' => 'Senamiestis']);
    catalogCache()->geography();

    $city->update(['name' => 'Naujamiestis']);
    expect(catalogCache()->geography()->findCity($city->id)?->name)->toBe('Naujamiestis');

    catalogCache()->geography();
    Region::factory()->create();

    expect(Cache::has(CatalogCache::GEOGRAPHY_KEY))->toBeFalse();
});

test('kategorijos pakeitimas geografijos cache neliečia', function () {
    City::factory()->create();
    catalogCache()->geography();

    Category::factory()->create();

    expect(Cache::has(CatalogCache::GEOGRAPHY_KEY))->toBeTrue();
});

test('po db:seed katalogo cache išvalomas, nes seed’ai vykdomi be model events', function (string $command, bool $flushed) {
    Category::factory()->create();
    catalogCache()->categories();
    catalogCache()->geography();

    app(FlushCatalogCacheAfterSeeding::class)->handle(
        new CommandFinished($command, new ArrayInput([]), new NullOutput, 0),
    );

    expect(Cache::has(CatalogCache::CATEGORIES_KEY))->toBe(! $flushed)
        ->and(Cache::has(CatalogCache::GEOGRAPHY_KEY))->toBe(! $flushed);
})->with([
    'db:seed' => ['db:seed', true],
    'migrate:fresh' => ['migrate:fresh', true],
    'kita komanda' => ['route:list', false],
]);

test('savivaldybės pasirinkimui rikiuojamos pagal lietuvių abėcėlę', function () {
    foreach (['Šiauliai', 'Zarasai', 'Alytus', 'Šakiai', 'Telšiai'] as $name) {
        City::factory()->create(['name' => $name]);
    }

    $names = array_column(catalogCache()->geography()->cityOptions(), 'name');

    expect($names)->toBe(['Alytus', 'Šakiai', 'Šiauliai', 'Telšiai', 'Zarasai']);
});
