<?php

use App\Actions\Offers\AcceptOffer;
use App\Actions\Offers\DeclineOffer;
use App\Actions\Offers\MarkOfferViewed;
use App\Actions\Offers\SendOffer;
use App\Actions\Offers\WithdrawOffer;
use App\Actions\ServiceRequests\CancelServiceRequest;
use App\Actions\ServiceRequests\CompleteServiceRequest;
use App\Actions\ServiceRequests\ExpireServiceRequest;
use App\Actions\ServiceRequests\PublishServiceRequest;
use App\Enums\CreditTransactionType;
use App\Enums\OfferDeclineReason;
use App\Enums\OfferStatus;
use App\Enums\ServiceRequestStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Jobs\NotifyMatchingProviders;
use App\Models\CreditTransaction;
use App\Models\Offer;
use App\Models\ProviderProfile;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\OfferAccepted;
use App\Notifications\OfferDeclined;
use App\Notifications\ServiceRequestCancelled;
use App\Notifications\ServiceRequestPublished;
use App\Notifications\ServiceRequestRejected;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\Support\Marketplace;

/**
 * Būsenų perėjimai ir kreditų grąžinimo taisyklės (docs/STATES.md 1–3 sk.) Action lygiu.
 */
beforeEach(function () {
    Notification::fake();
    $this->request = Marketplace::openRequest(cost: 2);
});

/**
 * Teikėjas išsiunčia pasiūlymą per tikrą SendOffer (su kreditų nurašymu).
 */
function sendOfferFor(ServiceRequest $request, int $credits = 5): Offer
{
    $provider = Marketplace::eligibleProvider($request, credits: $credits);

    return app(SendOffer::class)->handle($provider, $request, [
        'message' => 'Galiu atlikti darbus šią savaitę, kaina galutinė.',
        'price_cents' => 10000,
        'price_type' => 'fixed',
        'duration_text' => null,
        'start_date' => null,
    ]);
}

function balanceOf(Offer $offer): int
{
    return ProviderProfile::query()->whereKey($offer->provider_profile_id)->value('credits_balance');
}

// --- pending → open / cancelled -------------------------------------------------------------

test('paskelbimas: published_at, expires_at +30 d. ir atitikimo job\'as', function () {
    Queue::fake();
    $this->freezeSecond();
    $pending = ServiceRequest::factory()->pending()->create();

    app(PublishServiceRequest::class)->handle($pending, byAdmin: true);

    expect($pending->refresh())
        ->status->toBe(ServiceRequestStatus::Open)
        ->published_at->toEqual(now())
        ->expires_at->toEqual(now()->addDays(30));
    Queue::assertPushed(NotifyMatchingProviders::class, fn ($job) => $job->serviceRequest->is($pending));
    Notification::assertSentTo($pending->client, ServiceRequestPublished::class);
});

test('jau atviros užklausos paskelbti dar kartą negalima', function () {
    expect(fn () => app(PublishServiceRequest::class)->handle($this->request))
        ->toThrow(InvalidStateTransitionException::class);
});

test('admin atmeta laukiančią užklausą su priežastimi – klientas gauna pranešimą', function () {
    $pending = ServiceRequest::factory()->pending()->create();

    app(CancelServiceRequest::class)->handle($pending, User::factory()->admin()->create(), 'Tekste yra telefono numeris');

    expect($pending->refresh())
        ->status->toBe(ServiceRequestStatus::Cancelled)
        ->cancelled_at->not->toBeNull()
        ->cancellation_reason->toBe('Tekste yra telefono numeris');
    Notification::assertSentTo($pending->client, ServiceRequestRejected::class);
});

// --- open → in_progress (priėmimas) --------------------------------------------------------

