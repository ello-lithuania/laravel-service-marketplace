<?php

use App\Enums\SitePhotoKey;
use App\Models\Category;
use App\Models\PortfolioItem;
use App\Models\SitePhoto;
use App\Services\Catalog\CatalogCache;
use App\Services\Photos\StockPhotoLibrary;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Support\StockPhotos;

/*
 * Etapas 10: atsisiųstos nuotraukos seed'e. Biblioteka (diskas „stock-photos") – netikra, užpildoma testo pradžioje
 * taip, tarsi photos:download jau būtų paleista.
 */

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('stock-photos');
    config(['seeding.stock_photos' => true, 'seeding.scale' => 0.01]);
});

/**
 * 1 lygio sritis kiekvienai kategorijai (pagal tėvų grandinę).
 *
 * @return array<int, string> category id => 1 lygio slug
 */
function rootSlugs(): array
{
    $categories = Category::query()->get(['id', 'parent_id', 'slug'])->keyBy('id');
    $roots = [];

    foreach ($categories as $category) {
        $node = $category;

        while ($node->parent_id !== null) {
            $node = $categories[$node->parent_id];
        }

        $roots[$category->id] = $node->slug;
    }

    return $roots;
}

test('1 lygio kategorijų ir svetainės nuotraukos iš bibliotekos prisegamos seed\'inant – be interneto', function () {
    StockPhotos::inLibrary(StockPhotoLibrary::CATEGORIES, 'statyba-ir-remontas', 'pexels:1');
    StockPhotos::inLibrary(StockPhotoLibrary::CATEGORIES, 'grindys', 'pexels:2');
    StockPhotos::inLibrary(StockPhotoLibrary::SITE, 'hero', 'openverse:abc');

    $this->seed(DatabaseSeeder::class);

    $media = Media::query()->get();
    $root = Category::query()->where('slug', 'statyba-ir-remontas')->firstOrFail();
    $hero = SitePhoto::query()->where('key', SitePhotoKey::Hero)->firstOrFail();

    // 2 lygio kategorija (grindys) – tik su SEED_MEDIA=true (seed'o greitis), žr. kitą testą
    expect($media)->toHaveCount(2)
        ->and(Category::query()->where('slug', 'grindys')->firstOrFail()->hasMedia('image'))->toBeFalse()
        ->and($media->pluck('model_type')->unique()->sort()->values()->all())->toBe(['category', 'site_photo'])
        // Seed'e modelių įvykiai išjungti – UUID ir eilės numerį nurodom patys
        ->and($media->whereNull('uuid'))->toBeEmpty()
        ->and($media->whereNull('order_column'))->toBeEmpty()
        ->and($root->getFirstMedia('image')?->getCustomProperty('stock_id'))->toBe('pexels:1')
        ->and($root->getFirstMedia('image')?->hasGeneratedConversion('wide'))->toBeTrue()
        ->and($hero->getFirstMedia('photo')?->getCustomProperty('credit')['author'])->toBe('Bibliotekos autorius')
        // Alt tekstas – iš database/data/photo_queries.php
        ->and($hero->alt)->not->toBeNull()
        // Kategorijų medžio cache išvalytas, nors MediaCacheObserver seed'e neveikė
        ->and(app(CatalogCache::class)->categories()->find($root->id)?->imageUrl)->toContain('/storage/');
});

test('SEED_STOCK_PHOTOS=false – biblioteka ignoruojama', function () {
    config(['seeding.stock_photos' => false]);
    StockPhotos::inLibrary(StockPhotoLibrary::CATEGORIES, 'statyba-ir-remontas', 'pexels:1');

    $this->seed(DatabaseSeeder::class);

    expect(Media::query()->count())->toBe(0);
});

test('SEED_MEDIA: srities rinkinys – tikros nuotraukos su autoriumi, sritis be rinkinio – sugeneruoti paveikslėliai', function () {
    config(['seeding.demo' => true, 'seeding.media' => true]);

    // Rinkinys visoms sritims, išskyrus vieną – ji turi likti su sugeneruotais paveikslėliais
    $withoutPool = 'statyba-ir-remontas';
    $poolIds = [];

    foreach (require database_path('data/categories.php') as $index => $root) {
        $slug = Str::slug($root['name']);

        if ($slug === $withoutPool) {
            continue;
        }

        foreach ([1, 2] as $n) {
            $stockId = 'pexels:'.($index * 10 + $n);
            StockPhotos::inLibrary(StockPhotoLibrary::poolDirectory($slug), sprintf('%s-%02d', $slug, $n), $stockId, width: 900, height: 700);
            $poolIds[] = $stockId;
        }
    }

    StockPhotos::inLibrary(StockPhotoLibrary::CATEGORIES, 'grindys', 'pexels:999');
    // Ankstesnio seed'o failas, kurio nebenurodo jokia media eilutė
    Storage::disk('public')->put('9999/senas.jpg', 'x');

    $this->seed(DatabaseSeeder::class);

    // Su SEED_MEDIA – ir 2 lygio kategorijų nuotraukos; MediaGenerator jų failų netrina, o senus – trina
    $floors = Category::query()->where('slug', 'grindys')->firstOrFail()->getFirstMedia('image');
    expect($floors?->getCustomProperty('stock_id'))->toBe('pexels:999');
    Storage::disk('public')->assertExists((string) $floors?->getPathRelativeToRoot('wide'));
    Storage::disk('public')->assertMissing('9999/senas.jpg');

    $roots = rootSlugs();
    $images = Media::query()->where('collection_name', 'images')->get();
    $items = PortfolioItem::query()->whereKey($images->pluck('model_id'))->pluck('category_id', 'id');

    // Abu atvejai tikrai pasitaiko (ta pati sėkla – tas pats planas): statyba – populiariausia sritis
    $byRoot = $images->groupBy(fn (Media $image) => $roots[$items[$image->model_id]] === $withoutPool ? 'generated' : 'pool');
    expect($byRoot->get('generated'))->not->toBeEmpty()
        ->and($byRoot->get('pool'))->not->toBeEmpty();

    foreach ($images as $image) {
        $root = $roots[$items[$image->model_id]];
        $stockId = $image->getCustomProperty('stock_id');

        if ($root === $withoutPool) {
            // Sugeneruotas paveikslėlis – be autoriaus
            expect($image->getCustomProperty('credit'))->toBeNull();
        } else {
            expect($stockId)->toBeIn($poolIds)
                ->and($image->getCustomProperty('credit')['license'])->toBe('Pexels License')
                ->and($image->hasGeneratedConversion('thumb'))->toBeTrue();
        }
    }

    // Logotipai – visada sugeneruoti, be autoriaus
    expect(Media::query()->where('collection_name', 'logo')->get()->filter(fn (Media $logo) => $logo->hasCustomProperty('credit')))->toBeEmpty()
        // Viršeliai iš rinkinio – taip pat su autoriumi
        ->and(Media::query()->where('collection_name', 'cover')->get()
            ->every(fn (Media $cover) => $cover->hasCustomProperty('credit') || str_starts_with($cover->file_name, 'cover-')))->toBeTrue();

    // Rinkinio originalai nepajudinti (preservingOriginal)
    Storage::disk('stock-photos')->assertExists('portfolio/aplinka-ir-sodas/aplinka-ir-sodas-01.jpg');
});
