<?php

use App\Enums\CreditTransactionType;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\CreditTransaction;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Notifications\SubscriptionExpiring;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Billing;

/*
 * Prenumeratos gyvavimo ciklas (docs/DB_SCHEMA.md → subscriptions): pirkimas, kreditai kas laikotarpį,
 * pratęsimas, malonės laikotarpis, pasibaigimas, atšaukimas, plano keitimas.
 * Laikas „sukamas" travelTo() – Scheduler komandos paleidžiamos taip, lyg būtų praėjusios dienos.
 */

beforeEach(function () {
    config(['payments.default' => 'fake', 'payments.subscriptions.renewal_notice_days' => 7, 'payments.subscriptions.grace_days' => 3]);
    Notification::fake();
    $this->travelTo(now()->setDate(2026, 3, 10)->setTime(12, 0));

    $this->user = Billing::provider();
    $this->provider = $this->user->providerProfile;
    $this->plan = SubscriptionPlan::factory()->create(['name' => 'Profesionalas', 'slug' => 'profesionalas', 'price_cents' => 3900, 'credits_per_period' => 60]);
});

/**
 * Teikėjas nuperka planą per HTTP ir testinis tiekėjas jį apmoka.
 */
function buyPlan(object $test, SubscriptionPlan $plan): Payment
{
    $test->actingAs($test->user)->post("/teikejas/prenumerata/{$plan->slug}/pirkti")->assertStatus(302);

    return Billing::complete(Payment::query()->latest('id')->firstOrFail());
}

function subscriptionCredits(): int
{
    return (int) CreditTransaction::query()->where('type', CreditTransactionType::Subscription)->sum('amount');
}

test('apmokėtas planas: aktyvi prenumerata vienam mėnesiui ir kreditai už pirmą laikotarpį iškart', function () {
    $payment = buyPlan($this, $this->plan);
    $subscription = Subscription::query()->sole();

    expect($subscription)
        ->status->toBe(SubscriptionStatus::Active)
        ->auto_renew->toBeTrue()
        ->provider_profile_id->toBe($this->provider->id)
        ->and($subscription->starts_at->toDateTimeString())->toBe('2026-03-10 12:00:00')
        ->and($subscription->ends_at->toDateTimeString())->toBe('2026-04-10 12:00:00')
        ->and($subscription->credits_granted_until->toDateTimeString())->toBe('2026-04-10 12:00:00')
        ->and($payment->subscription_id)->toBe($subscription->id)
        ->and($payment->invoice_number)->not->toBeNull()
        ->and($this->provider->refresh()->credits_balance)->toBe(60);

    $transaction = CreditTransaction::query()->sole();
    expect($transaction->source_type)->toBe('subscription')
        ->and($transaction->source_id)->toBe($subscription->id)
        ->and($transaction->description)->toBe('Prenumerata „Profesionalas" (2026-03-10 – 2026-04-10)');
});

test('kreditai už tą patį laikotarpį dukart nesuteikiami, kiek kartų bepaleistum Scheduler', function () {
    buyPlan($this, $this->plan);

    $this->artisan('subscriptions:grant-credits')->assertSuccessful();
    $this->artisan('subscriptions:grant-credits')->assertSuccessful();
    $this->travelTo(now()->addDays(20));
    $this->artisan('subscriptions:grant-credits')->assertSuccessful();

    expect(subscriptionCredits())->toBe(60)
        ->and($this->provider->refresh()->credits_balance)->toBe(60);
});

test('pratęsimas: priminimas prieš 7 d., apmokėjus iš anksto – kreditai tik prasidėjus naujam laikotarpiui', function () {
    buyPlan($this, $this->plan);
    $subscription = Subscription::query()->sole();

    // 8 dienos iki pabaigos – dar per anksti
    $this->travelTo('2026-04-02 08:00');
    $this->artisan('subscriptions:renew')->assertSuccessful();
    expect(Payment::query()->where('status', PaymentStatus::Pending)->count())->toBe(0);

    // 6 dienos iki pabaigos – pratęsimo mokėjimas ir priminimas su nuoroda
    $this->travelTo('2026-04-04 08:00');
    $this->artisan('subscriptions:renew')->assertSuccessful();
    $this->artisan('subscriptions:renew')->assertSuccessful();

    $renewal = Payment::query()->where('status', PaymentStatus::Pending)->sole();
    expect($renewal->subscription_id)->toBe($subscription->id)
        ->and($renewal->amount_cents)->toBe(3900);
    Notification::assertSentToTimes($this->user, SubscriptionExpiring::class, 1);
    Notification::assertSentTo($this->user, SubscriptionExpiring::class, fn (SubscriptionExpiring $n) => ! $n->pastDue && $n->payment->is($renewal));

    // Apmokėta iš anksto: ends_at + 1 mėn., bet kreditų dar nėra (laikotarpis neprasidėjo)
    Billing::complete($renewal);
    $subscription->refresh();
    expect($subscription->ends_at->toDateTimeString())->toBe('2026-05-10 12:00:00')
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and(subscriptionCredits())->toBe(60);

    // Naujas laikotarpis prasidėjo – Scheduler suteikia kreditus vieną kartą
    $this->travelTo('2026-04-10 13:00');
    $this->artisan('subscriptions:grant-credits')->assertSuccessful();
    $this->artisan('subscriptions:grant-credits')->assertSuccessful();
    $this->artisan('subscriptions:renew')->assertSuccessful();

    expect(subscriptionCredits())->toBe(120)
        ->and($subscription->refresh()->credits_granted_until->toDateTimeString())->toBe('2026-05-10 12:00:00')
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($this->provider->refresh()->credits_balance)->toBe(120);
});

