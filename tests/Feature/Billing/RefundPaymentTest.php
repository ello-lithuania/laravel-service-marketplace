<?php

use App\Actions\Payments\RefundPayment;
use App\Enums\CreditTransactionType;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\CreditPackage;
use App\Models\CreditTransaction;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\LowCredits;
use App\Notifications\PaymentRefunded;
use App\Services\Credits\CreditLedger;
use App\Services\Payments\RefundCalculator;
use App\Support\NotificationTarget;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Support\Billing;

/*
 * Mokėjimo grąžinimas (Etapas 9b, taisyklės – docs/DB_SCHEMA.md → refunds): kreditų atėmimas per ledger'į
 * (balansas niekada < 0), prenumeratos sutrumpinimas, kreditinės sąskaitos numeris, idempotencija, pranešimas.
 */

beforeEach(function () {
    config(['payments.default' => 'fake', 'payments.low_credits_threshold' => 3]);
    Notification::fake();
    $this->travelTo(now()->setDate(2026, 3, 10)->setTime(12, 0));

    $this->admin = User::factory()->admin()->create();
    $this->user = Billing::provider();
    $this->provider = $this->user->providerProfile;
    $this->package = CreditPackage::factory()->create(['name' => '30 kreditų', 'credits' => 30, 'bonus_credits' => 0, 'price_cents' => 2420]);
    $this->plan = SubscriptionPlan::factory()->create(['name' => 'Profesionalas', 'price_cents' => 3900, 'credits_per_period' => 60]);
});

function buyPackage(object $test): Payment
{
    return Billing::complete(Payment::factory()->fake()->pending()->forPackage($test->package)->for($test->user)->create());
}

function buyPlanFor(object $test, SubscriptionPlan $plan, ?Subscription $renew = null): Payment
{
    return Billing::complete(Payment::factory()->fake()->pending()->forPlan($plan, $renew)->for($test->user)->create());
}

function refund(object $test, Payment $payment, string $reason = 'Nupirkta per klaidą'): Refund
{
    return app(RefundPayment::class)->handle($payment, $reason, $test->admin);
}

function ledgerSum(int $providerId): int
{
    return (int) CreditTransaction::query()->where('provider_profile_id', $providerId)->sum('amount');
}

test('kreditų paketas: būsena refunded, kreditai atimti ledger eilute, kreditinė sąskaita su KS numeriu', function () {
    $payment = buyPackage($this);
    expect($this->provider->refresh()->credits_balance)->toBe(30);

    $refund = refund($this, $payment);

    $payment->refresh();
    $entry = CreditTransaction::query()->latest('id')->firstOrFail();

    expect($payment->status)->toBe(PaymentStatus::Refunded)
        ->and($payment->hasInvoice())->toBeTrue()
        ->and($refund->credit_note_number)->toBe('KS-2026-000001')
        ->and($refund->amount_cents)->toBe(2420)
        ->and($refund->reason)->toBe('Nupirkta per klaidą')
        ->and($refund->credits_reversed)->toBe(30)
        ->and($refund->credits_shortfall)->toBe(0)
        ->and($refund->refunded_by_id)->toBe($this->admin->id)
        ->and($refund->billing_details)->toBe($payment->billing_details)
        ->and($entry->type)->toBe(CreditTransactionType::PaymentRefund)
        ->and($entry->amount)->toBe(-30)
        ->and($entry->balance_after)->toBe(0)
        ->and($entry->source_type)->toBe('payment')
        ->and($entry->source_id)->toBe($payment->id)
        ->and($entry->description)->toBe("Grąžintas mokėjimas {$payment->invoice_number} (kreditinė sąskaita KS-2026-000001)")
        ->and($this->provider->refresh()->credits_balance)->toBe(0)
        ->and(ledgerSum($this->provider->id))->toBe(0);
});

test('jei kreditai jau išleisti – atimama tik tiek, kiek yra, skirtumas įrašomas (balansas niekada < 0)', function () {
    $payment = buyPackage($this);
    app(CreditLedger::class)->debit($this->provider, 18, CreditTransactionType::Offer);

    $preview = app(RefundCalculator::class)->preview($payment);
    $refund = refund($this, $payment);

    expect($preview->creditsToReverse)->toBe(30)
        ->and($preview->creditsReversed)->toBe(12)
        ->and($preview->creditsShortfall)->toBe(18)
        ->and($refund->credits_reversed)->toBe(12)
        ->and($refund->credits_shortfall)->toBe(18)
        ->and($this->provider->refresh()->credits_balance)->toBe(0)
        ->and(CreditTransaction::query()->where('balance_after', '<', 0)->exists())->toBeFalse()
        ->and(ledgerSum($this->provider->id))->toBe(0);
});