test('priėmus pasiūlymą: užklausa vykdoma, kiti atmesti ir informuoti, išrinktasis gauna pranešimą', function () {
    $chosen = sendOfferFor($this->request);
    $other = sendOfferFor($this->request);
    $withdrawn = Offer::factory()->withdrawn()->for($this->request)->create();

    app(AcceptOffer::class)->handle($chosen);

    expect($this->request->refresh())
        ->status->toBe(ServiceRequestStatus::InProgress)
        ->accepted_offer_id->toBe($chosen->id)
        ->and($chosen->refresh())
        ->status->toBe(OfferStatus::Accepted)
        ->responded_at->not->toBeNull()
        ->viewed_at->not->toBeNull()
        ->and($other->refresh()->status)->toBe(OfferStatus::Declined)
        ->and($other->responded_at)->toBeNull()
        ->and($withdrawn->refresh()->status)->toBe(OfferStatus::Withdrawn);

    Notification::assertSentTo($chosen->providerProfile->user, OfferAccepted::class);
    Notification::assertSentTo($other->providerProfile->user, OfferDeclined::class,
        fn (OfferDeclined $n) => $n->reason === OfferDeclineReason::OtherAccepted && $n->creditsRefunded === 0);
    // Priėmus kitą – kreditai negrąžinami
    expect(balanceOf($other))->toBe(3);
});

test('antrą pasiūlymą priimti nebegalima', function () {
    $first = sendOfferFor($this->request);
    $second = sendOfferFor($this->request);
    app(AcceptOffer::class)->handle($first);

    expect(fn () => app(AcceptOffer::class)->handle($second->refresh()))->toThrow(InvalidStateTransitionException::class);
});

test('klientas atmeta pasiūlymą: responded_at, pranešimas, kreditai negrąžinami', function () {
    $offer = sendOfferFor($this->request);

    app(DeclineOffer::class)->handle($offer);

    expect($offer->refresh())
        ->status->toBe(OfferStatus::Declined)
        ->responded_at->not->toBeNull()
        ->and(balanceOf($offer))->toBe(3)
        ->and($this->request->refresh()->status)->toBe(ServiceRequestStatus::Open);
    Notification::assertSentTo($offer->providerProfile->user, OfferDeclined::class,
        fn (OfferDeclined $n) => $n->reason === OfferDeclineReason::ClientDeclined);
});

test('peržiūra įsimenama vieną kartą', function () {
    $offer = Offer::factory()->for($this->request)->create();
    $this->travelTo(now()->subHour());
    app(MarkOfferViewed::class)->handle($offer);
    $first = $offer->refresh()->viewed_at;
    $this->travelBack();

    app(MarkOfferViewed::class)->handle($offer);

    expect($offer->refresh()->viewed_at)->toEqual($first);
});

// --- in_progress → completed ----------------------------------------------------------------

test('užbaigus: completed_at ir teikėjo completed_jobs_count + 1', function () {
    $offer = sendOfferFor($this->request);
    app(AcceptOffer::class)->handle($offer);

    app(CompleteServiceRequest::class)->handle($this->request->refresh());

    expect($this->request->refresh())
        ->status->toBe(ServiceRequestStatus::Completed)
        ->completed_at->not->toBeNull()
        ->and(ProviderProfile::query()->find($offer->provider_profile_id)->completed_jobs_count)->toBe(1);
});

test('atviros užklausos užbaigti negalima', function () {
    expect(fn () => app(CompleteServiceRequest::class)->handle($this->request))
        ->toThrow(InvalidStateTransitionException::class);
});

// --- STATES.md 3 sk.: kreditų grąžinimo taisyklės -------------------------------------------

test('3.1 klientas atšaukė atvirą užklausą – grąžinama už visus pending pasiūlymus', function () {
    $viewed = sendOfferFor($this->request);
    app(MarkOfferViewed::class)->handle($viewed);
    $unviewed = sendOfferFor($this->request);

    app(CancelServiceRequest::class)->handle($this->request, $this->request->client);

    foreach ([$viewed, $unviewed] as $offer) {
        expect($offer->refresh()->status)->toBe(OfferStatus::Declined)
            ->and(balanceOf($offer))->toBe(5);
        Notification::assertSentTo($offer->providerProfile->user, OfferDeclined::class,
            fn (OfferDeclined $n) => $n->reason === OfferDeclineReason::RequestCancelled && $n->creditsRefunded === 2);
    }

    expect(CreditTransaction::query()->where('type', CreditTransactionType::Refund)->count())->toBe(2);
    Notification::assertNotSentTo($this->request->client, ServiceRequestRejected::class);
});

