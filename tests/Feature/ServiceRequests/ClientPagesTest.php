<?php

use App\Enums\OfferStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\Offer;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Marketplace;

beforeEach(function () {
    Notification::fake();
});

test('„Mano užklausos" rodo tik savas užklausas', function () {
    $client = User::factory()->create();
    $mine = ServiceRequest::factory()->for($client, 'client')->create();
    ServiceRequest::factory()->create();

    $this->actingAs($client)
        ->get('/mano-uzklausos')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('service-requests/Index')
            ->has('serviceRequests.data', 1)
            ->where('serviceRequests.data.0.slug', $mine->slug)
            ->where('serviceRequests.data.0.status.label', 'Laukia pasiūlymų')
            ->missing('serviceRequests.data.0.address'));
});

test('klientas mato savo užklausą su adresu ir pasiūlymų ištraukomis', function () {
    $request = Marketplace::openRequest(attributes: ['address' => 'Gedimino pr. 1']);
    $offer = Offer::factory()->for($request)->create(['message' => str_repeat('Labai ilga žinutė. ', 20)]);

    $this->actingAs($request->client)
        ->get(route('service-requests.show', $request))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('service-requests/Show')
            ->where('serviceRequest.address', 'Gedimino pr. 1')
            ->has('offers', 1)
            ->where('offers.0.id', $offer->id)
            ->where('offers.0.can.accept', true)
            ->where('offers.0.message', fn (string $message) => mb_strlen($message) <= 143)
            ->where('acceptedContact', null)
            ->where('can.cancel', true)
            ->where('can.complete', false));

    // Sąrašo peržiūra – dar ne pasiūlymo atidarymas
    expect($offer->refresh()->viewed_at)->toBeNull();
});

test('svetimos užklausos klientas nemato', function () {
    $request = ServiceRequest::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('service-requests.show', $request))
        ->assertForbidden();
});

test('atidarius pasiūlymą nustatomas viewed_at, o priėmus – atskleidžiami teikėjo kontaktai', function () {
    $request = Marketplace::openRequest();
    $offer = Offer::factory()->for($request)->for(Marketplace::eligibleProvider($request))->create();

    $this->actingAs($request->client)
        ->get(route('offers.show', [$request, $offer]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('offers/Show')
            ->where('offer.message', $offer->message)
            ->where('can.accept', true));
    expect($offer->refresh()->viewed_at)->not->toBeNull();

    $this->actingAs($request->client)
        ->post(route('offers.accept', $offer))
        ->assertRedirect(route('service-requests.show', $request));

    expect($request->refresh()->status)->toBe(ServiceRequestStatus::InProgress);

    $this->actingAs($request->client)
        ->get(route('service-requests.show', $request))
        ->assertInertia(fn (Assert $page) => $page
            ->where('acceptedContact.email', $offer->providerProfile->user->email)
            ->where('can.complete', true));
});

test('pasiūlymas turi priklausyti URL užklausai (scopeBindings)', function () {
    $request = Marketplace::openRequest();
    $foreign = Offer::factory()->create();

    $this->actingAs($request->client)
        ->get(route('offers.show', [$request, $foreign]))
        ->assertNotFound();
});

test('teikėjas savo pasiūlymą atidarius nukreipiamas į užklausą ir viewed_at nesikeičia', function () {
    $request = Marketplace::openRequest();
    $provider = Marketplace::eligibleProvider($request);
    $offer = Offer::factory()->for($request)->for($provider)->create();

    $this->actingAs($provider->user)
        ->get(route('offers.show', [$request, $offer]))
        ->assertRedirect(route('service-requests.show', $request));

    expect($offer->refresh()->viewed_at)->toBeNull();
});

test('kitas klientas ir teikėjas pasiūlymo priimti ar atmesti negali', function () {
    $request = Marketplace::openRequest();
    $provider = Marketplace::eligibleProvider($request);
    $offer = Offer::factory()->for($request)->for($provider)->create();

    $this->actingAs(User::factory()->create())->post(route('offers.accept', $offer))->assertForbidden();
    $this->actingAs($provider->user)->post(route('offers.accept', $offer))->assertForbidden();
    $this->actingAs(User::factory()->create())->post(route('offers.decline', $offer))->assertForbidden();

    expect($offer->refresh()->status)->toBe(OfferStatus::Pending);
});

test('atmetimas per HTTP', function () {
    $request = Marketplace::openRequest();
    $offer = Offer::factory()->for($request)->for(Marketplace::eligibleProvider($request))->create();

    $this->actingAs($request->client)
        ->post(route('offers.decline', $offer))
        ->assertRedirect(route('service-requests.show', $request));

    expect($offer->refresh())->status->toBe(OfferStatus::Declined)->responded_at->not->toBeNull();
});

test('atšaukimas: vykdomai užklausai reikia priežasties, atvirai – ne', function () {
    $open = Marketplace::openRequest();
    $this->actingAs($open->client)
        ->post(route('service-requests.cancel', $open))
        ->assertRedirect(route('service-requests.show', $open));
    expect($open->refresh()->status)->toBe(ServiceRequestStatus::Cancelled);

    $inProgress = ServiceRequest::factory()->inProgress()->create();
    $this->actingAs($inProgress->client)
        ->post(route('service-requests.cancel', $inProgress), ['reason' => ''])
        ->assertSessionHasErrors(['reason' => 'Vykdomą užklausą galima atšaukti tik nurodžius priežastį.']);

    $this->actingAs($inProgress->client)
        ->post(route('service-requests.cancel', $inProgress), ['reason' => 'Teikėjas neatvyko'])
        ->assertSessionHasNoErrors();
    expect($inProgress->refresh())
        ->status->toBe(ServiceRequestStatus::Cancelled)
        ->cancellation_reason->toBe('Teikėjas neatvyko');
});

test('svetimos užklausos atšaukti ar užbaigti negalima, užbaigta užklausa nebeatšaukiama', function () {
    $inProgress = ServiceRequest::factory()->inProgress()->create();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->post(route('service-requests.cancel', $inProgress), ['reason' => 'x'])->assertForbidden();
    $this->actingAs($stranger)->post(route('service-requests.complete', $inProgress))->assertForbidden();

    $completed = ServiceRequest::factory()->completed()->create();
    $this->actingAs($completed->client)->post(route('service-requests.cancel', $completed))->assertForbidden();
});

test('klientas pažymi darbą atliktu', function () {
    $request = ServiceRequest::factory()->inProgress()->create();

    $this->actingAs($request->client)
        ->post(route('service-requests.complete', $request))
        ->assertRedirect(route('service-requests.show', $request))
        ->assertInertiaFlash('toast.message', 'Puiku! Užklausa pažymėta kaip atlikta.');

    expect($request->refresh()->status)->toBe(ServiceRequestStatus::Completed);
});

test('pasenusi būsena: priimti jau priimtos užklausos pasiūlymą neleidžia Policy', function () {
    $request = ServiceRequest::factory()->inProgress()->create();
    $other = Offer::factory()->for($request)->create();

    $this->actingAs($request->client)->post(route('offers.accept', $other))->assertForbidden();
});
