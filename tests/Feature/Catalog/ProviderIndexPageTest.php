<?php

use App\Models\City;
use App\Models\ProviderProfile;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Visų teikėjų sąrašas /meistrai.
 */
test('visų teikėjų sąrašas su filtrais, rikiavimo pasirinkimais ir miestais', function () {
    $provider = catalogProvider();
    ProviderProfile::factory()->hidden()->create();

    $this->get(route('providers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/providers/Index')
            ->has('providers.data', 1)
            ->where('providers.data.0.slug', $provider->slug)
            ->where('filters', ['q' => null, 'miestas' => null, 'patikrinti' => false, 'reitingas' => null, 'rikiuoti' => 'reitingas'])
            ->has('sortOptions', 4)
            ->where('sortOptions.0', ['value' => 'reitingas', 'label' => 'Geriausiai įvertinti'])
            ->has('cities')
            ->where('seo.title', 'Visi meistrai ir paslaugų teikėjai'));
});

test('miesto filtras ?miestas= ir SEO antraštė su vietininku', function () {
    $kaunas = City::factory()->create(['name_locative' => 'Kaune']);
    $inKaunas = catalogProvider(zones: [$kaunas]);
    catalogProvider(zones: [City::factory()->create()]);

    $this->get(route('providers.index', ['miestas' => $kaunas->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.miestas', $kaunas->slug)
            ->has('providers.data', 1)
            ->where('providers.data.0.slug', $inKaunas->slug)
            ->where('seo.title', 'Meistrai ir paslaugų teikėjai Kaune')
            ->where('seo.canonical', route('providers.index', ['miestas' => $kaunas->slug])));
});

test('sąraše kaina „nuo" nerodoma – skirtingų paslaugų kainos nepalyginamos', function () {
    [, , $leaf] = categoryBranch();
    catalogProvider([$leaf]);

    $this->get(route('providers.index'))
        ->assertInertia(fn (Assert $page) => $page->where('providers.data.0.price_from', null));
});

test('rikiavimas pagal atliktus darbus', function () {
    $few = catalogProvider(attributes: ['completed_jobs_count' => 2, 'rating_avg' => 5]);
    $many = catalogProvider(attributes: ['completed_jobs_count' => 40, 'rating_avg' => 4]);

    $this->get(route('providers.index', ['rikiuoti' => 'darbai']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('providers.data.0.slug', $many->slug)
            ->where('providers.data.1.slug', $few->slug));
});
