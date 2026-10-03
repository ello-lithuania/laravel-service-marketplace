<?php

use App\Enums\CreditTransactionType;
use App\Models\CreditPackage;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Notifications\LowCredits;
use App\Notifications\PaymentSucceeded;
use App\Notifications\SubscriptionExpiring;
use App\Services\Credits\CreditLedger;
use App\Support\NotificationTarget;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Support\Billing;
use Tests\Support\Marketplace;

beforeEach(function () {
    config(['payments.default' => 'fake', 'payments.low_credits_threshold' => 3]);
});

test('LowCredits – kai po pasiūlymo balansas pereina ribą (3 → 2), bet ne po kiekvieno pasiūlymo', function () {
    Notification::fake();
    $request = Marketplace::openRequest(cost: 1);
    $provider = Marketplace::eligibleProvider($request, credits: 3);
    $second = Marketplace::openRequest(cost: 1, attributes: ['city_id' => $request->city_id, 'category_id' => $request->category_id]);

    $this->actingAs($provider->user)
        ->post("/uzklausos/{$request->slug}/pasiulymai", ['message' => str_repeat('Atliksiu darbą kokybiškai. ', 3), 'price_type' => 'after_inspection'])
        ->assertRedirect();

    Notification::assertSentTo($provider->user, LowCredits::class, fn (LowCredits $n) => $n->balance === 2);

    // 2 → 1: riba jau buvo peržengta – antro pranešimo nėra
    $this->actingAs($provider->user)
        ->post("/uzklausos/{$second->slug}/pasiulymai", ['message' => str_repeat('Atliksiu darbą kokybiškai. ', 3), 'price_type' => 'after_inspection'])
        ->assertRedirect();

    Notification::assertSentToTimes($provider->user, LowCredits::class, 1);
    expect($provider->refresh()->credits_balance)->toBe(1);
});

test('LowCredits nesiunčiamas, kai balansas lieka virš ribos arba kreditai pridedami', function () {
    Notification::fake();
    $provider = Billing::provider(credits: 10)->providerProfile;
    $ledger = app(CreditLedger::class);

    $ledger->debit($provider, 7, CreditTransactionType::Offer);
    $ledger->credit($provider, 1, CreditTransactionType::Refund);

    Notification::assertNotSentTo($provider->user, LowCredits::class);
});

test('LowCredits nesiunčiamas, jei transakcija atšaukiama (po COMMIT, ne anksčiau)', function () {
    Notification::fake();
    $provider = Billing::provider(credits: 3)->providerProfile;

    try {
        DB::transaction(function () use ($provider) {
            app(CreditLedger::class)->debit($provider, 2, CreditTransactionType::Offer);

            throw new RuntimeException('Atšaukiam');
        });
    } catch (RuntimeException) {
        // tyčia
    }

    Notification::assertNotSentTo($provider->user, LowCredits::class);
});

test('billing pranešimai paiso nustatymų: išjungus el. laiškus lieka tik varpelis', function () {
    $user = Billing::provider();
    $user->forceFill(['notification_settings' => ['billing' => ['mail' => false, 'database' => true]]])->save();

    expect((new LowCredits(2))->via($user))->toBe(['database'])
        ->and((new LowCredits(2))->settingsGroup())->toBe('billing');
});

test('PaymentSucceeded – laiškas su suma, pirkiniu ir sąskaitos nuoroda', function () {
    Notification::fake();
    $user = Billing::provider();
    $package = CreditPackage::factory()->create(['name' => '30 kreditų', 'price_cents' => 2690]);
    $payment = Billing::complete(Payment::factory()->fake()->pending()->forPackage($package)->for($user)->create());
    $notification = new PaymentSucceeded($payment);
    $mail = $notification->toMail($user);

    expect($mail->subject)->toBe("Mokėjimas gautas, sąskaita {$payment->invoice_number}")
        ->and(implode(' ', $mail->introLines))->toContain('Gavome jūsų mokėjimą 26,90 € už: Kreditų paketas „30 kreditų".')
        ->and($mail->actionUrl)->toBe(route('payments.invoice', $payment))
        ->and($notification->toArray($user))->toBe([
            'payment_id' => $payment->id,
            'payment_uuid' => $payment->uuid,
            'message' => 'Mokėjimas 26,90 € gautas – Kreditų paketas „30 kreditų"',
        ]);
});

test('SubscriptionExpiring – priminimas ir „nesumokėta" su apmokėjimo nuoroda', function () {
    $user = Billing::provider();
    $plan = SubscriptionPlan::factory()->create(['name' => 'Startas', 'price_cents' => 1900]);
    $subscription = Subscription::factory()->for($user->providerProfile)->create(['subscription_plan_id' => $plan->id, 'ends_at' => '2026-11-01 10:00']);
    $payment = Payment::factory()->fake()->pending()->forPlan($plan, $subscription)->for($user)->create();

    $expiring = (new SubscriptionExpiring($subscription, $payment))->toMail($user);
    $pastDue = new SubscriptionExpiring($subscription, $payment, pastDue: true);

    expect($expiring->subject)->toBe('Prenumerata „Startas" baigiasi 2026-11-01')
        ->and($expiring->actionUrl)->toBe(route('payments.show', $payment))
        ->and(implode(' ', $expiring->introLines))->toContain('Pratęsimo kaina: 19 €.')
        ->and($pastDue->toMail($user)->subject)->toBe('Prenumerata „Startas" nesumokėta')
        ->and($pastDue->toArray($user)['message'])->toBe('Prenumerata „Startas" nesumokėta – apmokėkite iki 2026-11-04');
});

test('varpelio nuorodos: mokėjimas → mokėjimo puslapis, mažai kreditų → kreditai', function () {
    $payment = Payment::factory()->create();
    $make = fn (string $type, array $data) => new DatabaseNotification(['id' => (string) Str::uuid(), 'type' => 'App\\Notifications\\'.$type, 'data' => $data]);

    expect(NotificationTarget::url($make('PaymentSucceeded', ['payment_uuid' => $payment->uuid])))->toBe(route('payments.show', $payment))
        ->and(NotificationTarget::url($make('SubscriptionExpiring', ['payment_uuid' => (string) Str::uuid()])))->toBe(route('credits.index'))
        ->and(NotificationTarget::url($make('LowCredits', ['balance' => 2])))->toBe(route('credits.index'));
});
