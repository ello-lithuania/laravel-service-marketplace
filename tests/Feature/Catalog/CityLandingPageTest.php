<?php

use App\Models\City;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * SEO puslapiai „{Paslauga} {mieste}": /paslaugos/{kategorija}/{miestas}.
 */
test('antraštė su vietininku: „Plytelių klijavimas Vilniuje"', function () {
    [, , $leaf] = categoryBranch(['name' => 'Plytelių klijavimas', 'slug' => 'plyteliu-klijavimas']);
    $vilnius = City::factory()->create(['name' => 'Vilnius', 'name_locative' => 'Vilniuje', 'slug' => 'vilnius']);
    catalogProvider([$leaf], [$vilnius]);

    $this->get(route('categories.city', ['category' => $leaf, 'city' => $vilnius]))
        ->assertOk()
        ->assertSee('<title>Plytelių klijavimas Vilniuje - '.config('app.name').'</title>', false)
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/categories/Show')
            ->where('city', ['name' => 'Vilnius', 'slug' => 'vilnius', 'name_locative' => 'Vilniuje'])
            ->where('filters.miestas', 'vilnius')
            ->where('seo.title', 'Plytelių klijavimas Vilniuje')
            ->where('seo.canonical', url('/paslaugos/plyteliu-klijavimas/vilnius'))
            ->where('seo.robots', 'index, follow'));
});

test('mieste rodomi zonos ir visos Lietuvos teikėjai, kitų miestų – ne', function () {
    [, $group, $leaf] = categoryBranch();
    $vilnius = City::factory()->create();
    $kaunas = City::factory()->create();

    $local = catalogProvider([$leaf], [$vilnius]);
    $everywhere = catalogProvider([$group], attributes: ['serves_whole_country' => true]);
    $other = catalogProvider([$leaf], [$kaunas]);

    $this->get(route('categories.city', ['category' => $leaf, 'city' => $vilnius]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('providers.data', 2)
            ->where('providers.data', fn ($cards) => collect($cards)->pluck('slug')->sort()->values()->all()
                === collect([$local->slug, $everywhere->slug])->sort()->values()->all()));

    expect($other->slug)->not->toBeIn([$local->slug, $everywhere->slug]);
});

test('kategorijos puslapyje – nuorodos į populiarius miestus', function () {
    [, , $leaf] = categoryBranch();
    City::factory()->create(['sort_order' => 0, 'name_locative' => 'Vilniuje']);
    City::factory()->create(['sort_order' => 1, 'name_locative' => 'Kaune']);

    $this->get(route('categories.show', $leaf))
        ->assertInertia(fn (Assert $page) => $page
            ->has('popularCities', 2)
            ->where('popularCities.0.name_locative', 'Vilniuje')
            ->where('popularCities.1.name_locative', 'Kaune'));
});

test('tuščias „paslauga mieste" puslapis neindeksuojamas', function () {
    [, , $leaf] = categoryBranch();
    $city = City::factory()->create();

    $this->get(route('categories.city', ['category' => $leaf, 'city' => $city]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('providers.data', 0)
            ->where('seo.robots', 'noindex, follow'));
});

test('nežinomas miestas – 404', function () {
    [, , $leaf] = categoryBranch();

    $this->get("/paslaugos/{$leaf->slug}/tokio-nera")->assertNotFound();
});

test('kitas ?miestas= mieste nukreipia į to miesto puslapį', function () {
    [, , $leaf] = categoryBranch();
    $vilnius = City::factory()->create();
    $kaunas = City::factory()->create();

    $this->get(route('categories.city', ['category' => $leaf, 'city' => $vilnius, 'miestas' => $kaunas->slug]))
        ->assertRedirect(route('categories.city', ['category' => $leaf->slug, 'city' => $kaunas->slug]));
});
