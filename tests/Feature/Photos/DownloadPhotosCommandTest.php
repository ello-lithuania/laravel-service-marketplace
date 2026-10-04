<?php

use App\Enums\SitePhotoKey;
use App\Models\Category;
use App\Models\SitePhoto;
use App\Services\Photos\PhotoQueries;
use App\Services\Photos\Providers\OpenverseProvider;
use App\Services\Photos\Providers\PexelsProvider;
use App\Services\Photos\StockPhotoLibrary;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Support\StockPhotos;

/*
 * Etapas 10: php artisan photos:download. Tikro interneto nenaudojam: Http::fake() grąžina Pexels / Openverse
 * formato JSON ir tikrą (GD sugeneruotą) JPEG, o Http::preventStrayRequests() užtikrina, kad nė viena užklausa
 * neišeitų į tikrą internetą. Diskai – netikri (Storage::fake), pauzės – netikros (Sleep::fake).
 */

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('stock-photos');
    Sleep::fake();
    Http::preventStrayRequests();

    // Kūrėjo .env PEXELS_API_KEY į testus nepatenka
    config(['services.pexels.key' => null]);

    app()->instance(PhotoQueries::class, new PhotoQueries([
        'categories' => ['statyba-ir-remontas' => ['home renovation'], 'grindys' => 'wooden floor'],
        'site' => [
            'hero' => ['queries' => 'handyman renovating apartment', 'alt' => 'Meistras remontuoja butą'],
            'providers' => ['queries' => 'craftsman tools', 'alt' => 'Meistro įrankiai'],
            'request' => ['queries' => 'renovation plans', 'alt' => 'Remonto planavimas'],
            'auth' => ['queries' => 'living room', 'alt' => 'Svetainės interjeras'],
        ],
        'portfolio' => ['statyba-ir-remontas' => ['renovated bathroom', 'new kitchen']],
    ]));

    $this->root = Category::factory()->create(['name' => 'Statyba ir remontas', 'slug' => 'statyba-ir-remontas']);
});

function pexelsFake(array $photos, array $images = []): void
{
    Http::fake([
        ...$images,
        'api.pexels.com/*' => Http::response(StockPhotos::pexelsSearch($photos)),
        'images.pexels.com/*' => Http::response(StockPhotos::jpeg(), 200, ['Content-Type' => 'image/jpeg']),
    ]);
}

test('Pexels: kategorijos nuotrauka atsisiunčiama, įrašoma į biblioteką ir prisegama su autoriumi', function () {
    config(['services.pexels.key' => 'pexels-test-key']);
    pexelsFake([StockPhotos::pexelsPhoto(101)]);

    $this->artisan('photos:download', ['--only' => 'categories'])
        ->expectsOutputToContain('Pexels · Fotografas 101')
        ->assertSuccessful();

    $media = $this->root->fresh()?->getFirstMedia('image');

    expect($media)->not->toBeNull()
        ->and($media?->getCustomProperty('stock_id'))->toBe('pexels:101')
        // toEqual, ne toBe: MySQL JSON stulpelis raktų tvarką keičia (rikiuoja), SQLite – ne
        ->and($media?->getCustomProperty('credit'))->toEqual([
            'author' => 'Fotografas 101',
            'author_url' => 'https://www.pexels.com/@fotografas-101',
            'source' => 'Pexels',
            'source_url' => 'https://www.pexels.com/photo/renovated-room-101/',
            'license' => 'Pexels License',
            'license_url' => 'https://www.pexels.com/license/',
            'title' => 'Renovated room 101',
        ])
        // Miniatiūros padarytos iškart (nonQueued) – kortelė ir plati juosta
        ->and($media?->hasGeneratedConversion('card'))->toBeTrue()
        ->and($media?->hasGeneratedConversion('wide'))->toBeTrue();

    // Originalas lieka bibliotekoje (kitam migrate:fresh --seed), credits.json – su autoriumi
    Storage::disk('stock-photos')->assertExists('categories/statyba-ir-remontas.jpg');
    $manifest = json_decode((string) Storage::disk('stock-photos')->get('categories/credits.json'), true);
    expect($manifest['statyba-ir-remontas']['stock_id'])->toBe('pexels:101')
        ->and($manifest['statyba-ir-remontas']['credit']['author'])->toBe('Fotografas 101');

    // Užklausa – tokia, kokios reikalauja Pexels API: raktas antraštėje, landscape orientacija
    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), PexelsProvider::SEARCH_URL)
        && $request->hasHeader('Authorization', 'pexels-test-key')
        && $request['query'] === 'home renovation'
        && $request['orientation'] === 'landscape');
    // Atsisiųstas large2x (≈1880 px), o ne originalas (6000 px, > 10 MB)
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'images.pexels.com/photos/101/') && str_contains($request->url(), 'dpr=2'));
});

