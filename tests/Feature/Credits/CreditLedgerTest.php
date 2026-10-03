<?php

use App\Enums\CreditTransactionType;
use App\Exceptions\InsufficientCreditsException;
use App\Models\CreditTransaction;
use App\Models\Offer;
use App\Models\ProviderProfile;
use App\Services\Credits\CreditLedger;

beforeEach(function () {
    $this->ledger = app(CreditLedger::class);
});

test('nurašymas sumažina balansą ir įrašo ledger eilutę su balance_after', function () {
    $provider = ProviderProfile::factory()->withCredits(5)->create();
    $offer = Offer::factory()->for($provider)->create();

    $transaction = $this->ledger->debit($provider, 2, CreditTransactionType::Offer, $offer, 'Pasiūlymas');

    expect($transaction)
        ->amount->toBe(-2)
        ->balance_after->toBe(3)
        ->type->toBe(CreditTransactionType::Offer)
        ->source_type->toBe('offer')
        ->source_id->toBe($offer->id)
        ->and($provider->credits_balance)->toBe(3)
        ->and($provider->refresh()->credits_balance)->toBe(3);
});

test('neužtenkant kreditų meta išimtį ir nieko neįrašo', function () {
    $provider = ProviderProfile::factory()->withCredits(1)->create();

    expect(fn () => $this->ledger->debit($provider, 2, CreditTransactionType::Offer))
        ->toThrow(InsufficientCreditsException::class);

    expect($provider->refresh()->credits_balance)->toBe(1)
        ->and(CreditTransaction::query()->count())->toBe(0);
});

test('balansas skaičiuojamas iš DB, o ne iš pasenusio modelio', function () {
    $provider = ProviderProfile::factory()->withCredits(1)->create();
    $stale = ProviderProfile::query()->findOrFail($provider->id);

    $this->ledger->debit($provider, 1, CreditTransactionType::Offer);

    // $stale vis dar „mato" 1 kreditą, bet ledger'is skaito užrakintą eilutę
    expect(fn () => $this->ledger->debit($stale, 1, CreditTransactionType::Offer))
        ->toThrow(InsufficientCreditsException::class);
    expect($provider->refresh()->credits_balance)->toBe(0);
});

test('pridėjimas padidina balansą', function () {
    $provider = ProviderProfile::factory()->withCredits(2)->create();

    $transaction = $this->ledger->credit($provider, 10, CreditTransactionType::Bonus, null, 'Dovana');

    expect($transaction->amount)->toBe(10)
        ->and($transaction->balance_after)->toBe(12)
        ->and($transaction->source_type)->toBeNull()
        ->and($provider->refresh()->credits_balance)->toBe(12);
});

test('grąžinimas idempotentiškas: antrą kartą nieko negrąžina', function () {
    $provider = ProviderProfile::factory()->withCredits(3)->create();
    $offer = Offer::factory()->for($provider)->create();
    $this->ledger->debit($provider, 3, CreditTransactionType::Offer, $offer);

    $refund = $this->ledger->refund($offer, 'Užklausa atšaukta');

    expect($refund)
        ->type->toBe(CreditTransactionType::Refund)
        ->amount->toBe(3)
        ->balance_after->toBe(3)
        ->and($this->ledger->refund($offer))->toBeNull()
        ->and($provider->refresh()->credits_balance)->toBe(3)
        ->and(CreditTransaction::query()->count())->toBe(2);
});

test('grąžinimas be nurašymo nieko nedaro', function () {
    expect($this->ledger->refund(Offer::factory()->create()))->toBeNull();
});

test('kiekis turi būti teigiamas', function () {
    $provider = ProviderProfile::factory()->withCredits(3)->create();

    expect(fn () => $this->ledger->debit($provider, 0, CreditTransactionType::Offer))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => $this->ledger->credit($provider, -5, CreditTransactionType::Bonus))
        ->toThrow(InvalidArgumentException::class);
});

test('ledger suma visada lygi balansui', function () {
    $provider = ProviderProfile::factory()->create();

    $this->ledger->credit($provider, 5, CreditTransactionType::Bonus);
    $offer = Offer::factory()->for($provider)->create();
    $this->ledger->debit($provider, 2, CreditTransactionType::Offer, $offer);
    $this->ledger->refund($offer);
    $this->ledger->debit($provider, 1, CreditTransactionType::Offer);

    expect((int) $provider->creditTransactions()->sum('amount'))->toBe($provider->refresh()->credits_balance)
        ->and($provider->credits_balance)->toBe(4);
});