test('neapmokėta: past_due su priminimu, po malonės laikotarpio – expired, pratęsimo mokėjimas atšaukiamas', function () {
    buyPlan($this, $this->plan);
    $subscription = Subscription::query()->sole();

    $this->travelTo('2026-04-04 08:00');
    $this->artisan('subscriptions:renew');
    $renewal = Payment::query()->where('status', PaymentStatus::Pending)->sole();

    // Laikotarpis baigėsi – past_due ir „nesumokėta" priminimas tam pačiam mokėjimui
    $this->travelTo('2026-04-11 08:00');
    $this->artisan('subscriptions:renew');

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::PastDue)
        ->and(Payment::query()->where('status', PaymentStatus::Pending)->count())->toBe(1);
    Notification::assertSentTo($this->user, SubscriptionExpiring::class, fn (SubscriptionExpiring $n) => $n->pastDue && $n->payment->is($renewal));

    // Malonės laikotarpis (3 d.) dar nesibaigė – būsena ta pati
    $this->travelTo('2026-04-13 08:00');
    $this->artisan('subscriptions:renew');
    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::PastDue);

    $this->travelTo('2026-04-14 08:00');
    $this->artisan('subscriptions:renew');

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Expired)
        ->and($renewal->refresh()->status)->toBe(PaymentStatus::Cancelled)
        ->and(subscriptionCredits())->toBe(60);
});

test('apmokėjus per malonės laikotarpį – vėl aktyvi, laikotarpis nuo senos pabaigos, kreditai iškart', function () {
    buyPlan($this, $this->plan);
    $subscription = Subscription::query()->sole();

    $this->travelTo('2026-04-04 08:00');
    $this->artisan('subscriptions:renew');
    $this->travelTo('2026-04-11 08:00');
    $this->artisan('subscriptions:renew');
    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::PastDue);

    Billing::complete(Payment::query()->where('status', PaymentStatus::Pending)->sole());

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->ends_at->toDateTimeString())->toBe('2026-05-10 12:00:00')
        ->and(subscriptionCredits())->toBe(120);
});

test('teikėjas atšaukia: galioja iki pabaigos, nepratęsiama, tada expired', function () {
    buyPlan($this, $this->plan);
    $subscription = Subscription::query()->sole();

    $this->travelTo('2026-04-04 08:00');
    $this->artisan('subscriptions:renew');
    $renewal = Payment::query()->where('status', PaymentStatus::Pending)->sole();

    $this->actingAs($this->user)
        ->post("/teikejas/prenumerata/{$subscription->id}/atsaukti")
        ->assertRedirect(route('credits.index'))
        ->assertInertiaFlash('toast.message', 'Prenumerata atšaukta. Ji galioja iki 2026-04-10, vėliau nebus pratęsiama.');

    expect($subscription->refresh())
        ->status->toBe(SubscriptionStatus::Cancelled)
        ->auto_renew->toBeFalse()
        ->cancelled_at->not->toBeNull()
        ->and($renewal->refresh()->status)->toBe(PaymentStatus::Cancelled);

    // Antrą kartą atšaukti nebėra ko (Policy)
    $this->actingAs($this->user)->post("/teikejas/prenumerata/{$subscription->id}/atsaukti")->assertForbidden();

    $this->travelTo('2026-04-11 08:00');
    $this->artisan('subscriptions:renew');

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Expired)
        ->and(Payment::query()->where('status', PaymentStatus::Pending)->count())->toBe(0);
});

test('svetimos prenumeratos atšaukti negalima', function () {
    $subscription = Subscription::factory()->create();

    $this->actingAs($this->user)->post("/teikejas/prenumerata/{$subscription->id}/atsaukti")->assertForbidden();

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Active);
});