test('balansas 0 – ledger eilutės nėra, visi kreditai – skirtumas, bet pinigai grąžinami', function () {
    $payment = buyPackage($this);
    app(CreditLedger::class)->debit($this->provider, 30, CreditTransactionType::Offer);
    $before = CreditTransaction::query()->count();

    $refund = refund($this, $payment);

    expect($refund->credits_reversed)->toBe(0)
        ->and($refund->credits_shortfall)->toBe(30)
        ->and(CreditTransaction::query()->count())->toBe($before)
        ->and($payment->refresh()->status)->toBe(PaymentStatus::Refunded);
});

test('idempotencija: antras grąžinimas atmetamas – vienas refund, viena ledger eilutė, vienas KS numeris', function () {
    $payment = buyPackage($this);
    refund($this, $payment);

    expect(fn () => refund($this, $payment))->toThrow(InvalidStateTransitionException::class, 'Mokėjimo būsenos „Grąžinta"');

    // Ir su senu (pasenusiu) modelio egzemplioriumi, kuris dar „mano", kad mokėjimas apmokėtas – tikrina užrakinta eilutė
    $stale = Payment::query()->findOrFail($payment->id)->forceFill(['status' => PaymentStatus::Paid]);
    expect(fn () => refund($this, $stale))->toThrow(InvalidStateTransitionException::class);

    expect(Refund::query()->count())->toBe(1)
        ->and(CreditTransaction::query()->where('type', CreditTransactionType::PaymentRefund)->count())->toBe(1)
        ->and(DB::table('invoice_sequences')->where('series', 'credit_note')->value('last_number'))->toBe(1);

    Notification::assertSentToTimes($this->user, PaymentRefunded::class, 1);
});

test('grąžinti galima tik apmokėtą mokėjimą', function (PaymentStatus $status) {
    $payment = Payment::factory()->fake()->forPackage($this->package)->for($this->user)->create(['status' => $status]);

    expect(fn () => refund($this, $payment))->toThrow(InvalidStateTransitionException::class);
    expect(Refund::query()->count())->toBe(0);
})->with([PaymentStatus::Pending, PaymentStatus::Failed, PaymentStatus::Cancelled]);

test('kreditinių sąskaitų numeracija ištisinė ir nepriklauso nuo sąskaitų faktūrų', function () {
    $first = buyPackage($this);
    $second = buyPackage($this);

    expect([refund($this, $first)->credit_note_number, refund($this, $second)->credit_note_number])
        ->toBe(['KS-2026-000001', 'KS-2026-000002'])
        ->and(buyPackage($this)->invoice_number)->toBe('SF-2026-000003');
});

test('pranešimas teikėjui: laiškas su suma, priežastimi, kreditais ir kreditinės sąskaitos nuoroda; LowCredits nesiunčiamas', function () {
    $payment = buyPackage($this);
    app(CreditLedger::class)->debit($this->provider, 10, CreditTransactionType::Offer);

    refund($this, $payment, 'Teikėjas paprašė');

    Notification::assertNotSentTo($this->user, LowCredits::class);
    Notification::assertSentTo($this->user, PaymentRefunded::class, function (PaymentRefunded $notification) use ($payment) {
        $mail = $notification->toMail($this->user);
        $lines = implode(' ', $mail->introLines);

        expect($mail->subject)->toBe('Mokėjimas grąžintas, kreditinė sąskaita KS-2026-000001')
            ->and($lines)->toContain('Grąžinome jūsų mokėjimą 24,20 € už: Kreditų paketas „30 kreditų".')
            ->toContain('Priežastis: Teikėjas paprašė')
            ->toContain('tuo pačiu būdu, kuriuo mokėjote (Testinis')
            ->toContain('Iš kreditų balanso atimta: 20 (dar 10 jau buvote išnaudoję pasiūlymams).')
            ->and($mail->actionUrl)->toBe(route('payments.credit-note', $payment))
            ->and($notification->toArray($this->user))->toMatchArray([
                'payment_uuid' => $payment->uuid,
                'credit_note_number' => 'KS-2026-000001',
                'credits_reversed' => 20,
                'message' => 'Mokėjimas 24,20 € grąžintas – Kreditų paketas „30 kreditų"',
            ])
            ->and($notification->settingsGroup())->toBe('billing');

        return true;
    });
});

test('pranešimas paiso billing nustatymų, varpelis veda į mokėjimo puslapį, „ištrintam" vartotojui nesiunčiamas', function () {
    $this->user->forceFill(['notification_settings' => ['billing' => ['mail' => false, 'database' => true]]])->save();
    $refund = refund($this, buyPackage($this));
    $notification = new PaymentRefunded($refund);

    $bell = new DatabaseNotification(['id' => (string) Str::uuid(), 'type' => PaymentRefunded::class, 'data' => $notification->toArray($this->user)]);

    expect($notification->via($this->user))->toBe(['database'])
        ->and(NotificationTarget::url($bell))->toBe(route('payments.show', $refund->payment));

    $other = Billing::provider();
    $payment = Billing::complete(Payment::factory()->fake()->pending()->forPackage($this->package)->for($other)->create());
    $other->delete();

    refund($this, $payment->refresh());

    Notification::assertNotSentTo($other, PaymentRefunded::class);
});

