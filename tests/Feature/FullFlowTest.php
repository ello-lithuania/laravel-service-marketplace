<?php

use App\Enums\CreditTransactionType;
use App\Enums\OfferStatus;
use App\Enums\ServiceRequestStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Jobs\NotifyMatchingProviders;
use App\Models\Category;
use App\Models\City;
use App\Models\CreditTransaction;
use App\Models\Offer;
use App\Models\ProviderProfile;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\NewMatchingRequest;
use App\Notifications\NewOffer;
use App\Notifications\OfferAccepted;
use App\Notifications\OfferDeclined;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/**
 * Visas Etapo 5 srautas per HTTP: užklausa → automatinis paskelbimas → pranešimai teikėjams →
 * pasiūlymai su kreditų nurašymu → priėmimas → užbaigimas.
 */
test('visas srautas nuo užklausos iki atlikto darbo', function () {
    Notification::fake();

    // Kategorijų medis: Statyba → Apdaila → Plytelių klijavimas (pasiūlymas kainuoja 2 kreditus)
    $leaf = Category::factory()->leaf()->create(['offer_cost_credits' => 2]);
    $city = City::factory()->create();
    $client = User::factory()->create();

    // Du tinkami teikėjai: vienas pasirinko lapą, kitas – visą 2 lygio kategoriją, ir trečias – kitame mieste
    $alice = ProviderProfile::factory()->withCredits(5)->create();
    $alice->categories()->attach($leaf->id);
    $alice->serviceAreas()->attach($city->id);
    $bob = ProviderProfile::factory()->withCredits(2)->wholeCountry()->create();
    $bob->categories()->attach($leaf->parent_id);
    $stranger = ProviderProfile::factory()->withCredits(5)->create();
    $stranger->categories()->attach($leaf->id);

    // 1. Klientas sukuria užklausą → švari, el. paštas patvirtintas → iškart open
    $this->actingAs($client)->post('/uzklausos', [
        'category_id' => $leaf->id,
        'city_id' => $city->id,
        'title' => 'Plytelių klijavimas vonios kambaryje',
        'description' => 'Reikia suklijuoti plyteles vonios kambaryje, plotas apie 12 m². Plyteles jau turiu.',
        'address' => 'Gedimino pr. 1-5',
        'budget_min' => '300',
        'budget_max' => '600',
        'start_preference' => 'this_month',
    ])->assertSessionHasNoErrors();

    $request = ServiceRequest::query()->sole();
    expect($request->status)->toBe(ServiceRequestStatus::Open);

    // 2. Atitikimo job'as pranešė tik tinkamiems teikėjams
    Notification::assertSentTo([$alice->user, $bob->user], NewMatchingRequest::class);
    Notification::assertNotSentTo($stranger->user, NewMatchingRequest::class);

    // 3. Abu siunčia pasiūlymus – nurašoma po 2 kreditus
    foreach ([[$alice, '450'], [$bob, '380']] as [$provider, $price]) {
        $this->actingAs($provider->user)->post(route('offers.store', $request), [
            'message' => 'Laba diena, darbus galiu atlikti per dvi dienas, kaina galutinė.',
            'price_type' => 'fixed',
            'price' => $price,
        ])->assertSessionHasNoErrors();
    }

    expect($alice->refresh()->credits_balance)->toBe(3)
        ->and($bob->refresh()->credits_balance)->toBe(0)
        ->and($request->refresh()->offers_count)->toBe(2)
        ->and(CreditTransaction::query()->where('type', CreditTransactionType::Offer)->count())->toBe(2);
    Notification::assertSentToTimes($client, NewOffer::class, 2);

    $aliceOffer = Offer::query()->where('provider_profile_id', $alice->id)->sole();
    $bobOffer = Offer::query()->where('provider_profile_id', $bob->id)->sole();

    // 4. Klientas atidaro Alice pasiūlymą ir jį priima
    $this->actingAs($client)->get(route('offers.show', [$request, $aliceOffer]))->assertOk();
    $this->actingAs($client)->post(route('offers.accept', $aliceOffer))->assertRedirect();

    expect($request->refresh())
        ->status->toBe(ServiceRequestStatus::InProgress)
        ->accepted_offer_id->toBe($aliceOffer->id)
        ->and($bobOffer->refresh()->status)->toBe(OfferStatus::Declined);
    Notification::assertSentTo($alice->user, OfferAccepted::class);
    Notification::assertSentTo($bob->user, OfferDeclined::class);

    // Alice mato adresą ir kliento telefoną, Bob – ne
    $this->actingAs($alice->user)->get(route('service-requests.show', $request))
        ->assertInertia(fn ($page) => $page->where('serviceRequest.address', 'Gedimino pr. 1-5')->where('client.phone', $client->phone));
    $this->actingAs($bob->user)->get(route('service-requests.show', $request))
        ->assertInertia(fn ($page) => $page->missing('serviceRequest.address')->where('client.phone', null));

    // 5. Darbas atliktas
    $this->actingAs($client)->post(route('service-requests.complete', $request))->assertRedirect();

    expect($request->refresh()->status)->toBe(ServiceRequestStatus::Completed)
        ->and($alice->refresh()->completed_jobs_count)->toBe(1)
        // Ledger visada sutampa su balansu
        ->and((int) $alice->creditTransactions()->sum('amount'))->toBe(-2)
        ->and((int) $bob->creditTransactions()->sum('amount'))->toBe(-2);
});

test('paskelbimas įdeda atitikimo job\'ą į eilę (Queue::fake)', function () {
    Queue::fake();
    $leaf = Category::factory()->leaf()->create();

    $this->actingAs(User::factory()->create())->post('/uzklausos', [
        'category_id' => $leaf->id,
        'city_id' => City::factory()->create()->id,
        'title' => 'Reikia pakeisti maišytuvą',
        'description' => 'Virtuvėje laša maišytuvas, reikia pakeisti nauju. Maišytuvą nupirksiu pats.',
        'start_preference' => 'asap',
    ]);

    Queue::assertPushed(NotifyMatchingProviders::class, 1);
});

test('InvalidStateTransitionException grąžina atgal su klaidos pranešimu', function () {
    $exception = InvalidStateTransitionException::for(ServiceRequestStatus::Completed, ServiceRequestStatus::Open);

    expect($exception->getMessage())->toBe('Užklausos būsenos „Atlikta" negalima pakeisti į „Laukia pasiūlymų". Atnaujinkite puslapį.');

    $request = Request::create('/uzklausos/x', 'POST');
    $response = $exception->render($request);
    expect($response->getStatusCode())->toBe(302);

    $json = Request::create('/uzklausos/x', 'POST', server: ['HTTP_ACCEPT' => 'application/json']);
    expect($exception->render($json)->getStatusCode())->toBe(409);
});
