<?php

use App\Enums\ProviderType;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\City;
use App\Models\Complaint;
use App\Models\Conversation;
use App\Models\CreditTransaction;
use App\Models\Message;
use App\Models\Offer;
use App\Models\Payment;
use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('vartotojas turi teikėjo profilį ir pasiūlymus per jį (hasOne + hasManyThrough)', function () {
    $profile = ProviderProfile::factory()->create();
    Offer::factory()->count(2)->for($profile)->create();

    $user = $profile->user()->with(['providerProfile', 'offers'])->first();

    expect($user->role)->toBe(UserRole::Provider)
        ->and($user->providerProfile->is($profile))->toBeTrue()
        ->and($user->offers)->toHaveCount(2);
});

test('teikėjo kategorijos turi kainą „nuo" pivot lentelėje, zonos – savivaldybės', function () {
    $profile = ProviderProfile::factory()->company()->create();
    $category = Category::factory()->leaf()->create();
    $cities = City::factory()->count(2)->create();

    $profile->categories()->attach($category, ['price_from_cents' => 1500, 'price_unit' => 'm2']);
    $profile->serviceAreas()->attach($cities);

    $profile->load(['categories', 'serviceAreas']);

    expect($profile->type)->toBe(ProviderType::Company)
        ->and($profile->company_code)->toStartWith('999')
        ->and($profile->categories->first()->pivot->price_from_cents)->toBe(1500)
        ->and($profile->serviceAreas)->toHaveCount(2)
        ->and($category->providerProfiles()->count())->toBe(1);
});

test('pokalbio dalyviai – klientas ir teikėjas; neperskaitytos skaičiuojamos pagal last_read_message_id', function () {
    $conversation = Conversation::factory()->create();
    $conversation->load(['offer.serviceRequest', 'offer.providerProfile', 'participants']);
    $client = User::findOrFail($conversation->offer->serviceRequest->client_id);
    $providerUserId = $conversation->offer->providerProfile->user_id;

    $first = Message::factory()->for($conversation)->create(['sender_id' => $providerUserId]);
    Message::factory()->count(2)->for($conversation)->create(['sender_id' => $providerUserId]);

    $conversation->participants()->updateExistingPivot($client->id, ['last_read_message_id' => $first->id]);
    $lastRead = $client->conversations()->whereKey($conversation->id)->firstOrFail()->pivot->last_read_message_id;

    $unread = $conversation->messages()
        ->where('id', '>', $lastRead)
        ->where('sender_id', '!=', $client->id)
        ->count();

    expect($conversation->participants->pluck('id')->sort()->values()->all())
        ->toBe(collect([$client->id, $providerUserId])->sort()->values()->all())
        ->and($unread)->toBe(2);
});

test('patvirtintas atsiliepimas susietas su atlikta užklausa, pakvietimo – ne', function () {
    $verified = Review::factory()->verified()->create()->load('serviceRequest');
    $invited = Review::factory()->create();

    expect($verified->isVerified())->toBeTrue()
        ->and($verified->author_id)->toBe($verified->serviceRequest->client_id)
        ->and($invited->isVerified())->toBeFalse();
});

test('UNIQUE leidžia daug NULL, bet vienai užklausai – tik vieną atsiliepimą', function () {
    Review::factory()->count(3)->create(); // visi su service_request_id = NULL
    $review = Review::factory()->verified()->create();

    expect(fn () => Review::factory()->create(['service_request_id' => $review->service_request_id]))
        ->toThrow(QueryException::class);
});

test('vienas teikėjas vienai užklausai gali pateikti tik vieną pasiūlymą', function () {
    $offer = Offer::factory()->create();

    expect(fn () => Offer::factory()->create([
        'service_request_id' => $offer->service_request_id,
        'provider_profile_id' => $offer->provider_profile_id,
    ]))->toThrow(QueryException::class);
});

test('morph map: polimorfiniuose stulpeliuose saugomi trumpi vardai', function () {
    $offer = Offer::factory()->create();
    $transaction = CreditTransaction::factory()->create(['provider_profile_id' => $offer->provider_profile_id]);
    $transaction->source()->associate($offer)->save();
    $complaint = Complaint::factory()->create();

    expect(DB::table('credit_transactions')->value('source_type'))->toBe('offer')
        ->and(DB::table('complaints')->value('reportable_type'))->toBe('review')
        ->and($complaint->reportable)->toBeInstanceOf(Review::class)
        ->and($offer->creditTransactions()->count())->toBe(1);
});

test('mokėjimas gauna uuid automatiškai, o pirminis raktas lieka skaitinis', function () {
    $payment = Payment::factory()->create();

    expect($payment->id)->toBeInt()
        ->and($payment->uuid)->toBeString()->toHaveLength(36);
});

test('soft deletes: ištrinta užklausa lieka DB, bet nerodoma įprastose užklausose', function () {
    $request = ServiceRequest::factory()->create();
    $request->delete();

    expect(ServiceRequest::query()->count())->toBe(0)
        ->and(ServiceRequest::withTrashed()->count())->toBe(1);
});

test('preventLazyLoading: ryšio užkrovimas cikle (N+1) meta klaidą', function () {
    Offer::factory()->count(2)->create();

    /** @var Collection<int, Offer> $offers */
    $offers = Offer::query()->get();

    expect(fn () => $offers->first()->providerProfile)->toThrow(LazyLoadingViolationException::class)
        ->and(Offer::query()->with('providerProfile')->get()->first()->providerProfile)->toBeInstanceOf(ProviderProfile::class);
});
