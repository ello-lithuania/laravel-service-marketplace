<?php

use App\Models\PortfolioItem;
use App\Models\ProviderProfile;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Demo\ImagePainter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/*
 * Demo paveikslėliai (SEED_MEDIA, docs/SEEDING.md 7 sk.). Diskas – netikras (Storage::fake), todėl testas
 * nieko nepalieka storage/app/public kataloge.
 */

beforeEach(function () {
    Storage::fake('public');
    config(['seeding.demo' => true, 'seeding.scale' => 0.01]);
});

test('SEED_MEDIA=true: logotipai, viršeliai ir portfolio nuotraukos aktyviems teikėjams, failai diske', function () {
    // Kaip dev'e (.env: QUEUE_CONNECTION=database): portfolio miniatiūros būtų paliktos eilei
    config(['seeding.media' => true, 'media-library.queue_connection_name' => 'database']);
    $this->seed(DatabaseSeeder::class);

    $media = Media::query()->get();
    $logos = $media->where('collection_name', 'logo');
    $images = $media->where('collection_name', 'images');

    // Mastelis 0.01: 500 × 0.01 = 5 profiliai, 1 000 × 0.01 = 10 portfolio darbų (po 1–3 nuotraukas)
    expect($logos)->toHaveCount(5)
        ->and($images->pluck('model_id')->unique())->toHaveCount(10)
        ->and($images->count())->toBeGreaterThanOrEqual(10)->toBeLessThanOrEqual(30)
        ->and($media->where('collection_name', 'cover')->count())->toBeLessThanOrEqual(5)
        // Trumpi morph map vardai, o ne klasių vardai (Relation::enforceMorphMap)
        ->and($media->pluck('model_type')->unique()->sort()->values()->all())->toBe(['portfolio_item', 'provider_profile'])
        // UUID ir eilės numeris priskirti, nors seed'as vyksta be modelių įvykių
        ->and($media->whereNull('uuid'))->toBeEmpty()
        ->and($media->pluck('uuid')->unique())->toHaveCount($media->count())
        ->and($media->whereNull('order_column'))->toBeEmpty();

    // Tik aktyvūs teikėjai
    $profileIds = $media->where('model_type', 'provider_profile')->pluck('model_id')
        ->merge(PortfolioItem::query()->whereKey($images->pluck('model_id'))->pluck('provider_profile_id'))
        ->unique();
    expect(ProviderProfile::query()->whereKey($profileIds)->where('status', '!=', 'active')->count())->toBe(0);

    // Originalai ir miniatiūros diske; miniatiūros padarytos seed'o metu (sync), o ne paliktos eilei
    foreach ($media as $item) {
        Storage::disk('public')->assertExists($item->getPathRelativeToRoot());

        foreach ($item->collection_name === 'images' ? ['thumb', 'large'] : [$item->collection_name === 'logo' ? 'thumb' : 'wide'] as $conversion) {
            expect($item->hasGeneratedConversion($conversion))->toBeTrue();
            Storage::disk('public')->assertExists($item->getPathRelativeToRoot($conversion));
        }
    }

    // Seed'o metu miniatiūros daromos iškart, o eilės nustatymas po to atstatomas
    expect(DB::table('jobs')->count())->toBe(0)
        ->and(config('media-library.queue_connection_name'))->toBe('database')
        ->and(ProviderProfile::query()->find($logos->first()?->model_id)?->logoUrl())->toContain('/storage/');
});

test('pagal nutylėjimą (SEED_MEDIA=false) paveikslėlių nėra', function () {
    $this->seed(DatabaseSeeder::class);

    expect(config('seeding.media'))->toBeFalse()
        ->and(Media::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('ta pati sėkla – tas pats paveikslėlis (atkartojamumas)', function () {
    $directory = storage_path('framework/testing/seed-media-'.getmypid());
    $painter = new ImagePainter($directory);
    $palette = ['#7c2d12', '#ea580c', '#fed7aa', '#facc15'];

    $hashes = [];

    foreach ([1, 1, 2] as $i => $seed) {
        mt_srand($seed);
        $hashes[] = [
            md5_file($painter->portfolio($palette, "portfolio-{$i}")),
            md5_file($painter->cover($palette, "cover-{$i}")),
            md5_file($painter->logo('ŠŽ', $palette, 3, "logo-{$i}")),
        ];
    }

    File::deleteDirectory($directory);

    expect($hashes[0])->toBe($hashes[1])
        ->and($hashes[2][0])->not->toBe($hashes[0][0]);
});