test('Openverse be rakto: CC BY nuotrauka su autoriumi, licencija ir User-Agent', function () {
    Http::fake([
        'api.openverse.org/*' => Http::response(StockPhotos::openverseSearch([StockPhotos::openverseResult('ov-1')])),
        'live.staticflickr.com/*' => Http::response(StockPhotos::jpeg(), 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $this->artisan('photos:download', ['--only' => 'categories'])->assertSuccessful();

    $media = $this->root->fresh()?->getFirstMedia('image');

    expect($media?->getCustomProperty('stock_id'))->toBe('openverse:ov-1')
        ->and($media?->getCustomProperty('credit'))->toEqual([
            'author' => 'Autorius Flickr',
            'author_url' => 'https://www.flickr.com/photos/autorius',
            'source' => 'Flickr',
            'source_url' => 'https://www.flickr.com/photos/autorius/ov-1',
            'license' => 'CC BY 2.0',
            'license_url' => 'https://creativecommons.org/licenses/by/2.0/',
            'title' => 'Garden ov-1',
        ]);

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), OpenverseProvider::SEARCH_URL)
        && $request['q'] === 'home renovation'
        && $request['license_type'] === 'commercial,modification'
        && $request['category'] === 'photograph'
        && (int) $request['page_size'] <= 20
        && str_contains($request->header('User-Agent')[0] ?? '', 'photos:download'));
});

test('svetainės nuotraukos: visos SitePhotoKey vietos su alt tekstu, nuotraukos nesikartoja', function () {
    config(['services.pexels.key' => 'pexels-test-key']);
    pexelsFake(array_map(fn (int $id) => StockPhotos::pexelsPhoto($id), range(1, 6)));

    $this->artisan('photos:download', ['--only' => 'site'])->assertSuccessful();

    $photos = SitePhoto::query()->with('media')->get();

    expect($photos)->toHaveCount(count(SitePhotoKey::cases()))
        ->and($photos->firstWhere('key', SitePhotoKey::Hero)?->alt)->toBe('Meistras remontuoja butą')
        ->and($photos->map(fn (SitePhoto $photo) => $photo->getFirstMedia('photo')?->getCustomProperty('stock_id'))->unique()->count())
        ->toBe(count(SitePhotoKey::cases()));

    Storage::disk('stock-photos')->assertExists('site/hero.jpg');
});

test('jau turinti nuotrauką vieta praleidžiama, administratoriaus nuotrauka nekeičiama net su --force', function () {
    Http::fake();
    $this->root->addMedia(UploadedFile::fake()->image('sava.jpg', 1600, 900))->toMediaCollection('image');

    $this->artisan('photos:download', ['--only' => 'categories', '--force' => true])
        ->expectsOutputToContain('sava nuotrauka')
        ->assertSuccessful();

    Http::assertNothingSent();
    expect($this->root->fresh()?->getFirstMedia('image')?->file_name)->toBe('sava.jpg');
});

test('atsisiųsta nuotrauka nekeičiama, o su --force pakeičiama kita', function () {
    config(['services.pexels.key' => 'pexels-test-key']);
    pexelsFake([StockPhotos::pexelsPhoto(101), StockPhotos::pexelsPhoto(102)]);

    $this->artisan('photos:download', ['--only' => 'categories'])->assertSuccessful();
    $this->artisan('photos:download', ['--only' => 'categories'])->expectsOutputToContain('jau yra')->assertSuccessful();

    expect($this->root->fresh()?->getFirstMedia('image')?->getCustomProperty('stock_id'))->toBe('pexels:101');

    $this->artisan('photos:download', ['--only' => 'categories', '--force' => true])->assertSuccessful();

    // singleFile: sena nuotrauka pakeista, ne pridėta
    expect(Media::query()->where('model_type', 'category')->count())->toBe(1)
        ->and($this->root->fresh()?->getFirstMedia('image')?->getCustomProperty('stock_id'))->toBe('pexels:102');
});

test('nuotrauka iš vietinės bibliotekos prisegama be interneto (pvz. po migrate:fresh)', function () {
    Http::fake();
    StockPhotos::inLibrary(StockPhotoLibrary::CATEGORIES, 'statyba-ir-remontas', 'pexels:555');

    $this->artisan('photos:download', ['--only' => 'categories'])
        ->expectsOutputToContain('iš bibliotekos')
        ->assertSuccessful();

    Http::assertNothingSent();
    expect($this->root->fresh()?->getFirstMedia('image')?->getCustomProperty('credit')['author'])->toBe('Bibliotekos autorius');
    // Bibliotekos originalas nepajudintas (preservingOriginal)
    Storage::disk('stock-photos')->assertExists('categories/statyba-ir-remontas.jpg');
});

