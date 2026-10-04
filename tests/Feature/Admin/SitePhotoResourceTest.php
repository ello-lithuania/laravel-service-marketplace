<?php

use App\Actions\Photos\AttachLibraryPhoto;
use App\Enums\SitePhotoKey;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\SitePhotos\Pages\ManageSitePhotos;
use App\Models\Category;
use App\Models\SitePhoto;
use App\Models\User;
use App\Services\Photos\StockPhotoLibrary;
use App\Services\Site\SitePhotos;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\StockPhotos;

/*
 * Etapas 10: nuotraukų įkėlimas admin panelėje (Filament + spatie-laravel-media-library-plugin).
 */

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('stock-photos');
    $this->actingAs(User::factory()->admin()->create());
});

test('„Svetainės nuotraukos": po eilutę kiekvienai SitePhotoKey vietai, sukuriamos automatiškai', function () {
    $this->get('/admin/svetaines-nuotraukos')->assertOk()->assertSee('Svetainės nuotraukos');

    expect(SitePhoto::query()->count())->toBe(count(SitePhotoKey::cases()));

    Livewire::test(ManageSitePhotos::class)
        ->assertCanSeeTableRecords(SitePhoto::query()->get())
        ->assertSee(SitePhotoKey::Hero->label())
        // Kurti ir trinti negalima – vietas apibrėžia kodas
        ->assertActionDoesNotExist('create');

    // Antras atidarymas eilučių nedubliuoja
    Livewire::test(ManageSitePhotos::class);
    expect(SitePhoto::query()->count())->toBe(count(SitePhotoKey::cases()));
});

test('vietos rodomos SitePhotoKey tvarka, nors eilutės sukurtos kita tvarka', function () {
    foreach (array_reverse(SitePhotoKey::cases()) as $key) {
        SitePhoto::forKey($key);
    }

    $ordered = array_map(
        fn (SitePhotoKey $key) => SitePhoto::query()->where('key', $key)->firstOrFail(),
        SitePhotoKey::cases(),
    );

    Livewire::test(ManageSitePhotos::class)->assertCanSeeTableRecords($ordered, inOrder: true);
});

test('svetainės nuotrauką galima įkelti modaliniame lange – ji iškart pasiekia pradžios puslapį', function () {
    Livewire::test(ManageSitePhotos::class);
    $hero = SitePhoto::query()->where('key', SitePhotoKey::Hero)->firstOrFail();

    // Cache užpildomas (dar be nuotraukos) – įkėlus turi išsivalyti pats
    expect(app(SitePhotos::class)->url(SitePhotoKey::Hero))->toBeNull();

    Livewire::test(ManageSitePhotos::class)
        ->callAction(TestAction::make(EditAction::class)->table($hero), data: [
            'photo' => UploadedFile::fake()->image('virsus.jpg', 2000, 1000),
            'alt' => 'Meistras dažo sieną',
        ])
        ->assertHasNoFormErrors();

    $media = $hero->refresh()->getFirstMedia('photo');

    expect($hero->alt)->toBe('Meistras dažo sieną')
        ->and($media)->not->toBeNull()
        ->and($media?->hasGeneratedConversion('large'))->toBeTrue()
        // Sava nuotrauka – be autoriaus įrašo
        ->and($media?->getCustomProperty('credit'))->toBeNull();

    $this->get('/')->assertInertia(fn ($page) => $page->where('photos.hero.alt', 'Meistras dažo sieną'));
});

test('atsisiųstos nuotraukos autorius rodomas sąraše ir kategorijos formoje, savai nuotraukai – ne', function () {
    $hero = SitePhoto::forKey(SitePhotoKey::Hero);
    app(AttachLibraryPhoto::class)->handle($hero, 'photo', StockPhotos::inLibrary(StockPhotoLibrary::SITE, 'hero', 'pexels:42'));

    Livewire::test(ManageSitePhotos::class)->assertSee('Bibliotekos autorius · Pexels · Pexels License');

    $category = Category::factory()->create();
    app(AttachLibraryPhoto::class)->handle($category, 'image', StockPhotos::inLibrary(StockPhotoLibrary::CATEGORIES, $category->slug, 'pexels:43'));

    Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
        ->assertSee('Autorius ir licencija')
        ->assertSee('Bibliotekos autorius · Pexels · Pexels License');

    $own = Category::factory()->create();
    $own->addMedia(UploadedFile::fake()->image('sava.jpg', 1600, 900))->toMediaCollection('image');

    Livewire::test(EditCategory::class, ['record' => $own->getRouteKey()])->assertDontSee('Autorius ir licencija');
});

test('netinkamas failas (ne nuotrauka) atmetamas', function () {
    Livewire::test(ManageSitePhotos::class);
    $hero = SitePhoto::query()->where('key', SitePhotoKey::Hero)->firstOrFail();

    Livewire::test(ManageSitePhotos::class)
        ->callAction(TestAction::make(EditAction::class)->table($hero), data: [
            'photo' => UploadedFile::fake()->create('dokumentas.pdf', 100, 'application/pdf'),
        ])
        ->assertHasFormErrors(['photo']);

    expect($hero->refresh()->getMedia('photo'))->toBeEmpty();
});

test('kategorijos nuotrauką galima įkelti, sąraše – miniatiūra', function () {
    $category = Category::factory()->create(['name' => 'Santechnika']);

    Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
        ->fillForm(['image' => UploadedFile::fake()->image('santechnika.jpg', 1600, 900)])
        ->call('save')
        ->assertHasNoFormErrors();

    $media = $category->refresh()->getFirstMedia('image');

    expect($media)->not->toBeNull()
        ->and($media?->hasGeneratedConversion('card'))->toBeTrue()
        ->and($media?->hasGeneratedConversion('wide'))->toBeTrue()
        ->and($category->imageUrl())->toContain('card');

    Livewire::test(ListCategories::class)
        ->assertCanSeeTableRecords([$category])
        ->assertTableColumnExists('image');
});
