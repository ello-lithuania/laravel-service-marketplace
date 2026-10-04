<?php

use App\Actions\Payments\RefundPayment;
use App\Enums\CreditTransactionType;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Models\CreditPackage;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Notifications\PaymentRefunded;
use App\Services\Credits\CreditLedger;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Support\Billing;

/*
 * Filament: „Grąžinti pinigus" (Etapas 9b). pest-plugin-livewire neįdiegtas, todėl – Livewire::test(...).
 * Pačią grąžinimo logiką tikrina RefundPaymentTest; čia – forma, peržiūra, matomumas, teisės.
 */

beforeEach(function () {
    config(['payments.default' => 'fake']);
    Notification::fake();
    $this->travelTo(now()->setDate(2026, 3, 10)->setTime(12, 0));

    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);

    $this->owner = Billing::provider();
    $package = CreditPackage::factory()->create(['name' => '30 kreditų', 'credits' => 30, 'bonus_credits' => 0, 'price_cents' => 2420]);
    $this->payment = Billing::complete(Payment::factory()->fake()->pending()->forPackage($package)->for($this->owner)->create());
});

test('peržiūros puslapyje: priežastis + varnelė → grąžinta, KS numeris, kreditai atimti, teikėjui pranešta', function () {
    Livewire::test(ViewPayment::class, ['record' => $this->payment->getRouteKey()])
        ->assertActionVisible('refund')
        ->assertActionHidden('creditNote')
        ->callAction('refund', ['reason' => 'Teikėjas paprašė per 14 d.', 'money_returned' => true])
        ->assertHasNoActionErrors()
        ->assertNotified('Mokėjimas grąžintas. Kreditinė sąskaita KS-2026-000001')
        ->assertActionHidden('refund')
        ->assertActionVisible('creditNote')
        ->assertSee('Grąžinimas')
        ->assertSee('Teikėjas paprašė per 14 d.');

    $refund = Refund::query()->sole();

    expect($this->payment->refresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($refund->refunded_by_id)->toBe($this->admin->id)
        ->and($refund->credits_reversed)->toBe(30)
        ->and($this->owner->providerProfile->refresh()->credits_balance)->toBe(0);

    Notification::assertSentTo($this->owner, PaymentRefunded::class);
});

test('patvirtinimo lange – pasekmės iš anksto: kiek kreditų bus atimta ir kiek jau išleista', function () {
    app(CreditLedger::class)->debit($this->owner->providerProfile, 18, CreditTransactionType::Offer);

    Livewire::test(ViewPayment::class, ['record' => $this->payment->getRouteKey()])
        ->mountAction('refund')
        ->assertMountedActionModalSee('kreditinė sąskaita -24,20 €')
        ->assertMountedActionModalSee('bus atimta tik 12, 18 atimti nepavyks')
        ->assertMountedActionModalSee('mokėjimų tiekėjo savitarnoje');
});

test('be priežasties ar nepažymėjus varnelės negrąžinama', function () {
    Livewire::test(ViewPayment::class, ['record' => $this->payment->getRouteKey()])
        ->callAction('refund', ['reason' => '', 'money_returned' => false])
        ->assertHasActionErrors(['reason' => 'required', 'money_returned' => 'accepted']);

    expect($this->payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and(Refund::query()->count())->toBe(0);
});

test('sąraše: veiksmas tik apmokėtiems, po grąžinimo – KS numeris ir kreditinės sąskaitos nuoroda', function () {
    $pending = Payment::factory()->fake()->pending()->create();

    Livewire::test(ListPayments::class)
        ->assertActionHidden(TestAction::make('refund')->table($pending))
        ->callAction(TestAction::make('refund')->table($this->payment), ['reason' => 'Dvigubas apmokėjimas', 'money_returned' => true])
        ->assertNotified('Mokėjimas grąžintas. Kreditinė sąskaita KS-2026-000001')
        ->assertActionHidden(TestAction::make('refund')->table($this->payment))
        ->assertActionVisible(TestAction::make('creditNote')->table($this->payment))
        ->assertSee('KS-2026-000001');
});

test('jei kas nors ką tik grąžino tą patį mokėjimą – antro grąžinimo nebus', function () {
    $page = Livewire::test(ViewPayment::class, ['record' => $this->payment->getRouteKey()])->mountAction('refund');

    // Kitas administratorius kitame skirtuke spėjo pirmas. Livewire kitai užklausai įrašą perskaito iš DB iš naujo,
    // todėl Filament jau mato „refunded" (visible/authorize – false) ir veiksmo nevykdo. Jei vis dėlto įvykdytų,
    // RefundPayment užrakintoje eilutėje pamatytų „refunded" ir mestų InvalidStateTransitionException.
    app(RefundPayment::class)->handle(Payment::query()->findOrFail($this->payment->id), 'Pirmas', $this->admin);

    $page->setActionData(['reason' => 'Antras', 'money_returned' => true])
        ->callMountedAction();

    Livewire::test(ViewPayment::class, ['record' => $this->payment->getRouteKey()])->assertActionHidden('refund');

    expect(Refund::query()->count())->toBe(1)
        ->and(Refund::query()->sole()->reason)->toBe('Pirmas');
});

test('Policy: grąžinti gali tik administratorius ir tik apmokėtą; teikėjas – ne', function () {
    $refunded = Payment::factory()->create(['status' => PaymentStatus::Refunded]);

    expect($this->admin->can('refund', $this->payment))->toBeTrue()
        ->and($this->admin->can('refund', $refunded))->toBeFalse()
        ->and($this->owner->can('refund', $this->payment))->toBeFalse();
});