test('tinklo klaida: bandomas kitas rezultatas, komanda nesustoja', function () {
    config(['services.pexels.key' => 'pexels-test-key']);
    pexelsFake(
        [StockPhotos::pexelsPhoto(1), StockPhotos::pexelsPhoto(2)],
        ['images.pexels.com/photos/1/*' => Http::failedConnection('cURL error 28: Operation timed out')],
    );

    $this->artisan('photos:download', ['--only' => 'categories'])->assertSuccessful();

    expect($this->root->fresh()?->getFirstMedia('image')?->getCustomProperty('stock_id'))->toBe('pexels:2');
});

test('ne paveikslėlis (HTML), per maža ir per didelė nuotrauka atmetamos', function () {
    config(['services.pexels.key' => 'pexels-test-key']);
    pexelsFake(
        [StockPhotos::pexelsPhoto(1), StockPhotos::pexelsPhoto(2), StockPhotos::pexelsPhoto(3), StockPhotos::pexelsPhoto(4)],
        [
            // Content-Type meluoja – tikrinamas turinys
            'images.pexels.com/photos/1/*' => Http::response('<html><body>Not found</body></html>', 200, ['Content-Type' => 'image/jpeg']),
            'images.pexels.com/photos/2/*' => Http::response(StockPhotos::jpeg(400, 300), 200, ['Content-Type' => 'image/jpeg']),
            'images.pexels.com/photos/3/*' => Http::response(StockPhotos::jpeg(), 200, ['Content-Type' => 'image/jpeg', 'Content-Length' => '52428800']),
        ],
    );

    $this->artisan('photos:download', ['--only' => 'categories'])->assertSuccessful();

    expect($this->root->fresh()?->getFirstMedia('image')?->getCustomProperty('stock_id'))->toBe('pexels:4');
});

test('nepavykus nieko – klaidos kodas ir priežastys išvestyje', function () {
    config(['services.pexels.key' => 'pexels-test-key']);
    pexelsFake([StockPhotos::pexelsPhoto(1)], [
        'images.pexels.com/photos/1/*' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html']),
        // Openverse taip pat nieko neranda
        'api.openverse.org/*' => Http::response(StockPhotos::openverseSearch([])),
    ]);

    $this->artisan('photos:download', ['--only' => 'categories'])
        ->expectsOutputToContain('ne JPEG, PNG ar WEBP')
        ->assertFailed();

    expect(Media::query()->count())->toBe(0);
});

