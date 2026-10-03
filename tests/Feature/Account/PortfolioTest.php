<?php

use App\Models\Category;
use App\Models\City;
use App\Models\PortfolioItem;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Atlikti darbai (portfolio) su nuotraukomis.
 */
beforeEach(function () {
    Storage::fake('public');
});

function portfolioPhoto(string $name = 'darbas.jpg'): UploadedFile
{
    return UploadedFile::fake()->image($name, 800, 600);
}

test('teikėjas mato savo darbų sąrašą su viršelio nuotrauka', function () {
    $profile = ProviderProfile::factory()->create();
    $item = PortfolioItem::factory()->for($profile)->create(['title' => 'Vonia']);
    $item->addMedia(portfolioPhoto())->toMediaCollection('images');
    PortfolioItem::factory()->create(); // svetimas darbas – neturi matytis

    $this->actingAs($profile->user)->get(route('portfolio.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('account/portfolio/Index')
            ->has('items', 1)
            ->where('items.0.title', 'Vonia')
            ->where('items.0.images_count', 1)
            ->where('items.0.cover', fn (string $url) => str_contains($url, '-thumb.jpg')));
});

test('naujas darbas sukuriamas su nuotraukomis sąrašo gale', function () {
    $profile = ProviderProfile::factory()->create();
    $category = Category::factory()->create();
    $profile->categories()->attach($category);
    PortfolioItem::factory()->for($profile)->create(['sort_order' => 3]);

    $this->actingAs($profile->user)->get(route('portfolio.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('account/portfolio/Create')
            ->where('categories', [['id' => $category->id, 'name' => $category->name]])
            ->where('maxImages', PortfolioItem::MAX_IMAGES));

    $this->actingAs($profile->user)
        ->post(route('portfolio.store'), [
            'title' => 'Virtuvės baldai',
            'description' => 'Sumontuota per dvi dienas.',
            'category_id' => $category->id,
            'city_id' => City::factory()->create()->id,
            'completed_date' => '2026-05-01',
            'images' => [portfolioPhoto('a.jpg'), portfolioPhoto('b.png')],
        ])
        ->assertRedirect(route('portfolio.index'))
        ->assertSessionHasNoErrors();

    $item = $profile->portfolioItems()->where('title', 'Virtuvės baldai')->first();

    expect($item->sort_order)->toBe(4)
        ->and($item->completed_date->toDateString())->toBe('2026-05-01')
        ->and($item->getMedia('images'))->toHaveCount(2)
        ->and($item->getFirstMedia('images')->getRawOriginal('model_type'))->toBe('portfolio_item')
        // QUEUE_CONNECTION=sync testuose – „eilėje" daromos miniatiūros sukuriamos iškart
        ->and($item->getFirstMedia('images')->hasGeneratedConversion('thumb'))->toBeTrue();
});

test('naujam darbui reikia bent vienos nuotraukos ir savo kategorijos', function () {
    $profile = ProviderProfile::factory()->create();

    $this->actingAs($profile->user)
        ->post(route('portfolio.store'), [
            'title' => 'Be nuotraukų',
            'category_id' => Category::factory()->create()->id,
        ])
        ->assertSessionHasErrors([
            'images' => 'Įkelkite bent vieną nuotrauką.',
            'category_id' => 'Pasirinkite vieną iš savo kategorijų.',
        ]);

    expect(PortfolioItem::count())->toBe(0);
});

test('viename darbe – ne daugiau kaip MAX_IMAGES nuotraukų', function () {
    $profile = ProviderProfile::factory()->create();
    $item = PortfolioItem::factory()->for($profile)->create();

    foreach (range(1, PortfolioItem::MAX_IMAGES - 1) as $i) {
        $item->addMedia(portfolioPhoto("{$i}.jpg"))->toMediaCollection('images');
    }

    $this->actingAs($profile->user)
        ->put(route('portfolio.update', $item), [
            'title' => $item->title,
            'images' => [portfolioPhoto('x.jpg'), portfolioPhoto('y.jpg')],
        ])
        ->assertSessionHasErrors(['images' => 'Viename darbe gali būti daugiausia '.PortfolioItem::MAX_IMAGES.' nuotraukų.']);

    $this->actingAs($profile->user)
        ->put(route('portfolio.update', $item), [
            'title' => $item->title,
            'images' => [portfolioPhoto('x.jpg')],
        ])
        ->assertSessionHasNoErrors();

    expect($item->fresh()->getMedia('images'))->toHaveCount(PortfolioItem::MAX_IMAGES);
});

test('darbą galima redaguoti ir pridėti nuotraukų', function () {
    $profile = ProviderProfile::factory()->create();
    $item = PortfolioItem::factory()->for($profile)->create(['title' => 'Senas']);
    $item->addMedia(portfolioPhoto())->toMediaCollection('images');

    $this->actingAs($profile->user)->get(route('portfolio.edit', $item))
        ->assertInertia(fn (Assert $page) => $page
            ->component('account/portfolio/Edit')
            ->where('item.title', 'Senas')
            ->has('item.images', 1));

    $this->actingAs($profile->user)
        ->put(route('portfolio.update', $item), ['title' => 'Naujas', 'images' => [portfolioPhoto()]])
        ->assertRedirect(route('portfolio.edit', $item));

    expect($item->fresh())
        ->title->toBe('Naujas')
        ->and($item->fresh()->getMedia('images'))->toHaveCount(2);
});

test('nuotrauką galima pašalinti, bet tik iš savo darbo', function () {
    $profile = ProviderProfile::factory()->create();
    $item = PortfolioItem::factory()->for($profile)->create();
    $media = $item->addMedia(portfolioPhoto())->toMediaCollection('images');

    $foreign = PortfolioItem::factory()->create();
    $foreignMedia = $foreign->addMedia(portfolioPhoto())->toMediaCollection('images');

    // Svetima nuotrauka per savo darbo adresą – scopeBindings() grąžina 404
    $this->actingAs($profile->user)
        ->delete(route('portfolio.images.destroy', [$item, $foreignMedia]))
        ->assertNotFound();

    $this->actingAs($profile->user)
        ->delete(route('portfolio.images.destroy', [$item, $media]))
        ->assertRedirect();

    expect(Media::find($media->id))->toBeNull()
        ->and(Media::find($foreignMedia->id))->not->toBeNull();
});

test('ištrynus darbą ištrinamos ir jo nuotraukos', function () {
    $profile = ProviderProfile::factory()->create();
    $item = PortfolioItem::factory()->for($profile)->create();
    $path = $item->addMedia(portfolioPhoto())->toMediaCollection('images')->getPathRelativeToRoot();

    $this->actingAs($profile->user)
        ->delete(route('portfolio.destroy', $item))
        ->assertRedirect(route('portfolio.index'));

    expect(PortfolioItem::find($item->id))->toBeNull()
        ->and(Media::count())->toBe(0);
    Storage::disk('public')->assertMissing($path);
});

test('svetimo darbo negalima nei atidaryti, nei keisti, nei ištrinti', function () {
    $item = PortfolioItem::factory()->create();
    $otherProvider = ProviderProfile::factory()->create()->user;

    $this->actingAs($otherProvider)->get(route('portfolio.edit', $item))->assertForbidden();
    $this->actingAs($otherProvider)->put(route('portfolio.update', $item), ['title' => 'Pavogta'])->assertForbidden();
    $this->actingAs($otherProvider)->delete(route('portfolio.destroy', $item))->assertForbidden();

    expect($item->fresh()->title)->not->toBe('Pavogta');
});

test('klientas portfolio puslapių neatidaro', function () {
    $this->actingAs(User::factory()->create())->get(route('portfolio.index'))->assertForbidden();
});

test('darbų tvarką galima pakeisti, bet tik savo darbų', function () {
    $profile = ProviderProfile::factory()->create();
    [$first, $second, $third] = PortfolioItem::factory()->for($profile)->count(3)
        ->sequence(['sort_order' => 1], ['sort_order' => 2], ['sort_order' => 3])
        ->create();

    $this->actingAs($profile->user)
        ->put(route('portfolio.reorder'), ['ids' => [$third->id, $first->id, $second->id]])
        ->assertRedirect();

    expect($profile->portfolioItems()->pluck('id')->all())->toBe([$third->id, $first->id, $second->id]);

    $this->actingAs($profile->user)
        ->put(route('portfolio.reorder'), ['ids' => [PortfolioItem::factory()->create()->id]])
        ->assertSessionHasErrors('ids.0');
});
