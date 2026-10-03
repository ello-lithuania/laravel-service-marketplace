<?php

use App\Enums\OfferStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\CreditTransaction;
use App\Models\Offer;
use App\Models\ProviderProfile;
use App\Models\ServiceRequest;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Marketplace;

beforeEach(function () {
    Notification::fake();
});

test('komanda uždaro tik atviras užklausas, kurių expires_at praėjo', function () {
    $past = ServiceRequest::factory()->create(['expires_at' => now()->subHour()]);
    $future = ServiceRequest::factory()->create(['expires_at' => now()->addDay()]);
    $inProgress = ServiceRequest::factory()->inProgress()->create(['expires_at' => now()->subDay()]);

    $this->artisan('service-requests:expire')
        ->expectsOutputToContain('Uždaryta pasibaigusių užklausų: 1')
        ->assertSuccessful();

    expect($past->refresh()->status)->toBe(ServiceRequestStatus::Expired)
        ->and($future->refresh()->status)->toBe(ServiceRequestStatus::Open)
        ->and($inProgress->refresh()->status)->toBe(ServiceRequestStatus::InProgress);
});

test('pasibaigus kreditai grąžinami tik už neatidarytus pasiūlymus', function () {
    $request = Marketplace::openRequest(cost: 1, attributes: ['expires_at' => now()->subMinute()]);
    $unviewed = Offer::factory()->for($request)->for(Marketplace::eligibleProvider($request, credits: 0))->create(['credits_spent' => 1]);
    $viewed = Offer::factory()->for($request)->for(Marketplace::eligibleProvider($request, credits: 0))->create(['credits_spent' => 1, 'viewed_at' => now()]);
    // Ledger nurašymai, kad būtų ką grąžinti (kaip po tikro SendOffer)
    foreach ([$unviewed, $viewed] as $offer) {
        CreditTransaction::factory()->for($offer->providerProfile)->create([
            'amount' => -1, 'balance_after' => 0, 'type' => 'offer', 'source_type' => 'offer', 'source_id' => $offer->id,
        ]);
    }

    $this->artisan('service-requests:expire')->assertSuccessful();

    expect($unviewed->refresh()->status)->toBe(OfferStatus::Declined)
        ->and(ProviderProfile::query()->find($unviewed->provider_profile_id)->credits_balance)->toBe(1)
        ->and(ProviderProfile::query()->find($viewed->provider_profile_id)->credits_balance)->toBe(0);
});

test('komanda suplanuota kas valandą be persidengimo', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event) => str_contains($event->command ?? '', 'service-requests:expire'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});
