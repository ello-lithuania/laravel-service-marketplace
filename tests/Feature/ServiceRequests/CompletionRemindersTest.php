<?php

use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\CompletionReminder;
use App\Notifications\CompletionRequested;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * docs/STATES.md 1 sk. „Papildomos taisyklės" (Etapas 6, atidėta iš Etapo 5):
 * teikėjas gali tik paprašyti pažymėti darbą atliktu; po 60 d. vykdymo klientui primenama vieną kartą.
 */
beforeEach(function () {
    Notification::fake();
    $this->request = ServiceRequest::factory()->inProgress()->create(['title' => 'Stogo remontas']);
    $this->request->load(['client', 'acceptedOffer.providerProfile.user']);
    $this->providerUser = $this->request->acceptedOffer->providerProfile->user;
});

// --- Teikėjo prašymas ------------------------------------------------------------------------

test('išrinktas teikėjas paprašo – klientas gauna pranešimą, data įsimenama', function () {
    $this->freezeSecond();

    $this->actingAs($this->providerUser)
        ->post(route('service-requests.request-completion', $this->request))
        ->assertRedirect(route('service-requests.show', $this->request))
        ->assertInertiaFlash('toast.message', 'Priminimas išsiųstas klientui.');

    expect($this->request->refresh()->completion_requested_at?->equalTo(now()))->toBeTrue()
        // Būsena nekeičiama – pažymėti gali tik klientas
        ->and($this->request->status)->toBe(ServiceRequestStatus::InProgress);

    Notification::assertSentTo($this->request->client, CompletionRequested::class, fn (CompletionRequested $n) => $n->toArray($this->request->client)['message']
        === $this->request->acceptedOffer->providerProfile->display_name.' prašo pažymėti, kad darbas „Stogo remontas" atliktas');
});

test('pakartoti galima tik po 3 dienų', function () {
    $this->actingAs($this->providerUser)->post(route('service-requests.request-completion', $this->request))->assertRedirect();

    $this->travel(2)->days();
    $this->actingAs($this->providerUser)->post(route('service-requests.request-completion', $this->request))->assertForbidden();

    $this->travel(1)->days();
    $this->travel(1)->minutes();
    $this->actingAs($this->providerUser)->post(route('service-requests.request-completion', $this->request))->assertRedirect();

    Notification::assertSentToTimes($this->request->client, CompletionRequested::class, 2);
});

test('kiti teikėjai, klientas ar ne vykdoma užklausa – negalima', function () {
    $this->actingAs(User::factory()->provider()->create())->post(route('service-requests.request-completion', $this->request))->assertForbidden();
    $this->actingAs($this->request->client)->post(route('service-requests.request-completion', $this->request))->assertForbidden();

    $completed = ServiceRequest::factory()->completed()->create();
    $completed->load('acceptedOffer.providerProfile.user');
    $this->actingAs($completed->acceptedOffer->providerProfile->user)
        ->post(route('service-requests.request-completion', $completed))
        ->assertForbidden();

    Notification::assertNothingSent();
});

test('puslapiuose: teikėjui – mygtukas ir būsena, klientui – priminimas prie „Darbas atliktas"', function () {
    $this->actingAs($this->providerUser)->get(route('service-requests.show', $this->request))
        ->assertInertia(fn (Assert $page) => $page
            ->where('completion.can_request', true)
            ->where('completion.requested_at', null));

    $this->actingAs($this->providerUser)->post(route('service-requests.request-completion', $this->request));

    $this->actingAs($this->providerUser)->get(route('service-requests.show', $this->request))
        ->assertInertia(fn (Assert $page) => $page
            ->where('completion.can_request', false)
            ->where('completion.reason', 'Priminimą jau siuntėte. Kitą galėsite išsiųsti po 3 d.')
            ->whereNot('completion.requested_at', null));

    $this->actingAs($this->request->client)->get(route('service-requests.show', $this->request))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.complete', true)
            ->whereNot('serviceRequest.completion_requested_at', null));
});

// --- 60 d. priminimas (kasdienė komanda) -----------------------------------------------------

function acceptedDaysAgo(ServiceRequest $request, int $days): void
{
    $request->acceptedOffer->forceFill(['responded_at' => now()->subDays($days)])->save();
}

test('komanda primena tik apie 60+ d. vykdomas užklausas ir tik vieną kartą', function () {
    acceptedDaysAgo($this->request, 61);
    $fresh = ServiceRequest::factory()->inProgress()->create();
    $fresh->load('acceptedOffer');
    acceptedDaysAgo($fresh, 30);
    $completed = ServiceRequest::factory()->completed()->create();
    $completed->load('acceptedOffer');
    acceptedDaysAgo($completed, 90);

    $this->artisan('service-requests:remind-completion')
        ->expectsOutput('Išsiųsta priminimų: 1')
        ->assertSuccessful();

    expect($this->request->refresh()->completion_reminded_at)->not->toBeNull()
        ->and($fresh->refresh()->completion_reminded_at)->toBeNull();
    Notification::assertSentTo($this->request->client, CompletionReminder::class, fn (CompletionReminder $n) => $n->days === 61
        && $n->toArray($this->request->client)['message'] === 'Užklausa „Stogo remontas" vykdoma jau 61 d. Ar darbas atliktas?');
    Notification::assertNotSentTo($fresh->client, CompletionReminder::class);
    Notification::assertNotSentTo($completed->client, CompletionReminder::class);

    // Kitą dieną – nebe
    $this->travel(1)->days();
    $this->artisan('service-requests:remind-completion')->expectsOutput('Išsiųsta priminimų: 0');
    Notification::assertSentToTimes($this->request->client, CompletionReminder::class, 1);
});

test('užklausa automatiškai neužbaigiama', function () {
    acceptedDaysAgo($this->request, 200);

    $this->artisan('service-requests:remind-completion')->assertSuccessful();

    expect($this->request->refresh()->status)->toBe(ServiceRequestStatus::InProgress);
});

test('komanda suplanuota kasdien 9 val. Lietuvos laiku', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'service-requests:remind-completion'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 9 * * *')
        ->and($event->timezone)->toBe('Europe/Vilnius');
});
