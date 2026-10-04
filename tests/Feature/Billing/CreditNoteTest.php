<?php

use App\Enums\PaymentStatus;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Models\CreditPackage;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Invoices\InvoicePdf;
use Livewire\Livewire;
use Tests\Support\Billing;

/*
 * Kreditinė sąskaita faktūra (Etapas 9b): PDF duomenys, šablonas ir kas gali atsisiųsti.
 * Grąžinimo eiga (kreditai, prenumerata, numeracija) – RefundPaymentTest; čia duomenis paruošia factory.
 */

beforeEach(function () {
    $this->owner = Billing::provider();
    $package = CreditPackage::factory()->create(['name' => '30 kreditų', 'credits' => 30, 'price_cents' => 2420]);
    $this->payment = Payment::factory()->forPackage($package)->for($this->owner)->create([
        'status' => PaymentStatus::Refunded,
        'invoice_number' => 'SF-2026-000123',
        'paid_at' => '2026-03-10 10:00',
    ]);
    $this->refund = Refund::factory()->for($this->payment)->create([
        'amount_cents' => 2420,
        'credit_note_number' => 'KS-2026-000001',
        'reason' => 'Paketas nupirktas per klaidą.',
        'created_at' => '2026-03-12 09:00',
    ]);
});

test('kreditinės sąskaitos duomenys: sumos su minusu, koreguojama sąskaita, priežastis, suma žodžiais', function () {
    $data = app(InvoicePdf::class)->creditNoteData($this->refund);

    expect($data)->toMatchArray([
        'title' => 'Kreditinė sąskaita faktūra',
        'number' => 'KS-2026-000001',
        'date' => '2026-03-12',
        'gross' => '-24,20 €',
        'amount_in_words' => 'Minus dvidešimt keturi eurai 20 ct',
        'credit_note' => [
            'invoice_number' => 'SF-2026-000123',
            'invoice_date' => '2026-03-10',
            'reason' => 'Paketas nupirktas per klaidą.',
        ],
    ])
        ->and($data['line']['description'])->toBe('Grąžinimas: Kreditų paketas „30 kreditų"')
        ->and($data['line']['total'])->toBe('-24,20 €')
        // Rekvizitai – iš grąžinimo snapshot'o, ne iš dabartinio profilio
        ->and($data['buyer']['name'])->toBe('Petras Petraitis');

    $html = view('invoices.invoice', $data)->render();

    expect($html)->toContain('Kreditinė sąskaita faktūra')
        ->toContain('Koreguojama sąskaita faktūra: <strong>SF-2026-000123</strong>, 2026-03-10')
        ->toContain('Iš viso grąžinama')
        ->toContain('Grąžinimo priežastis: Paketas nupirktas per klaidą.')
        ->toContain('Minus dvidešimt keturi eurai 20 ct')
        ->not->toContain('Iš viso mokėti');
});

test('PVM mokėtojo kreditinė sąskaita: PVM išskiriamas taip pat kaip sąskaitoje, tik su minusu', function () {
    $this->refund->update(['billing_details' => [...$this->refund->billing_details, 'vat_payer' => true, 'vat_rate' => 21]]);

    $data = app(InvoicePdf::class)->creditNoteData($this->refund->refresh());

    expect($data)->toMatchArray([
        'title' => 'Kreditinė PVM sąskaita faktūra',
        'net' => '-20 €',
        'vat' => '-4,20 €',
        'gross' => '-24,20 €',
    ]);
});

test('teikėjas atsisiunčia savo kreditinę sąskaitą PDF, administratorius – bet kurią, kiti – ne', function () {
    $url = "/mokejimai/{$this->payment->uuid}/kreditine-saskaita";

    $response = $this->actingAs($this->owner)->get($url)->assertOk();

    expect($response->headers->get('content-type'))->toBe('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain('KS-2026-000001.pdf')
        ->and(substr((string) $response->getContent(), 0, 4))->toBe('%PDF');

    $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();
    $this->actingAs(Billing::provider())->get($url)->assertForbidden();
});

test('kreditinės sąskaitos nėra, kol mokėjimas negrąžintas; grąžinto mokėjimo sąskaita faktūra lieka', function () {
    $paid = Payment::factory()->for($this->owner)->create(['invoice_number' => 'SF-2026-000124']);

    $this->actingAs($this->owner)->get("/mokejimai/{$paid->uuid}/kreditine-saskaita")->assertForbidden();
    $this->actingAs($this->owner)->get("/mokejimai/{$this->payment->uuid}/saskaita")->assertOk();

    expect($this->payment->hasInvoice())->toBeTrue();
});

test('Filament: „Kreditinė sąskaita PDF" matoma tik grąžintam mokėjimui', function () {
    $this->actingAs(User::factory()->admin()->create());
    $paid = Payment::factory()->create(['invoice_number' => 'SF-2026-000125']);

    Livewire::test(ViewPayment::class, ['record' => $this->payment->getRouteKey()])
        ->assertActionVisible('creditNote')
        ->assertActionHasUrl('creditNote', route('payments.credit-note', $this->payment))
        ->assertActionVisible('invoice');

    Livewire::test(ViewPayment::class, ['record' => $paid->getRouteKey()])
        ->assertActionHidden('creditNote');

    $this->get('/admin/mokejimai')->assertOk()->assertSee('KS-2026-000001');
});
