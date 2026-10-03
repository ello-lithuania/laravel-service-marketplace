<?php

use App\Models\Category;
use App\Models\City;
use Inertia\Testing\AssertableInertia as Assert;

test('pradžios puslapis atidaromas', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/Home'));
});

test('pradžios puslapyje – aktyvios 1 lygio kategorijos, miestai ir geriausi teikėjai', function () {
    [$root, $group] = categoryBranch();
    $root->update(['icon' => 'hammer']);
    Category::factory()->inactive()->create();
    City::factory()->count(3)->create();

    $best = catalogProvider([$group], attributes: ['rating_avg' => 4.9, 'reviews_count' => 10]);
    catalogProvider(attributes: ['rating_avg' => 5, 'reviews_count' => 1]); // per mažai atsiliepimų

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/Home')
            ->has('categories', 1, fn (Assert $category) => $category
                ->where('slug', $root->slug)
                ->where('icon', 'hammer')
                ->has('children', 1)
                ->etc())
            // 3 miestai sukurti čia + 2 teikėjų bazinių miestų (factory)
            ->has('cities', 5)
            ->has('popularCities', 5)
            ->has('featuredProviders', 1, fn (Assert $provider) => $provider
                ->where('slug', $best->slug)
                ->where('price_from', null)
                ->etc())
            ->where('seo.canonical', route('home')));
});
