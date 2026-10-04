<?php

use App\Enums\SitePhotoKey;
use App\Models\Category;
use App\Models\SitePhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Etapas 10: svetainės ir kategorijų nuotraukos pradžios puslapyje (iš cache, cache išvalomas įkėlus nuotrauką).
 */

beforeEach(function () {
    Storage::fake('public');
});

test('be nuotraukų pradžios puslapis gauna null – Vue rodo atsarginį dizainą', function () {
    Category::factory()->create();

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('photos.hero', null)
            ->where('photos.providers', null)
            ->where('photos.request', null)
            ->where('categories.0.image_url', null));
});

test('įkėlus svetainės nuotrauką ji atsiranda be rankinio cache valymo', function () {
    $this->get('/')->assertInertia(fn (Assert $page) => $page->where('photos.hero', null));

    $photo = SitePhoto::query()->create(['key' => SitePhotoKey::Hero, 'alt' => 'Meistras darbe']);
    $photo->addMedia(UploadedFile::fake()->image('hero.jpg', 2400, 1200))->toMediaCollection('photo');

    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->where('photos.hero.alt', 'Meistras darbe')
        ->where('photos.hero.url', fn (string $url) => str_contains($url, 'hero'))
        ->where('photos.providers', null));
});

test('kategorijos nuotrauka patenka į cache medį ir pradžios puslapio korteles', function () {
    $category = Category::factory()->create();
    $this->get('/')->assertInertia(fn (Assert $page) => $page->where('categories.0.image_url', null));

    $category->addMedia(UploadedFile::fake()->image('statyba.jpg', 1600, 1000))->toMediaCollection('image');

    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->where('categories.0.image_url', fn (string $url) => str_contains($url, 'statyba') && str_contains($url, 'card')));

    expect($category->fresh()?->imageUrl('wide'))->toContain('wide');
});

test('ištrynus kategorijos nuotrauką kortelė vėl be nuotraukos', function () {
    $category = Category::factory()->create();
    $category->addMedia(UploadedFile::fake()->image('a.jpg', 1000, 800))->toMediaCollection('image');
    $this->get('/')->assertInertia(fn (Assert $page) => $page->whereNot('categories.0.image_url', null));

    $category->clearMediaCollection('image');

    $this->get('/')->assertInertia(fn (Assert $page) => $page->where('categories.0.image_url', null));
});

test('svetainės nuotraukos media saugomos trumpu morph vardu', function () {
    $photo = SitePhoto::query()->create(['key' => SitePhotoKey::Providers]);
    $photo->addMedia(UploadedFile::fake()->image('p.jpg', 1200, 800))->toMediaCollection('photo');

    expect($photo->getFirstMedia('photo')?->model_type)->toBe('site_photo');
});
