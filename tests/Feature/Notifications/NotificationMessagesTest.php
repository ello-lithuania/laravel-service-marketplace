<?php

use App\Enums\OfferDeclineReason;
use App\Models\Offer;
use App\Models\ServiceRequest;
use App\Notifications\NewMatchingRequest;
use App\Notifications\NewOffer;
use App\Notifications\OfferAccepted;
use App\Notifications\OfferDeclined;
use App\Notifications\ServiceRequestCancelled;
use App\Notifications\ServiceRequestPublished;
use App\Notifications\ServiceRequestRejected;

/**
 * Laiškų ir varpelio tekstai lietuviškai; data raktai suderinti su seed'ais (NotificationGenerator).
 */
beforeEach(function () {
    $this->request = ServiceRequest::factory()->create([
        'title' => 'Buto dažymas',
        'budget_min_cents' => 20000,
        'budget_max_cents' => 50000,
    ]);
    $this->offer = Offer::factory()->for($this->request)->create(['price_cents' => 32050]);
    $this->user = $this->request->client;
});

test('NewMatchingRequest – laiškas su biudžetu ir kredito kaina', function () {
    $mail = (new NewMatchingRequest($this->request))->toMail($this->user);

    expect($mail->subject)->toBe('Nauja užklausa: Buto dažymas')
        ->and($mail->greeting)->toBe('Sveiki!')
        ->and($mail->actionUrl)->toBe(route('service-requests.show', $this->request))
        ->and(implode(' ', $mail->introLines))->toContain('Biudžetas: 200 € – 500 €')
        ->and(implode(' ', $mail->outroLines))->toContain('Pasiūlymas kainuoja 1 kred.');

    expect((new NewMatchingRequest($this->request))->toArray($this->user))->toBe([
        'service_request_id' => $this->request->id,
        'message' => 'Nauja užklausa jūsų srityje: „Buto dažymas"',
    ]);
});

test('NewOffer – kaina ir nuoroda į pasiūlymą', function () {
    $notification = new NewOffer($this->offer);
    $mail = $notification->toMail($this->user);
    $provider = $this->offer->providerProfile->display_name;

    expect($mail->subject)->toBe('Naujas pasiūlymas: Buto dažymas')
        ->and(implode(' ', $mail->introLines))->toContain('Kaina: 320,50 € (fiksuota)')
        ->and($mail->actionUrl)->toBe(route('offers.show', [$this->request, $this->offer]))
        ->and($notification->toArray($this->user))->toBe([
            'offer_id' => $this->offer->id,
            'service_request_id' => $this->request->id,
            'message' => $provider.' atsiuntė pasiūlymą užklausai „Buto dažymas"',
        ]);
});

test('OfferAccepted', function () {
    $notification = new OfferAccepted($this->offer);

    expect($notification->toMail($this->user)->subject)->toBe('Jūsų pasiūlymas priimtas: Buto dažymas')
        ->and($notification->toArray($this->user)['message'])->toBe('Jūsų pasiūlymas užklausai „Buto dažymas" priimtas!');
});

test('OfferDeclined – priežastis ir grąžinti kreditai', function (OfferDeclineReason $reason, int $refunded, string $message) {
    $data = (new OfferDeclined($this->offer, $reason, $refunded))->toArray($this->user);

    expect($data['message'])->toBe($message)
        ->and($data['reason'])->toBe($reason->value)
        ->and($data['credits_refunded'])->toBe($refunded);
})->with([
    [OfferDeclineReason::ClientDeclined, 0, 'Klientas atmetė jūsų pasiūlymą užklausai „Buto dažymas".'],
    [OfferDeclineReason::OtherAccepted, 0, 'Klientas pasirinko kitą pasiūlymą užklausai „Buto dažymas".'],
    [OfferDeclineReason::RequestCancelled, 2, 'Užklausa „Buto dažymas" atšaukta. Grąžinta kreditų: 2.'],
    [OfferDeclineReason::RequestExpired, 1, 'Užklausa „Buto dažymas" pasibaigė – klientas pasiūlymo nepasirinko. Grąžinta kreditų: 1.'],
]);

test('ServiceRequestCancelled, Published, Rejected', function () {
    $this->request->forceFill(['cancellation_reason' => 'Teikėjas neatvyko'])->save();

    $cancelled = (new ServiceRequestCancelled($this->request))->toMail($this->user);
    $rejected = new ServiceRequestRejected($this->request);

    expect(implode(' ', $cancelled->introLines))->toContain('Priežastis: Teikėjas neatvyko')
        ->and((new ServiceRequestPublished($this->request))->toArray($this->user)['message'])
        ->toBe('Jūsų užklausa „Buto dažymas" paskelbta')
        ->and($rejected->toArray($this->user)['message'])->toBe('Jūsų užklausa „Buto dažymas" atmesta: Teikėjas neatvyko')
        ->and($rejected->toMail($this->user)->actionUrl)->toBe(route('service-requests.create'));
});

test('visi laiškai baigiasi nuoroda į nustatymus', function () {
    $mail = (new OfferAccepted($this->offer))->toMail($this->user);

    expect(end($mail->outroLines))->toContain('Pranešimai');
});