test('plano keitimas: naujas planas prasideda pasibaigus dabartiniam, kreditai – tik jam prasidėjus', function () {
    buyPlan($this, $this->plan);
    $current = Subscription::query()->sole();
    $business = SubscriptionPlan::factory()->create(['slug' => 'verslas', 'name' => 'Verslas', 'credits_per_period' => 140, 'price_cents' => 7900]);

    $this->travelTo('2026-03-20 10:00');
    buyPlan($this, $business);

    $next = Subscription::query()->whereKeyNot($current->id)->sole();
    expect($current->refresh()->status)->toBe(SubscriptionStatus::Cancelled)
        ->and($current->auto_renew)->toBeFalse()
        ->and($next->status)->toBe(SubscriptionStatus::Active)
        ->and($next->isScheduled())->toBeTrue()
        ->and($next->starts_at->toDateTimeString())->toBe('2026-04-10 12:00:00')
        ->and($next->ends_at->toDateTimeString())->toBe('2026-05-10 12:00:00')
        ->and(subscriptionCredits())->toBe(60);

    // Trečio plano, kol vienas laukia eilės, pirkti negalima
    $this->actingAs($this->user)
        ->from('/kainos')
        ->post("/teikejas/prenumerata/{$this->plan->slug}/pirkti")
        ->assertSessionHasErrors(['purchase' => 'Jau turite suplanuotą planą „Verslas", kuris prasidės 2026-04-10. Kitą planą galėsite pasirinkti po to.']);

    $this->travelTo('2026-04-10 13:00');
    $this->artisan('subscriptions:renew');
    $this->artisan('subscriptions:grant-credits');

    expect($current->refresh()->status)->toBe(SubscriptionStatus::Expired)
        ->and(subscriptionCredits())->toBe(60 + 140)
        // Vienu metu galioja tik viena prenumerata
        ->and(Subscription::query()->get()->filter(fn (Subscription $s) => $s->isCurrent())->count())->toBe(1);
});

test('to paties automatiškai pratęsiamo plano antrą kartą pirkti negalima', function () {
    buyPlan($this, $this->plan);

    $this->actingAs($this->user)
        ->from('/kainos')
        ->post("/teikejas/prenumerata/{$this->plan->slug}/pirkti")
        ->assertRedirect('/kainos')
        ->assertSessionHasErrors(['purchase' => 'Šį planą jau turite – jis pratęsiamas automatiškai.']);

    expect(Payment::query()->count())->toBe(1);
});

test('naujas planas, kai senoji prenumerata nesumokėta (past_due): senoji baigiama, nauja prasideda iškart', function () {
    $old = Subscription::factory()->pastDue()->for($this->provider)->create(['subscription_plan_id' => $this->plan->id]);
    $renewal = Payment::factory()->fake()->pending()->forPlan($this->plan, $old)->for($this->user)->create();
    $other = SubscriptionPlan::factory()->create(['credits_per_period' => 25]);

    buyPlan($this, $other);

    $new = Subscription::query()->whereKeyNot($old->id)->sole();
    expect($old->refresh()->status)->toBe(SubscriptionStatus::Expired)
        ->and($renewal->refresh()->status)->toBe(PaymentStatus::Cancelled)
        ->and($new->starts_at->toDateTimeString())->toBe('2026-03-10 12:00:00')
        ->and($this->provider->refresh()->credits_balance)->toBe(25);
});

test('pasibaigusios prenumeratos pratęsimo mokėjimas laikomas nauju plano pirkimu', function () {
    $old = Subscription::factory()->expired()->for($this->provider)->create(['subscription_plan_id' => $this->plan->id]);
    $payment = Payment::factory()->fake()->pending()->forPlan($this->plan, $old)->for($this->user)->create();

    Billing::complete($payment);

    $new = Subscription::query()->whereKeyNot($old->id)->sole();
    expect($old->refresh()->status)->toBe(SubscriptionStatus::Expired)
        ->and($new->status)->toBe(SubscriptionStatus::Active)
        ->and($payment->refresh()->subscription_id)->toBe($new->id)
        ->and($this->provider->refresh()->credits_balance)->toBe(60);
});

test('metinis planas – laikotarpis metai', function () {
    $yearly = SubscriptionPlan::factory()->create(['billing_period' => 'year', 'credits_per_period' => 700]);

    buyPlan($this, $yearly);

    expect(Subscription::query()->sole()->ends_at->toDateTimeString())->toBe('2027-03-10 12:00:00')
        ->and($this->provider->refresh()->credits_balance)->toBe(700);
});

test('seed\'ų prenumeratoms Scheduler kreditų antrą kartą nesuteikia', function () {
    // Kaip MonetizationGenerator: kreditai už visus laikotarpius jau įrašyti, credits_granted_until = ends_at
    Subscription::factory()->count(3)->create();

    $this->artisan('subscriptions:grant-credits')->expectsOutputToContain('Suteikta kreditų už laikotarpių: 0')->assertSuccessful();

    expect(CreditTransaction::query()->count())->toBe(0);
});
