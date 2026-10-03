<?php

use App\Actions\Moderation\BanUser;
use App\Actions\Moderation\UnbanUser;
use App\Actions\Offers\SendOffer;
use App\Enums\OfferStatus;
use App\Enums\ProviderStatus;
use App\Enums\ServiceRequestStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Category;
use App\Models\Offer;
use App\Models\ProviderProfile;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Marketplace;

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->admin()->create();
});

test('užblokavus teikėją: banned_at, priežastis, naujas remember_token, profilis suspended', function () {
    $profile = ProviderProfile::factory()->create();
    $user = $profile->user;
    $user->forceFill(['remember_token' => 'senas-tokenas'])->save();

    app(BanUser::class)->handle($user, $this->admin, 'Šlamštas');

    expect($user->refresh())
        ->banned_at->not->toBeNull()
        ->ban_reason->toBe('Šlamštas')
        ->remember_token->not->toBe('senas-tokenas')
        ->and($profile->refresh()->status)->toBe(ProviderStatus::Suspended);
});

test('teikėjo laukiantys pasiūlymai atviroms užklausoms atšaukiami, kreditai negrąžinami', function () {
    $request = Marketplace::openRequest();
    $provider = Marketplace::eligibleProvider($request, credits: 5);
    $pending = Offer::factory()->for($request)->for($provider)->create(['credits_spent' => 1]);
    $request->forceFill(['offers_count' => 1])->save();
    $accepted = Offer::factory()->accepted()->for($provider)->create();

    $result = app(BanUser::class)->handle($provider->user, $this->admin, 'Sukčiavimas');

    expect($result['withdrawn_offers'])->toBe(1)
        ->and($pending->refresh()->status)->toBe(OfferStatus::Withdrawn)
        ->and($accepted->refresh()->status)->toBe(OfferStatus::Accepted)
        ->and($request->refresh()->offers_count)->toBe(0)
        ->and($provider->refresh()->credits_balance)->toBe(5);
});

test('kliento laukiančios ir atviros užklausos atšaukiamos, teikėjams grąžinami kreditai', function () {
    $client = User::factory()->create();
    $open = Marketplace::openRequest(cost: 2, attributes: ['client_id' => $client->id]);
    $pending = ServiceRequest::factory()->pending()->create(['client_id' => $client->id]);
    $completed = ServiceRequest::factory()->completed()->create(['client_id' => $client->id]);
    $provider = Marketplace::eligibleProvider($open, credits: 5);
    $sent = app(SendOffer::class)->handle($provider, $open, [
        'message' => 'Galiu atlikti darbus šią savaitę, kaina galutinė.',
        'price_cents' => 10000,
        'price_type' => 'fixed',
        'duration_text' => null,
        'start_date' => null,
    ]);
    expect($provider->refresh()->credits_balance)->toBe(3);

    $result = app(BanUser::class)->handle($client, $this->admin, 'Netikros užklausos');

    expect($result['cancelled_requests'])->toBe(2)
        ->and($open->refresh()->status)->toBe(ServiceRequestStatus::Cancelled)
        ->and($pending->refresh()->status)->toBe(ServiceRequestStatus::Cancelled)
        ->and($completed->refresh()->status)->toBe(ServiceRequestStatus::Completed)
        ->and($sent->refresh()->status)->toBe(OfferStatus::Declined)
        ->and($provider->refresh()->credits_balance)->toBe(5);
});

test('pasirinktinai pasiūlymų ir užklausų neatšaukia', function () {
    $client = User::factory()->create();
    $open = ServiceRequest::factory()->create(['client_id' => $client->id]);

    $result = app(BanUser::class)->handle($client, $this->admin, 'Priežastis', withdrawOffers: false, cancelRequests: false);

    expect($result)->toBe(['withdrawn_offers' => 0, 'cancelled_requests' => 0])
        ->and($open->refresh()->status)->toBe(ServiceRequestStatus::Open);
});

test('DB sesijos ištrinamos', function () {
    config(['session.driver' => 'database']);
    $user = User::factory()->create();
    DB::table('sessions')->insert([
        ['id' => 'a', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
        ['id' => 'b', 'user_id' => $this->admin->id, 'payload' => '', 'last_activity' => time()],
    ]);

    app(BanUser::class)->handle($user, $this->admin, 'Priežastis');

    expect(DB::table('sessions')->pluck('id')->all())->toBe(['b']);
});

test('administratoriaus ir jau užblokuoto vartotojo užblokuoti negalima', function () {
    $otherAdmin = User::factory()->admin()->create();
    $banned = User::factory()->banned()->create();

    expect(fn () => app(BanUser::class)->handle($otherAdmin, $this->admin, 'x'))
        ->toThrow(InvalidStateTransitionException::class)
        ->and(fn () => app(BanUser::class)->handle($banned, $this->admin, 'x'))
        ->toThrow(InvalidStateTransitionException::class);
});

test('atblokavus profilis atkuriamas: active, jei užpildytas, kitaip pending; arba paliekamas', function () {
    $complete = ProviderProfile::factory()->suspended()->create();
    $complete->categories()->attach(Category::factory()->leaf()->create());
    $complete->forceFill(['serves_whole_country' => true])->save();
    $complete->user->forceFill(['banned_at' => now(), 'ban_reason' => 'x'])->save();

    $incomplete = ProviderProfile::factory()->suspended()->create();
    $incomplete->user->forceFill(['banned_at' => now()])->save();

    $kept = ProviderProfile::factory()->suspended()->create();
    $kept->user->forceFill(['banned_at' => now()])->save();

    app(UnbanUser::class)->handle($complete->user);
    app(UnbanUser::class)->handle($incomplete->user);
    app(UnbanUser::class)->handle($kept->user, restoreProfile: false);

    expect($complete->user->refresh())->banned_at->toBeNull()->ban_reason->toBeNull()
        ->and($complete->refresh()->status)->toBe(ProviderStatus::Active)
        ->and($incomplete->refresh()->status)->toBe(ProviderStatus::Pending)
        ->and($kept->refresh()->status)->toBe(ProviderStatus::Suspended);
});
