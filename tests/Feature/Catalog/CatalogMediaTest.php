<?php

use App\Models\City;
use App\Models\PortfolioItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Etapų 3 ir 4 sujungimas: medialibrary nuotraukos viešame kataloge (logotipas, viršelis, portfolio).
 */

beforeEach(function () {
    Storage::fake('public');
});

test('teikėjo kortelėje sąraše rodomas logotipas', function () {
    [, , $leaf] = categoryBranch();
    $provider = catalogProvider([$leaf], [City::factory()->create()]);
    $provider->addMedia(UploadedFile::fake()->image('logo.jpg', 300, 300))->toMediaCollection('logo');

    $this->get(route('providers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('providers.data.0.logo_url', fn (?string $url) => $url !== null && str_contains($url, 'logo')));
});

test('profilyje – logotipas, viršelis ir portfolio nuotraukos', function () {
    [, , $leaf] = categoryBranch();
    $provider = catalogProvider([$leaf], [City::factory()->create()]);
    $provider->addMedia(UploadedFile::fake()->image('logo.jpg', 300, 300))->toMediaCollection('logo');
    $provider->addMedia(UploadedFile::fake()->image('cover.jpg', 1500, 600))->toMediaCollection('cover');
    $item = PortfolioItem::factory()->for($provider)->create(['category_id' => $leaf->id]);
    $item->addMedia(UploadedFile::fake()->image('darbas.jpg', 800, 600))->toMediaCollection('images');

    $this->get(route('providers.show', $provider))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('provider.logo_url', fn (?string $url) => $url !== null)
            ->where('provider.cover_url', fn (?string $url) => $url !== null)
            ->has('portfolio.0.images', 1)
            ->where('portfolio.0.images.0.thumb_url', fn (string $url) => str_contains($url, 'darbas')));
});

test('be įkeltų failų – null ir tuščias sąrašas', function () {
    [, , $leaf] = categoryBranch();
    $provider = catalogProvider([$leaf], [City::factory()->create()]);
    PortfolioItem::factory()->for($provider)->create(['category_id' => $leaf->id]);

    $this->get(route('providers.show', $provider))
        ->assertInertia(fn (Assert $page) => $page
            ->where('provider.logo_url', null)
            ->where('provider.cover_url', null)
            ->where('portfolio.0.images', []));
});
