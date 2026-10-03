<?php

use App\Actions\Offers\SendOffer;
use App\Actions\Offers\WithdrawOffer;
use App\Enums\CreditTransactionType;
use App\Enums\OfferStatus;
use App\Enums\ServiceRequestStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\CreditTransaction;
use App\Models\Offer;
use App\Models\ProviderProfile;
use App\Notifications\NewOffer;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\Support\Marketplace;

beforeEach(function () {
    Notification::fake();
    $this->send = app(SendOffer::class);
    $this->attributes = [
        'message' => 'Laba diena, galiu atvykti apžiūrėti jau rytoj.',
        'price_cents' => 15000,
        'price_type' => 'fixed',
        'duration_text' => '2 dienos',
        'start_date' => null,
    ];
});

/**
 * Pagalbinė: ValidationException klaidos tekstas pagal lauką.
 */
function offerError(Closure $callback, string $field): string
{
    try {
        $callback();
    } catch (ValidationException $e) {
        return $e->errors()[$field][0] ?? '';
    }

    throw new RuntimeException('Tikėtasi ValidationException');
}

test('pasiūlymas nurašo kategorijos kainą, įrašo ledger ir praneša klientui', function () {
    $request = Marketplace::openRequest(cost: 2);
    $provider = Marketplace::eligibleProvider($request, credits: 5);

    $offer = $this->send->handle($provider, $request, $this->attributes);

    expect($offer->status)->toBe(OfferStatus::Pending)
        ->and($offer->credits_spent)->toBe(2)
        ->and($offer->price_cents)->toBe(15000)
        ->and($provider->credits_balance)->toBe(3)
        ->and($provider->refresh()->credits_balance)->toBe(3)
        ->and($request->refresh()->offers_count)->toBe(1);

    $ledger = CreditTransaction::query()->sole();
    expect($ledger->type)->toBe(CreditTransactionType::Offer)
        ->and($ledger->amount)->toBe(-2)
        ->and($ledger->balance_after)->toBe(3)
        ->and($ledger->source_type)->toBe('offer')
        ->and($ledger->source_id)->toBe($offer->id)
        ->and($ledger->description)->toBe('Pasiūlymas: '.$request->title);

    Notification::assertSentTo($request->client, NewOffer::class, fn (NewOffer $n) => $n->offer->is($offer));
});

test('neužtenkant kreditų pasiūlymas nesukuriamas ir nieko nenurašoma', function () {
    $request = Marketplace::openRequest(cost: 3);
    $provider = Marketplace::eligibleProvider($request, credits: 2);

    $message = offerError(fn () => $this->send->handle($provider, $request, $this->attributes), 'credits');

    expect($message)->toBe('Nepakanka kreditų: pasiūlymas kainuoja 3 kred., o jūs turite 2.')
        ->and(Offer::query()->count())->toBe(0)
        ->and(CreditTransaction::query()->count())->toBe(0)
        ->and($provider->refresh()->credits_balance)->toBe(2);
    Notification::assertNothingSent();
});

test('netinkamam teikėjui pasiūlymo siųsti neleidžiama', function () {
    $request = Marketplace::openRequest();
    $stranger = ProviderProfile::factory()->withCredits(10)->create();

    expect(offerError(fn () => $this->send->handle($stranger, $request, $this->attributes), 'offer'))
        ->toContain('ne jūsų paslaugų srityje');
    expect(Offer::query()->count())->toBe(0);
});

test('antras pasiūlymas tai pačiai užklausai atmetamas', function () {
    $request = Marketplace::openRequest();
    $provider = Marketplace::eligibleProvider($request, credits: 10);
    $this->send->handle($provider, $request, $this->attributes);

    expect(offerError(fn () => $this->send->handle($provider, $request, $this->attributes), 'offer'))
        ->toBe('Šiai užklausai pasiūlymą jau išsiuntėte.')
        ->and($provider->refresh()->credits_balance)->toBe(9)
        ->and($request->refresh()->offers_count)->toBe(1);
});

test('neatvirai užklausai pasiūlymo siųsti negalima', function () {
    $request = Marketplace::openRequest();
    $provider = Marketplace::eligibleProvider($request);
    $request->forceFill(['status' => ServiceRequestStatus::Pending])->save();

    expect(offerError(fn () => $this->send->handle($provider, $request, $this->attributes), 'offer'))
        ->toBe('Ši užklausa nebepriima pasiūlymų.');
});

test('du pasiūlymai iš eilės, kai kreditų užtenka vienam: antras nepavyksta, balansas ne minusinis', function () {
    $first = Marketplace::openRequest();
    $provider = Marketplace::eligibleProvider($first, credits: 1);
    $second = Marketplace::openRequest(attributes: ['city_id' => $first->city_id]);
    $provider->categories()->attach($second->category_id);

    // Antras kvietimas su tuo pačiu (jau pasenusiu) modeliu – kaip lygiagretus užklausimas
    $stale = ProviderProfile::query()->findOrFail($provider->id);

    $this->send->handle($provider, $first, $this->attributes);

    expect(fn () => $this->send->handle($stale, $second, $this->attributes))->toThrow(ValidationException::class);
    expect($provider->refresh()->credits_balance)->toBe(0)
        ->and(Offer::query()->count())->toBe(1)
        ->and((int) CreditTransaction::query()->sum('amount'))->toBe(-1)
        ->and(CreditTransaction::query()->min('balance_after'))->toBe(0);
});

test('teikėjas gali atšaukti pasiūlymą – kreditai negrąžinami, skaitliukas sumažėja', function () {
    $request = Marketplace::openRequest();
    $provider = Marketplace::eligibleProvider($request, credits: 3);
    $offer = $this->send->handle($provider, $request, $this->attributes);

    app(WithdrawOffer::class)->handle($offer);

    expect($offer->refresh()->status)->toBe(OfferStatus::Withdrawn)
        ->and($request->refresh()->offers_count)->toBe(0)
        ->and($provider->refresh()->credits_balance)->toBe(2)
        ->and(CreditTransaction::query()->where('type', CreditTransactionType::Refund)->count())->toBe(0);
});

test('atšaukti galima tik laukiantį pasiūlymą atviroje užklausoje', function () {
    $offer = Offer::factory()->declined()->create();

    expect(fn () => app(WithdrawOffer::class)->handle($offer))->toThrow(InvalidStateTransitionException::class);
});
