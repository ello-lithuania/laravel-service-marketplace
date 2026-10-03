<?php

use App\Models\Category;
use App\Models\City;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Marketplace;

/**
 * Užklausos nuotraukos (Etapas 6, atidėta iš Etapo 5): forma, valdymas, privatus rodymas.
 */
beforeEach(function () {
    Notification::fake();
    Storage::fake('local');
    Storage::fake('public');
});

function photo(string $name = 'vonia.jpg'): UploadedFile
{
    return UploadedFile::fake()->image($name, 800, 600);
}

function requestWithPhotos(int $count = 2, array $attributes = []): ServiceRequest
{
    $request = Marketplace::openRequest(attributes: $attributes);

    foreach (range(1, $count) as $i) {
        $request->addMedia(photo("foto{$i}.jpg"))->toMediaCollection('photos');
    }

    return $request;
}

test('užklausos formoje įkeltos nuotraukos išsaugomos privačiame diske', function () {
    $client = User::factory()->create();

    $this->actingAs($client)->post(route('service-requests.store'), [
        'category_id' => Category::factory()->leaf()->create()->id,
        'city_id' => City::factory()->create()->id,
        'title' => 'Vonios kambario plytelės',
        'description' => 'Reikia išklijuoti apie 12 m² sienų ir 4 m² grindų plytelėmis.',
        'start_preference' => 'flexible',
        'photos' => [photo('siena.jpg'), photo('grindys.png')],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $media = ServiceRequest::query()->sole()->getMedia('photos');

    expect($media)->toHaveCount(2)
        ->and($media->pluck('disk')->unique()->all())->toBe(['local'])
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('netinkamos nuotraukos atmetamos (ne paveikslėlis, per maža, per daug)', function (array $photos, string $errorKey) {
    $this->actingAs(User::factory()->create())->post(route('service-requests.store'), [
        'category_id' => Category::factory()->leaf()->create()->id,
        'city_id' => City::factory()->create()->id,
        'title' => 'Vonios kambario plytelės',
        'description' => 'Reikia išklijuoti apie 12 m² sienų ir 4 m² grindų plytelėmis.',
        'start_preference' => 'flexible',
        'photos' => $photos,
    ])->assertSessionHasErrors($errorKey);

    expect(ServiceRequest::query()->count())->toBe(0);
})->with([
    'PDF' => [fn () => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')], 'photos.0'],
    'per maža' => [fn () => [UploadedFile::fake()->image('m.jpg', 100, 100)], 'photos.0'],
    'per daug' => [fn () => array_map(fn (int $i) => photo("f{$i}.jpg"), range(1, ServiceRequest::MAX_PHOTOS + 1)), 'photos'],
]);

test('klientas prideda ir pašalina nuotraukas, kol užklausa atvira', function () {
    $request = requestWithPhotos(1);
    $client = $request->client;

    $this->actingAs($client)
        ->post(route('service-requests.photos.store', $request), ['photos' => [photo('naujas.jpg')]])
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', 'Nuotraukos pridėtos.');
    expect($request->refresh()->getMedia('photos'))->toHaveCount(2);

    $media = $request->getMedia('photos')->first();
    $this->actingAs($client)
        ->delete(route('service-requests.photos.destroy', ['serviceRequest' => $request, 'media' => $media->id]))
        ->assertRedirect();
    expect($request->refresh()->getMedia('photos'))->toHaveCount(1);
});

test('iš viso ne daugiau kaip 8 nuotraukos', function () {
    $request = requestWithPhotos(ServiceRequest::MAX_PHOTOS - 1);

    $this->actingAs($request->client)
        ->post(route('service-requests.photos.store', $request), ['photos' => [photo('a.jpg'), photo('b.jpg')]])
        ->assertSessionHasErrors('photos');
});

test('vykdomos užklausos ar svetimos užklausos nuotraukų keisti negalima', function () {
    $inProgress = ServiceRequest::factory()->inProgress()->create();
    $this->actingAs($inProgress->client)
        ->post(route('service-requests.photos.store', $inProgress), ['photos' => [photo()]])
        ->assertForbidden();

    $request = requestWithPhotos(1);
    $this->actingAs(User::factory()->create())
        ->post(route('service-requests.photos.store', $request), ['photos' => [photo()]])
        ->assertForbidden();
});

test('kitos užklausos nuotraukos ID per šią užklausą – 404 (scopeBindings)', function () {
    $mine = requestWithPhotos(1);
    $other = requestWithPhotos(1);

    $this->actingAs($mine->client)
        ->delete(route('service-requests.photos.destroy', ['serviceRequest' => $mine, 'media' => $other->getFirstMedia('photos')->id]))
        ->assertNotFound();
});

test('nuotraukas mato klientas ir tinkamas teikėjas, kiti – ne', function () {
    $request = requestWithPhotos(1);
    $media = $request->getFirstMedia('photos');
    $url = route('media.show', ['media' => $media->id, 'conversion' => 'thumb']);

    $this->actingAs($request->client)->get($url)->assertOk();
    $this->actingAs(Marketplace::eligibleProvider($request)->user)->get($url)->assertOk();
    $this->actingAs(User::factory()->provider()->create())->get($url)->assertForbidden();
    $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
});

test('užklausos puslapyje – nuotraukos ir valdymas klientui, nuotraukos teikėjui', function () {
    $request = requestWithPhotos(2);
    $provider = Marketplace::eligibleProvider($request);

    $this->actingAs($request->client)->get(route('service-requests.show', $request))
        ->assertInertia(fn (Assert $page) => $page
            ->has('serviceRequest.photos', 2)
            ->where('serviceRequest.photos.0.is_image', true)
            ->where('can.updatePhotos', true)
            ->where('maxPhotos', ServiceRequest::MAX_PHOTOS));

    $this->actingAs($provider->user)->get(route('service-requests.show', $request))
        ->assertInertia(fn (Assert $page) => $page
            ->component('service-requests/ProviderShow')
            ->has('serviceRequest.photos', 2));
});

test('nuotraukos užkraunamos viena užklausa (be N+1), kad ir kiek jų būtų', function () {
    $few = requestWithPhotos(1);
    $many = requestWithPhotos(6);

    $count = function (ServiceRequest $request): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($request->client)->get(route('service-requests.show', $request))->assertOk();

        return count(DB::getQueryLog());
    };

    expect($count($many))->toBe($count($few));
});

test('teikėjo sraute – nuotraukų skaičius', function () {
    $request = requestWithPhotos(3);
    $provider = Marketplace::eligibleProvider($request);

    $this->actingAs($provider->user)->get(route('provider-feed.index'))
        ->assertInertia(fn (Assert $page) => $page->where('serviceRequests.data.0.photos_count', 3));
});
