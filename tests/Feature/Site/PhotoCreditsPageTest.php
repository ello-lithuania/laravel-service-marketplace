<?php

use App\Actions\Photos\AttachLibraryPhoto;
use App\Enums\SitePhotoKey;
use App\Models\Category;
use App\Models\SitePhoto;
use App\Services\Photos\StockPhotoLibrary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\StockPhotos;

/*
 * Etapas 10: viešas puslapis /nuotrauku-autoriai – visos media su custom_properties.credit.
 */

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('stock-photos');
});

/**
 * Demo media eilutė be failo (greitai): puslapiui užtenka custom_properties ir URL.
 */
function demoMediaRow(int $modelId, string $stockId, string $author, string $modelType = 'portfolio_item'): void
{
    DB::table('media')->insert([
        'model_type' => $modelType,
        'model_id' => $modelId,
        'uuid' => (string) Str::uuid(),
        'collection_name' => $modelType === 'portfolio_item' ? 'images' : 'cover',
        'name' => $stockId,
        'file_name' => 'demo.jpg',
        'mime_type' => 'image/jpeg',
        'disk' => 'public',
        'conversions_disk' => 'public',
        'size' => 1000,
        'manipulations' => '[]',
        'custom_properties' => json_encode([
            'stock_id' => $stockId,
            'credit' => ['author' => $author, 'author_url' => null, 'source' => 'Pexels', 'source_url' => null, 'license' => 'Pexels License', 'license_url' => null, 'title' => null],
        ]),
        'generated_conversions' => '[]',
        'responsive_images' => '[]',
        'order_column' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('puslapis rodo svetainės, kategorijų ir demo nuotraukų autorius; savos nuotraukos nerodomos', function () {
    $attach = app(AttachLibraryPhoto::class);

    $category = Category::factory()->create(['name' => 'Statyba ir remontas']);
    $attach->handle($category, 'image', StockPhotos::inLibrary(StockPhotoLibrary::CATEGORIES, $category->slug, 'pexels:10'));

    // Administratoriaus įkelta nuotrauka be autoriaus – ne į sąrašą
    Category::factory()->create()->addMedia(UploadedFile::fake()->image('sava.jpg', 1200, 800))->toMediaCollection('image');

    $hero = SitePhoto::forKey(SitePhotoKey::Hero);
    $attach->handle($hero, 'photo', StockPhotos::inLibrary(StockPhotoLibrary::SITE, 'hero', 'openverse:abc'));

    // Ta pati rinkinio nuotrauka prisegta prie dviejų darbų ir viršelio – rodoma vieną kartą
    demoMediaRow(1, 'pexels:77', 'Rinkinio autorius');
    demoMediaRow(2, 'pexels:77', 'Rinkinio autorius');
    demoMediaRow(3, 'pexels:77', 'Rinkinio autorius', 'provider_profile');
    demoMediaRow(4, 'pexels:78', 'Kitas autorius');

    $this->get('/nuotrauku-autoriai')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/PhotoCredits')
            ->where('seo.robots', 'noindex, follow')
            ->where('seo.canonical', route('photo-credits'))
            ->has('sitePhotos', 1)
            ->where('sitePhotos.0.label', 'Pradžios puslapio viršus')
            ->has('categories', 1)
            ->where('categories.0.label', 'Statyba ir remontas')
            ->where('categories.0.author', 'Bibliotekos autorius')
            ->where('categories.0.license', 'Pexels License')
            ->where('categories.0.thumb_url', fn (string $url) => str_contains($url, '/storage/') && str_contains($url, 'card'))
            ->has('demo.data', 2)
            ->where('demo.data.0.author', 'Rinkinio autorius')
            ->where('demo.data.1.author', 'Kitas autorius')
            ->where('demo.meta.total', 2)
            // Tik reikalingi laukai, ne visas Media modelis
            ->has('categories.0', 10));
});

test('demo rinkinys puslapiuojamas, svetainės ir kategorijų nuotraukos – tik pirmame puslapyje', function () {
    $hero = SitePhoto::forKey(SitePhotoKey::Hero);
    app(AttachLibraryPhoto::class)->handle($hero, 'photo', StockPhotos::inLibrary(StockPhotoLibrary::SITE, 'hero', 'pexels:1'));

    foreach (range(1, 30) as $i) {
        demoMediaRow($i, "pexels:{$i}00", "Autorius {$i}");
    }

    $this->get('/nuotrauku-autoriai')->assertInertia(fn (Assert $page) => $page
        ->has('sitePhotos', 1)
        ->has('demo.data', 24)
        ->where('demo.meta.last_page', 2));

    $this->get('/nuotrauku-autoriai?puslapis=2')->assertInertia(fn (Assert $page) => $page
        ->has('sitePhotos', 0)
        ->has('categories', 0)
        ->has('demo.data', 6)
        ->where('demo.data.0.author', 'Autorius 25'));
});

test('be svetimų nuotraukų puslapis veikia (tuščias)', function () {
    $this->get('/nuotrauku-autoriai')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('sitePhotos', 0)
            ->has('categories', 0)
            ->has('demo.data', 0));
});

test('nuorodos tik http(s): „javascript:" iš išorinio API į puslapį nepatenka', function () {
    $category = Category::factory()->create();
    $category->addMedia(UploadedFile::fake()->image('a.jpg', 1200, 800))
        ->withCustomProperties(['stock_id' => 'openverse:x', 'credit' => [
            'author' => '<b>Autorius</b>', 'author_url' => 'javascript:alert(1)', 'source' => 'Flickr',
            'source_url' => 'https://www.flickr.com/photos/x', 'license' => 'CC BY 2.0', 'license_url' => 'data:text/html,hi',
        ]])
        ->toMediaCollection('image');

    $this->get('/nuotrauku-autoriai')->assertInertia(fn (Assert $page) => $page
        ->where('categories.0.author', 'Autorius')
        ->where('categories.0.author_url', null)
        ->where('categories.0.license_url', null)
        ->where('categories.0.source_url', 'https://www.flickr.com/photos/x'));
});
