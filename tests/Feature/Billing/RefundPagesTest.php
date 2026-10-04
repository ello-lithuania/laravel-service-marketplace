<?php

use App\Actions\Payments\RefundPayment;
use App\Models\CreditPackage;
use App\Models\Payment;
use App\Models\User;
use App\Services\Privacy\UserDataExporter;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Billing;

/*
 * Teikėjo puslapiai po grąžinimo (Etapas 9b): mokėjimų sąrašas ir mokėjimo puslapis rodo kreditinę sąskaitą,
 * kas ir kiek atimta; administratoriaus vardas teikėjui nesiunčiamas.
 */

beforeEach(function () {
    config(['payments.default' => 'fake']);
    Notification::fake();

    $this->owner = Billing::provider();
    $package = CreditPackage::factory()->create(['name' => '30 kreditų', 'credits' => 30, 'bonus_credits' => 0, 'price_cents' => 2420]);
    $this->payment = Billing::complete(Payment::factory()->fake()->pending()->forPackage($package)->for($this->owner)->create());
    $this->paid = Billing::complete(Payment::factory()->fake()->pending()->forPackage($package)->for($this->owner)->create());

    $this->refund = app(RefundPayment::class)->handle($this->payment, 'Nupirkta per klaidą', User::factory()->admin()->create());
});

test('mokėjimų sąraše – grąžinimas ir kreditinės sąskaitos nuoroda tik grąžintam mokėjimui', function () {
    $this->actingAs($this->owner)
        ->get('/teikejas/mokejimai')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('billing/Payments')
            ->has('payments.data', 2)
            // Naujausias pirmas: apmokėtas, tada grąžintas
            ->where('payments.data.0.uuid', $this->paid->uuid)
            ->where('payments.data.0.refund', null)
            ->where('payments.data.0.can.download_credit_note', false)
            ->where('payments.data.1.status.value', 'refunded')
            ->where('payments.data.1.can.download_invoice', true)
            ->where('payments.data.1.can.download_credit_note', true)
            ->where('payments.data.1.refund.credit_note_number', $this->refund->credit_note_number)
            ->where('payments.data.1.refund.credits_reversed', 30)
            ->missing('payments.data.1.refund.refunded_by_id')
            ->etc());
});

test('BDAR duomenų archyve – grąžinimai (be administratoriaus)', function () {
    $data = app(UserDataExporter::class)->collect($this->owner);

    expect($data['grazinimai'])->toHaveCount(1)
        ->and($data['grazinimai'][0])->toMatchArray([
            'mokejimas' => $this->payment->uuid,
            'amount_cents' => 2420,
            'reason' => 'Nupirkta per klaidą',
            'credit_note_number' => $this->refund->credit_note_number,
        ])
        ->and($data['grazinimai'][0])->not->toHaveKey('refunded_by_id');
});

test('mokėjimo puslapis: grąžinimo data, priežastis, atimti kreditai', function () {
    $this->actingAs($this->owner)
        ->get("/mokejimai/{$this->payment->uuid}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('billing/PaymentShow')
            ->where('payment.status.value', 'refunded')
            ->where('payment.refund.reason', 'Nupirkta per klaidą')
            ->where('payment.refund.credits_reversed', 30)
            ->where('payment.refund.credits_shortfall', 0)
            ->where('payment.can.download_credit_note', true)
            ->etc());
});
