<?php

use App\Models\Category;
use App\Models\City;
use App\Models\Message;
use App\Models\ProviderProfile;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Messaging;

/**
 * Dažnio ribos (Etapas 6): pavadinti limiter'iai, lietuviškas 429 ir Precognition išimtis.
 */
beforeEach(function () {
    Notification::fake();
});

test('žinutės: 15 per minutę, 16-a – grąžinama atgal su lietuvišku pranešimu; po minutės vėl galima', function () {
    $offer = Messaging::offer();
    $conversation = Messaging::conversation($offer);
    $client = Messaging::client($offer);

    for ($i = 1; $i <= 15; $i++) {
        $this->actingAs($client)->post(route('messages.store', $conversation), ['body' => "Žinutė {$i}"])->assertSessionHasNoErrors();
    }

    // Inertia užklausa – atgal su klaida (forma neišsivalo) ir pranešimu
    $this->actingAs($client)
        ->post(route('messages.store', $conversation), ['body' => 'Per daug'], ['X-Inertia' => 'true'])
        ->assertRedirect()
        ->assertSessionHasErrors(['throttle' => 'Per daug bandymų per trumpą laiką. Bandykite dar kartą po 1 min.'])
        ->assertInertiaFlash('toast.type', 'error');

    expect(Message::query()->count())->toBe(15);

    $this->travel(61)->seconds();
    $this->actingAs($client)->post(route('messages.store', $conversation), ['body' => 'Vėl galima'])->assertSessionHasNoErrors();
    expect(Message::query()->count())->toBe(16);
});

test('JSON užklausai – 429 su lietuvišku pranešimu ir Retry-After', function () {
    $offer = Messaging::offer();
    $conversation = Messaging::conversation($offer);
    $client = Messaging::client($offer);

    for ($i = 1; $i <= 15; $i++) {
        $this->actingAs($client)->post(route('messages.store', $conversation), ['body' => "Žinutė {$i}"]);
    }

    $this->actingAs($client)
        ->postJson(route('messages.store', $conversation), ['body' => 'Per daug'])
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJson(['message' => 'Per daug bandymų per trumpą laiką. Bandykite dar kartą po 1 min.']);
});

test('riba skaičiuojama kiekvienam vartotojui atskirai', function () {
    $offer = Messaging::offer();
    $conversation = Messaging::conversation($offer);

    for ($i = 1; $i <= 15; $i++) {
        $this->actingAs(Messaging::client($offer))->post(route('messages.store', $conversation), ['body' => "K{$i}"]);
    }

    $this->actingAs(Messaging::provider($offer))
        ->post(route('messages.store', $conversation), ['body' => 'Teikėjo atsakymas'])
        ->assertSessionHasNoErrors();
});

test('skundai: 10 per valandą', function () {
    $user = User::factory()->create();
    $providers = ProviderProfile::factory()->count(11)->create();

    foreach ($providers->take(10) as $provider) {
        $this->actingAs($user)->post(route('complaints.store'), ['type' => 'provider_profile', 'id' => $provider->id, 'reason' => 'spam'])
            ->assertSessionHasNoErrors();
    }

    // Ne Inertia ir ne JSON užklausa – paprastas 429 atsakymas su tuo pačiu lietuvišku tekstu
    $this->actingAs($user)
        ->post(route('complaints.store'), ['type' => 'provider_profile', 'id' => $providers->last()->id, 'reason' => 'spam'])
        ->assertStatus(429)
        ->assertSee('Per daug bandymų per trumpą laiką.');
});

test('užklausos: ribojamas tik tikras išsaugojimas, ne Precognition žingsnių tikrinimas', function () {
    $client = User::factory()->create();
    $data = [
        'category_id' => Category::factory()->leaf()->create()->id,
        'city_id' => City::factory()->create()->id,
        'title' => 'Reikia nudažyti tvorą sodyboje',
        'description' => 'Apie 40 metrų medinė tvora, reikia nušveisti ir nudažyti dviem sluoksniais.',
        'start_preference' => 'flexible',
    ];

    // Daugiažingsnė forma: daug Precognition užklausų – nė viena neribojama
    for ($i = 1; $i <= 30; $i++) {
        $this->actingAs($client)
            ->postJson(route('service-requests.store'), $data, ['Precognition' => 'true', 'Precognition-Validate-Only' => 'title,description'])
            ->assertNoContent();
    }

    for ($i = 1; $i <= 5; $i++) {
        $this->actingAs($client)->post(route('service-requests.store'), $data)->assertSessionHasNoErrors();
    }

    $this->actingAs($client)
        ->post(route('service-requests.store'), $data, ['X-Inertia' => 'true'])
        ->assertSessionHasErrors('throttle');
    expect(ServiceRequest::query()->count())->toBe(5);
});

test('atsiliepimai: limiter\'is prijungtas prie atsiliepimų maršrutų', function () {
    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route) => in_array($route->getName(), ['reviews.store', 'reviews.invitation.store', 'provider-reviews.reply'], true));

    expect($routes)->toHaveCount(3)
        ->and($routes->every(fn ($route) => in_array('throttle:reviews', $route->gatherMiddleware(), true)))->toBeTrue();
});
