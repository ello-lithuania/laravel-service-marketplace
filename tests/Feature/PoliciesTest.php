<?php

use App\Enums\ServiceRequestStatus;
use App\Models\Offer;
use App\Models\ProviderProfile;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\Support\Marketplace;

/**
 * ServiceRequestPolicy ir OfferPolicy: kas ką gali (docs/STATES.md – kas inicijuoja perėjimą).
 */
beforeEach(function () {
    $this->request = Marketplace::openRequest();
    $this->client = $this->request->client;
    $this->provider = Marketplace::eligibleProvider($this->request);
});

test('užklausą mato savininkas, administratorius ir tinkamas teikėjas', function () {
    expect($this->client->can('view', $this->request))->toBeTrue()
        ->and(User::factory()->admin()->create()->can('view', $this->request))->toBeTrue()
        ->and($this->provider->user->can('view', $this->request))->toBeTrue()
        ->and(User::factory()->create()->can('view', $this->request))->toBeFalse()
        ->and(ProviderProfile::factory()->create()->user->can('view', $this->request))->toBeFalse()
        ->and(User::factory()->provider()->create()->can('view', $this->request))->toBeFalse();
});

test('neatviros užklausos teikėjas nemato, nebent siuntė pasiūlymą', function () {
    $this->request->forceFill(['status' => ServiceRequestStatus::InProgress])->save();
    $other = Marketplace::eligibleProvider($this->request);
    Offer::factory()->for($this->request)->for($this->provider)->create();

    expect($this->provider->user->can('view', $this->request))->toBeTrue()
        ->and($other->user->can('view', $this->request))->toBeFalse();
});

test('atšaukti: savininkas ar administratorius, kol būsena leidžia', function (string $status, bool $allowed) {
    $this->request->forceFill(['status' => $status])->save();

    expect($this->client->can('cancel', $this->request))->toBe($allowed)
        ->and(User::factory()->admin()->create()->can('cancel', $this->request))->toBe($allowed)
        ->and($this->provider->user->can('cancel', $this->request))->toBeFalse();
})->with([
    ['pending', true], ['open', true], ['in_progress', true],
    ['completed', false], ['cancelled', false], ['expired', false],
]);

test('užbaigti gali tik klientas ir tik vykdomą', function () {
    expect($this->client->can('complete', $this->request))->toBeFalse();

    $this->request->forceFill(['status' => ServiceRequestStatus::InProgress])->save();

    expect($this->client->can('complete', $this->request))->toBeTrue()
        ->and(User::factory()->admin()->create()->can('complete', $this->request))->toBeFalse();
});

test('kurti užklausą gali tik neužblokuotas klientas; patvirtinti – tik administratorius', function () {
    $pending = ServiceRequest::factory()->pending()->create();

    expect(Gate::forUser($this->client)->allows('create', ServiceRequest::class))->toBeTrue()
        ->and(Gate::forUser(User::factory()->banned()->create())->allows('create', ServiceRequest::class))->toBeFalse()
        ->and(Gate::forUser($this->provider->user)->allows('create', ServiceRequest::class))->toBeFalse()
        ->and(User::factory()->admin()->create()->can('publish', $pending))->toBeTrue()
        ->and($pending->client->can('publish', $pending))->toBeFalse();
});

test('pasiūlymo siuntimo atsisakymai turi aiškias priežastis', function () {
    $inspect = fn (User $user) => Gate::forUser($user)->inspect('create', [Offer::class, $this->request]);

    expect($inspect($this->provider->user)->allowed())->toBeTrue()
        ->and($inspect($this->client)->message())->toBe('Pasiūlymus gali siųsti tik paslaugų teikėjai.')
        ->and($inspect(ProviderProfile::factory()->create()->user)->message())->toContain('ne jūsų paslaugų srityje');

    $this->provider->forceFill(['status' => 'suspended'])->save();
    expect($inspect($this->provider->user->refresh())->message())->toBe('Jūsų profilis neaktyvus, todėl pasiūlymų siųsti negalite.');
});

test('pasiūlymą atšaukti gali autorius, priimti ir atmesti – užklausos klientas', function () {
    $offer = Offer::factory()->for($this->request)->for($this->provider)->create();

    expect($this->provider->user->can('withdraw', $offer))->toBeTrue()
        ->and($this->client->can('withdraw', $offer))->toBeFalse()
        ->and($this->client->can('accept', $offer))->toBeTrue()
        ->and($this->client->can('decline', $offer))->toBeTrue()
        ->and($this->provider->user->can('accept', $offer))->toBeFalse()
        ->and($this->provider->user->can('view', $offer))->toBeTrue()
        ->and(User::factory()->create()->can('view', $offer))->toBeFalse();

    $offer->forceFill(['status' => 'declined'])->save();

    expect($this->provider->user->can('withdraw', $offer->refresh()))->toBeFalse()
        ->and($this->client->can('accept', $offer))->toBeFalse();
});
