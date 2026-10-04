<?php

use App\Enums\SitePhotoKey;
use App\Models\City;
use App\Models\SitePhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Etapas 10: dizainui reikalingi props – bendras „site" (poraštė, prisijungimo nuotrauka), kategorijų nuotraukos
 * kategorijų puslapiuose, kvietimo teikėjams nuotrauka kainų puslapyje.
 */

beforeEach(function () {
    Storage::fake('public');
});

test('viešuose puslapiuose – poraštės sritys, miestai ir prisijungimo nuotrauka', function () {
    [$root] = categoryBranch();
    City::factory()->count(10)->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('site.categories', 1, fn (Assert $category) => $category
                ->where('slug', $root->slug)
                ->etc())
            ->has('site.cities', 8)
            ->where('site.auth_photo', null));
});

test('prisijungimo puslapis gauna administratoriaus įkeltą nuotrauką', function () {
    $photo = SitePhoto::query()->create(['key' => SitePhotoKey::Auth, 'alt' => 'Meistras su klientu']);
    $photo->addMedia(UploadedFile::fake()->image('auth.jpg', 1600, 1200))->toMediaCollection('photo');

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/Login')
            ->where('site.auth_photo.alt', 'Meistras su klientu')
            ->where('site.auth_photo.url', fn (string $url) => str_contains($url, 'auth')));
});

test('paskyros puslapiuose (auth) „site" nesiunčiamas – jiems poraštės nereikia', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->missing('site'));
});

test('kategorijos puslapis: sava nuotrauka arba 1 lygio srities nuotrauka', function () {
    [$root, $group, $leaf] = categoryBranch();
    $root->addMedia(UploadedFile::fake()->image('sritis.jpg', 1800, 900))->toMediaCollection('image');

    $this->get(route('categories.show', $leaf))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('category.image_url', fn (string $url) => str_contains($url, 'sritis') && str_contains($url, 'card'))
            ->where('category.image_wide_url', fn (string $url) => str_contains($url, 'sritis') && str_contains($url, 'wide')));

    $group->addMedia(UploadedFile::fake()->image('grupe.jpg', 1800, 900))->toMediaCollection('image');

    $this->get(route('categories.show', $root))
        ->assertInertia(fn (Assert $page) => $page
            ->where('category.image_wide_url', fn (string $url) => str_contains($url, 'sritis'))
            ->where('subcategories.0.image_url', fn (string $url) => str_contains($url, 'grupe')));

    $this->get(route('categories.show', $group))
        ->assertInertia(fn (Assert $page) => $page
            ->where('category.image_wide_url', fn (string $url) => str_contains($url, 'grupe')));
});

test('be nuotraukų kategorijų puslapiai gauna null', function () {
    [$root, , $leaf] = categoryBranch();

    $this->get(route('categories.show', $leaf))
        ->assertInertia(fn (Assert $page) => $page
            ->where('category.image_url', null)
            ->where('category.image_wide_url', null));

    $this->get(route('categories.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('categories.0.slug', $root->slug)
            ->where('categories.0.image_url', null)
            ->where('categories.0.children.0.image_url', null));
});

test('kainų puslapis gauna kvietimo teikėjams nuotrauką', function () {
    $this->get(route('pricing'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('photos.providers', null));

    $photo = SitePhoto::query()->create(['key' => SitePhotoKey::Providers]);
    $photo->addMedia(UploadedFile::fake()->image('teikejai.jpg', 1600, 1200))->toMediaCollection('photo');

    $this->get(route('pricing'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('photos.providers.url', fn (string $url) => str_contains($url, 'teikejai')));
});