// --- Prenumeratos: „vienas mokėjimas = vienas laikotarpis" ---

test('ką tik nupirktas planas: prenumerata baigiama iškart, kreditai už laikotarpį atimami, pratęsimai atšaukiami', function () {
    $payment = buyPlanFor($this, $this->plan);
    $subscription = Subscription::query()->sole();
    $renewal = Payment::factory()->fake()->pending()->forPlan($this->plan, $subscription)->for($this->user)->create();
    expect($this->provider->refresh()->credits_balance)->toBe(60);

    $this->travelTo(now()->addDays(3));
    $refund = refund($this, $payment);
    $subscription->refresh();

    expect($subscription->status)->toBe(SubscriptionStatus::Expired)
        ->and($subscription->auto_renew)->toBeFalse()
        ->and($subscription->ends_at->toDateTimeString())->toBe('2026-03-13 12:00:00')
        ->and($subscription->credits_granted_until->toDateTimeString())->toBe('2026-03-13 12:00:00')
        ->and($subscription->cancelled_at)->not->toBeNull()
        ->and($refund->credits_reversed)->toBe(60)
        ->and($this->provider->refresh()->credits_balance)->toBe(0)
        ->and($renewal->refresh()->status)->toBe(PaymentStatus::Cancelled);

    // Scheduler nebesuteikia kreditų už grąžintą laikotarpį
    $this->artisan('subscriptions:grant-credits')->assertSuccessful();
    expect($this->provider->refresh()->credits_balance)->toBe(0);

    Notification::assertSentTo($this->user, PaymentRefunded::class, fn (PaymentRefunded $n) => str_contains(
        implode(' ', $n->toMail($this->user)->introLines),
        'Prenumerata „Profesionalas" baigta.',
    ));
});

test('iš anksto apmokėtas pratęsimas: grąžinamas tik jis – prenumerata galioja iki senos pabaigos ir nebepratęsiama', function () {
    $first = buyPlanFor($this, $this->plan);
    $subscription = Subscription::query()->sole();

    $this->travelTo(now()->setDate(2026, 4, 5));
    $renewal = buyPlanFor($this, $this->plan, $subscription);
    expect($subscription->refresh()->ends_at->toDateString())->toBe('2026-05-10')
        ->and($this->provider->refresh()->credits_balance)->toBe(60);

    $refund = refund($this, $renewal);
    $subscription->refresh();

    expect($subscription->status)->toBe(SubscriptionStatus::Cancelled)
        ->and($subscription->auto_renew)->toBeFalse()
        ->and($subscription->ends_at->toDateTimeString())->toBe('2026-04-10 12:00:00')
        ->and($refund->credits_reversed)->toBe(0)
        ->and($refund->credits_shortfall)->toBe(0)
        ->and($this->provider->refresh()->credits_balance)->toBe(60);

    Notification::assertSentTo($this->user, PaymentRefunded::class, fn (PaymentRefunded $n) => str_contains(
        implode(' ', $n->toMail($this->user)->introLines),
        'Prenumerata „Profesionalas" galios iki 2026-04-10 ir nebebus pratęsiama.',
    ));

    // Grąžinus ir pirmą mokėjimą – prenumerata baigiasi dabar, atimami einamojo laikotarpio kreditai
    refund($this, $first);

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Expired)
        ->and($subscription->ends_at->toDateTimeString())->toBe('2026-04-05 12:00:00')
        ->and($this->provider->refresh()->credits_balance)->toBe(0)
        ->and(ledgerSum($this->provider->id))->toBe(0);
});

test('suplanuotas naujas planas (plano keitimas): niekada neprasidės, dabartinė prenumerata nepaliečiama', function () {
    $current = Subscription::factory()->for($this->provider)->create(['starts_at' => now()->subDays(10), 'ends_at' => now()->addDays(20)]);
    $other = SubscriptionPlan::factory()->create(['name' => 'Startas', 'credits_per_period' => 20]);

    $payment = buyPlanFor($this, $other);
    $scheduled = Subscription::query()->whereKeyNot($current->id)->sole();
    expect($scheduled->isScheduled())->toBeTrue();

    $refund = refund($this, $payment);
    $scheduled->refresh();

    expect($scheduled->status)->toBe(SubscriptionStatus::Expired)
        ->and($scheduled->ends_at->toDateTimeString())->toBe($scheduled->starts_at->toDateTimeString())
        ->and($refund->credits_reversed)->toBe(0)
        ->and($current->refresh()->status)->toBe(SubscriptionStatus::Cancelled);
});