test('3.1 admin atšaukė atvirą užklausą – taip pat grąžinama, klientas informuojamas', function () {
    $offer = sendOfferFor($this->request);

    app(CancelServiceRequest::class)->handle($this->request, User::factory()->admin()->create(), 'Pažeidžia taisykles');

    expect(balanceOf($offer))->toBe(5);
    Notification::assertSentTo($this->request->client, ServiceRequestRejected::class);
});

test('3.2–3.3 pasibaigus: grąžinama tik už neatidarytus pasiūlymus', function () {
    $unviewed = sendOfferFor($this->request);
    $viewed = sendOfferFor($this->request);
    app(MarkOfferViewed::class)->handle($viewed);
    $this->request->forceFill(['expires_at' => now()->subMinute()])->save();

    expect(app(ExpireServiceRequest::class)->handle($this->request))->toBeTrue();

    expect($this->request->refresh()->status)->toBe(ServiceRequestStatus::Expired)
        ->and($unviewed->refresh()->status)->toBe(OfferStatus::Declined)
        ->and($viewed->refresh()->status)->toBe(OfferStatus::Declined)
        ->and(balanceOf($unviewed))->toBe(5)
        ->and(balanceOf($viewed))->toBe(3);
    Notification::assertSentTo($unviewed->providerProfile->user, OfferDeclined::class,
        fn (OfferDeclined $n) => $n->reason === OfferDeclineReason::RequestExpired && $n->creditsRefunded === 2);
    Notification::assertSentTo($viewed->providerProfile->user, OfferDeclined::class,
        fn (OfferDeclined $n) => $n->creditsRefunded === 0);
});

test('3.4 klientas atmetė – negrąžinama; 3.5 teikėjas atšaukė – negrąžinama', function () {
    $declined = sendOfferFor($this->request);
    app(DeclineOffer::class)->handle($declined);

    $withdrawn = sendOfferFor($this->request);
    app(WithdrawOffer::class)->handle($withdrawn);

    // Net vėliau atšaukus užklausą, už jau atmestus / atšauktus pasiūlymus negrąžinama
    app(CancelServiceRequest::class)->handle($this->request, $this->request->client);

    expect(balanceOf($declined))->toBe(3)
        ->and(balanceOf($withdrawn))->toBe(3)
        ->and(CreditTransaction::query()->where('type', CreditTransactionType::Refund)->count())->toBe(0);
});

test('3.6 atšaukus vykdomą užklausą: reikia priežasties, kreditai negrąžinami, teikėjas informuojamas', function () {
    $offer = sendOfferFor($this->request);
    app(AcceptOffer::class)->handle($offer);
    $this->request->refresh();

    expect(fn () => app(CancelServiceRequest::class)->handle($this->request, $this->request->client))
        ->toThrow(ValidationException::class);

    app(CancelServiceRequest::class)->handle($this->request, $this->request->client, 'Teikėjas neatvyko');

    expect($this->request->refresh())
        ->status->toBe(ServiceRequestStatus::Cancelled)
        ->cancellation_reason->toBe('Teikėjas neatvyko')
        ->and($offer->refresh()->status)->toBe(OfferStatus::Accepted)
        ->and(balanceOf($offer))->toBe(3);
    Notification::assertSentTo($offer->providerProfile->user, ServiceRequestCancelled::class);
});

test('galutinės būsenos užklausos atšaukti nebegalima', function () {
    $completed = ServiceRequest::factory()->completed()->create();

    expect(fn () => app(CancelServiceRequest::class)->handle($completed, $completed->client))
        ->toThrow(InvalidStateTransitionException::class);
});

test('dar negaliojančios užklausos komanda nepaliečia', function () {
    expect(app(ExpireServiceRequest::class)->handle($this->request))->toBeFalse()
        ->and($this->request->refresh()->status)->toBe(ServiceRequestStatus::Open);
});
