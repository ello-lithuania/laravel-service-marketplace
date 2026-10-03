<?php

use App\Enums\ServiceRequestStatus;
use App\Models\Category;
use App\Models\City;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\NewMatchingRequest;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Marketplace;

beforeEach(function () {
    $this->leaf = Category::factory()->leaf()->create(['name' => 'Plytelių klijavimas']);
    $this->city = City::factory()->create();
    $this->client = User::factory()->create();
});

function validRequestData(array $overrides = []): array
{
    return [
        'category_id' => test()->leaf->id,
        'city_id' => test()->city->id,
        'title' => 'Plytelių klijavimas vonios kambaryje',
        'description' => 'Reikia suklijuoti plyteles vonios kambaryje, plotas apie 12 m². Plyteles jau turiu.',
        'address' => 'Gedimino pr. 1',
        'budget_min' => '300',
        'budget_max' => '600',
        'start_preference' => 'flexible',
        'start_date' => '',
        ...$overrides,
    ];
}

test('svečias nukreipiamas prisijungti ir po to grąžinamas į formą', function () {
    $this->get('/uzklausos/nauja')->assertRedirect('/login');

    expect(session('url.intended'))->toBe(url('/uzklausos/nauja'));
});

test('teikėjas ir administratorius užklausos kurti negali', function () {
    $this->actingAs(User::factory()->provider()->create())->get('/uzklausos/nauja')->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->post('/uzklausos', validRequestData())->assertForbidden();
});

test('klientas mato formą su kategorijų medžiu, miestais ir iš anksto parinkta paslauga', function () {
    $this->client->forceFill(['city_id' => $this->city->id])->save();

    $this->actingAs($this->client)
        ->get('/uzklausos/nauja?kategorija='.$this->leaf->slug)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('service-requests/Create')
            ->has('categories', 1)
            ->has('categories.0.children.0.children.0', fn (Assert $leaf) => $leaf
                ->where('id', $this->leaf->id)
                ->where('name', 'Plytelių klijavimas')
                ->etc())
            ->has('cities', 1)
            ->has('startPreferences', 5)
            ->where('defaults.category_id', $this->leaf->id)
            ->where('defaults.city_id', $this->city->id));
});

test('švari užklausa patvirtinto kliento paskelbiama iškart ir tinkami teikėjai gauna pranešimą', function () {
    Notification::fake();
    $this->freezeSecond();
    $provider = Marketplace::eligibleProvider(new ServiceRequest(['category_id' => $this->leaf->id, 'city_id' => $this->city->id]));

    $response = $this->actingAs($this->client)->post('/uzklausos', validRequestData());

    $request = ServiceRequest::query()->sole();
    $response->assertRedirect(route('service-requests.show', $request));

    expect($request)
        ->client_id->toBe($this->client->id)
        ->status->toBe(ServiceRequestStatus::Open)
        ->published_at->toEqual(now())
        ->expires_at->toEqual(now()->addDays(30))
        ->budget_min_cents->toBe(30000)
        ->budget_max_cents->toBe(60000)
        ->address->toBe('Gedimino pr. 1')
        ->start_date->toBeNull()
        ->and($request->slug)->toStartWith('plyteliu-klijavimas-vonios-kambaryje-');

    Notification::assertSentTo($provider->user, NewMatchingRequest::class);
});

test('tekstas su telefono numeriu lieka laukti moderavimo', function () {
    Notification::fake();
    Marketplace::eligibleProvider(new ServiceRequest(['category_id' => $this->leaf->id, 'city_id' => $this->city->id]));

    $this->actingAs($this->client)->post('/uzklausos', validRequestData([
        'description' => 'Reikia suklijuoti plyteles vonioje, skambinkite +370 612 34567 bet kada.',
    ]))->assertRedirect();

    expect(ServiceRequest::query()->sole()->status)->toBe(ServiceRequestStatus::Pending);
    Notification::assertNothingSent();
});

test('nepatvirtinto kliento užklausa paskelbiama patvirtinus el. paštą', function () {
    Notification::fake();
    $client = User::factory()->unverified()->create();

    $this->actingAs($client)->post('/uzklausos', validRequestData())->assertRedirect();
    $request = ServiceRequest::query()->sole();
    expect($request->status)->toBe(ServiceRequestStatus::Pending);

    $client->markEmailAsVerified();
    event(new Verified($client));

    expect($request->refresh()->status)->toBe(ServiceRequestStatus::Open);
});

test('validacija lietuviškai: tik 3 lygio kategorija, biudžeto intervalas, data', function () {
    $this->actingAs($this->client)
        ->post('/uzklausos', validRequestData([
            'category_id' => $this->leaf->parent_id,
            'title' => 'Trumpas',
            'budget_min' => '500',
            'budget_max' => '100',
            'start_preference' => 'date',
            'start_date' => '',
        ]))
        ->assertSessionHasErrors([
            'category_id' => 'Pasirinkite konkrečią paslaugą (trečio lygio kategoriją).',
            'title' => 'Simbolių kiekis lauke pavadinimas turi būti ne mažiau nei 10.',
            'budget_max' => 'Biudžetas „iki" negali būti mažesnis už biudžetą „nuo".',
            'start_date' => 'Nurodykite datą, kada norite pradėti.',
        ]);

    expect(ServiceRequest::query()->count())->toBe(0);
});

test('biudžetas „iki" be „nuo" leidžiamas, data išsaugoma tik pasirinkus konkrečią dieną', function () {
    $this->actingAs($this->client)->post('/uzklausos', validRequestData([
        'budget_min' => '',
        'budget_max' => '200',
        'start_preference' => 'date',
        'start_date' => now()->addWeek()->toDateString(),
    ]))->assertSessionHasNoErrors();

    expect(ServiceRequest::query()->sole())
        ->budget_min_cents->toBeNull()
        ->budget_max_cents->toBe(20000)
        ->start_date->toDateString()->toBe(now()->addWeek()->toDateString());
});

test('Precognition: žingsnio laukai tikrinami neišsaugant', function () {
    $headers = ['Precognition' => 'true', 'Precognition-Validate-Only' => 'title,description'];

    $this->actingAs($this->client)
        ->postJson('/uzklausos', ['title' => 'Per trumpas', 'description' => 'trumpai'], $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['description'])
        ->assertJsonMissingValidationErrors(['category_id', 'city_id']);

    $this->actingAs($this->client)
        ->postJson('/uzklausos', validRequestData(), $headers)
        ->assertNoContent();

    expect(ServiceRequest::query()->count())->toBe(0);
});