test('Pexels limitas (429) – automatiškai pereinama prie Openverse', function () {
    config(['services.pexels.key' => 'pexels-test-key']);
    Http::fake([
        'api.pexels.com/*' => Http::response(['error' => 'Rate limit exceeded'], 429),
        'api.openverse.org/*' => Http::response(StockPhotos::openverseSearch([StockPhotos::openverseResult('ov-7')])),
        'live.staticflickr.com/*' => Http::response(StockPhotos::jpeg(), 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $this->artisan('photos:download', ['--only' => 'categories'])
        ->expectsOutputToContain('Pexels: išnaudotas užklausų limitas')
        ->assertSuccessful();

    expect($this->root->fresh()?->getFirstMedia('image')?->getCustomProperty('credit')['source'])->toBe('Flickr');
});

test('Openverse 429 su Retry-After – palaukia ir bando dar kartą', function () {
    Http::fake([
        'api.openverse.org/*' => Http::sequence()
            ->push(['detail' => 'Request was throttled.'], 429, ['Retry-After' => '7'])
            ->push(StockPhotos::openverseSearch([StockPhotos::openverseResult('ov-8')])),
        'live.staticflickr.com/*' => Http::response(StockPhotos::jpeg(), 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $this->artisan('photos:download', ['--only' => 'categories'])->assertSuccessful();

    Sleep::assertSlept(fn ($duration) => (int) $duration->totalSeconds === 7);
    expect($this->root->fresh()?->getFirstMedia('image')?->getCustomProperty('stock_id'))->toBe('openverse:ov-8');
});

test('Openverse: žmonės, vidinio tinklo adresai ir netinkami formatai praleidžiami, Wikimedia – miniatiūra', function () {
    Http::fake([
        'api.openverse.org/*' => Http::response(StockPhotos::openverseSearch([
            // CC licencija neapima žmogaus sutikimo – nuotraukų su žmonėmis iš Openverse neimam
            StockPhotos::openverseResult('ov-person', ['title' => 'Portrait of a man in the garden']),
            StockPhotos::openverseResult('ov-local', ['url' => 'http://127.0.0.1/secret.jpg']),
            StockPhotos::openverseResult('ov-svg', ['url' => 'https://example.org/a.svg', 'filetype' => 'svg']),
            StockPhotos::openverseResult('ov-nc', ['license' => 'by-nc']),
            StockPhotos::openverseResult('ov-wiki', [
                'source' => 'wikimedia',
                'url' => 'https://upload.wikimedia.org/wikipedia/commons/a/ab/Garden_view.jpg',
                'width' => 6000,
                'height' => 4000,
            ]),
        ])),
        'upload.wikimedia.org/*' => Http::response(StockPhotos::jpeg(1920, 1280), 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $this->artisan('photos:download', ['--only' => 'categories'])->assertSuccessful();

    $media = $this->root->fresh()?->getFirstMedia('image');
    expect($media?->getCustomProperty('stock_id'))->toBe('openverse:ov-wiki')
        ->and($media?->getCustomProperty('credit')['source'])->toBe('Wikimedia Commons');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ab/Garden_view.jpg/1920px-Garden_view.jpg');
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '127.0.0.1') || str_contains($request->url(), 'example.org'));
});

test('portfolio rinkinys: N nuotraukų su credits.json, nuotraukos su žmonėmis praleidžiamos', function () {
    config(['services.pexels.key' => 'pexels-test-key']);
    pexelsFake([
        StockPhotos::pexelsPhoto(1, ['alt' => 'Man fixing a pipe']),
        StockPhotos::pexelsPhoto(2, ['alt' => 'Renovated bathroom with tiles']),
        StockPhotos::pexelsPhoto(3, ['alt' => 'New kitchen cabinets']),
        StockPhotos::pexelsPhoto(4, ['alt' => 'Woman painting a wall']),
        StockPhotos::pexelsPhoto(5, ['alt' => 'Tiled floor']),
    ]);

    $this->artisan('photos:download', ['--only' => 'portfolio', '--per-category' => 3])
        ->expectsOutputToContain('3/3')
        ->assertSuccessful();

    $pool = app(StockPhotoLibrary::class)->pools()['statyba-ir-remontas'] ?? [];

    expect(array_map(fn ($photo) => $photo->stockId, $pool))->toBe(['pexels:2', 'pexels:3', 'pexels:5'])
        ->and(array_map(fn ($photo) => $photo->fileName, $pool))->toBe(['statyba-ir-remontas-01.jpg', 'statyba-ir-remontas-02.jpg', 'statyba-ir-remontas-03.jpg'])
        ->and($pool[0]->credit->author)->toBe('Fotografas 2');

    Storage::disk('stock-photos')->assertExists('portfolio/statyba-ir-remontas/credits.json');

    // Antrą kartą – nieko nesiunčia
    Http::fake();
    $this->artisan('photos:download', ['--only' => 'portfolio', '--per-category' => 3])
        ->expectsOutputToContain('jau yra 3')
        ->assertSuccessful();
    Http::assertNothingSent();
});

test('nepavykus įrašyti į biblioteką – aiški klaida, kitos vietos tęsiamos', function () {
    config(['services.pexels.key' => 'pexels-test-key']);
    pexelsFake(array_map(fn (int $id) => StockPhotos::pexelsPhoto($id), range(1, 6)));
    // Failas vietoj katalogo „categories" – įrašyti į jį neįmanoma (kaip pilnas diskas ar be teisių)
    Storage::disk('stock-photos')->put('categories', 'ne katalogas');

    $this->artisan('photos:download', ['--only' => 'categories,site'])
        ->expectsOutputToContain('nepavyko įrašyti')
        ->assertSuccessful();

    expect($this->root->fresh()?->getMedia('image'))->toBeEmpty()
        // Svetainės nuotraukos (kitas katalogas) – atsisiųstos
        ->and(SitePhoto::query()->where('key', SitePhotoKey::Hero)->firstOrFail()->getFirstMedia('photo'))->not->toBeNull();
});

test('Windows SSL klaida (cURL error 60) – patarimas, kaip pataisyti php.ini', function () {
    config(['services.pexels.key' => 'pexels-test-key']);
    Category::factory()->childOf($this->root)->create(['name' => 'Grindys', 'slug' => 'grindys']);
    Http::fake(['*' => Http::failedConnection('cURL error 60: SSL certificate problem: unable to get local issuer certificate')]);

    $this->artisan('photos:download', ['--only' => 'categories'])
        ->expectsOutputToContain('cacert.pem')
        // Klaida pati nepraeis – kitos vietos nebebandomos (po vieną paiešką kiekvienam šaltiniui)
        ->expectsOutputToContain('praleista – šaltiniai nepasiekiami')
        ->assertFailed();

    Http::assertSentCount(2);
});

test('neteisingi parametrai – aiški klaida', function () {
    $this->artisan('photos:download', ['--only' => 'nuotraukos'])->assertFailed();
    $this->artisan('photos:download', ['--per-category' => 0])->assertFailed();
    $this->artisan('photos:download', ['--source' => 'pexels'])
        ->expectsOutputToContain('PEXELS_API_KEY')
        ->assertFailed();
});
