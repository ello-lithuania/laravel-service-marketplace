<?php

use App\Enums\InvoiceSeries;
use App\Enums\PaymentStatus;
use App\Enums\ProviderType;
use App\Models\CreditPackage;
use App\Models\Payment;
use App\Models\ProviderProfile;
use App\Models\Refund;
use App\Models\User;
use App\Services\Invoices\InvoiceNumberGenerator;
use App\Services\Invoices\InvoicePdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Billing;

beforeEach(function () {
    config(['payments.default' => 'fake']);
    Notification::fake();
    $this->package = CreditPackage::factory()->create(['name' => '30 kreditų', 'credits' => 30, 'price_cents' => 2420]);
});

test('numeriai eina iš eilės metų viduje ir tęsia nuo didžiausio jau esančio (seed\'ų) numerio', function () {
    // Seed'ų formatas: SF-{metai}-{mokėjimo ID}
    Payment::factory()->create(['invoice_number' => 'SF-2026-001234', 'paid_at' => '2026-02-01 10:00']);
    Payment::factory()->create(['invoice_number' => 'SF-2025-009999', 'paid_at' => '2025-12-01 10:00']);
    $generator = app(InvoiceNumberGenerator::class);

    $numbers = DB::transaction(fn () => [
        $generator->next(CarbonImmutable::parse('2026-03-10 12:00')),
        $generator->next(CarbonImmutable::parse('2026-03-10 12:01')),
        $generator->next(CarbonImmutable::parse('2027-01-02 12:00')),
        $generator->next(CarbonImmutable::parse('2026-12-31 12:00')),
    ]);

    expect($numbers)->toBe(['SF-2026-001235', 'SF-2026-001236', 'SF-2027-000001', 'SF-2026-001237']);
});

test('metai – pagal Lietuvos laiką: gruodžio 31 d. 23:30 UTC jau sausio 1-oji', function () {
    $number = DB::transaction(fn () => app(InvoiceNumberGenerator::class)->next(CarbonImmutable::parse('2026-12-31 23:30', 'UTC')));

    expect($number)->toBe('SF-2027-000001');
});

test('apmokėtų mokėjimų sąskaitų numeriai unikalūs ir be tarpų, neapmokėti numerio negauna', function () {
    $user = Billing::provider();
    $payments = Payment::factory()->fake()->pending()->forPackage($this->package)->for($user)->count(4)->create();

    Billing::complete($payments[0]);
    Billing::complete($payments[1], PaymentStatus::Failed);
    Billing::complete($payments[2]);
    Billing::complete($payments[3]);

    $year = now('Europe/Vilnius')->year;
    $numbers = Payment::query()->whereNotNull('invoice_number')->orderBy('id')->pluck('invoice_number')->all();

    expect($numbers)->toBe([
        sprintf('SF-%d-000001', $year),
        sprintf('SF-%d-000002', $year),
        sprintf('SF-%d-000003', $year),
    ])->and($payments[1]->refresh()->invoice_number)->toBeNull();
});

test('teikėjas atsisiunčia savo sąskaitą PDF, administratorius – bet kurią, kiti – ne', function () {
    $owner = Billing::provider();
    $payment = Billing::complete(Payment::factory()->fake()->pending()->forPackage($this->package)->for($owner)->create());

    $response = $this->actingAs($owner)->get("/mokejimai/{$payment->uuid}/saskaita")->assertOk();

    expect($response->headers->get('content-type'))->toBe('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain($payment->invoice_number.'.pdf')
        ->and(substr((string) $response->getContent(), 0, 4))->toBe('%PDF');

    $this->actingAs(User::factory()->admin()->create())->get("/mokejimai/{$payment->uuid}/saskaita")->assertOk();
    $this->actingAs(Billing::provider())->get("/mokejimai/{$payment->uuid}/saskaita")->assertForbidden();
});

test('neapmokėtam mokėjimui sąskaitos nėra', function () {
    $owner = Billing::provider();
    $payment = Payment::factory()->fake()->pending()->forPackage($this->package)->for($owner)->create();

    $this->actingAs($owner)->get("/mokejimai/{$payment->uuid}/saskaita")->assertForbidden();
});

test('PVM mokėtojo sąskaita: iš kainos su PVM išskiriamas PVM, pirkėjas – įmonė su kodais', function () {
    config(['invoices.vat_payer' => true, 'invoices.vat_rate' => 21, 'invoices.seller.vat_code' => 'LT100000000000']);
    $company = ProviderProfile::factory()->company()->create(['display_name' => 'UAB Meistrai']);
    $payment = Billing::complete(Payment::factory()->fake()->pending()->forPackage($this->package)->for($company->user)->create());

    $data = app(InvoicePdf::class)->data($payment);

    expect($data)->toMatchArray([
        'title' => 'PVM sąskaita faktūra',
        'net' => '20 €',
        'vat' => '4,20 €',
        'gross' => '24,20 €',
    ])
        ->and($data['buyer']['name'])->toBe('UAB Meistrai')
        ->and($data['buyer']['company_code'])->toBe($company->company_code)
        ->and($data['seller']['vat_code'])->toBe('LT100000000000');
});

test('rekvizitai užfiksuojami apmokėjimo metu – vėlesni profilio pakeitimai sąskaitos nekeičia', function () {
    $company = ProviderProfile::factory()->company()->create(['display_name' => 'UAB Senas']);
    $payment = Billing::complete(Payment::factory()->fake()->pending()->forPackage($this->package)->for($company->user)->create());

    $company->update(['display_name' => 'UAB Naujas', 'type' => ProviderType::Company]);

    expect(app(InvoicePdf::class)->data($payment->refresh())['buyer']['name'])->toBe('UAB Senas')
        ->and(app(InvoicePdf::class)->data($payment)['title'])->toBe('Sąskaita faktūra');
});

// --- Etapas 9b: serijos ir suma žodžiais ---

test('kreditinių sąskaitų serija turi savo numeraciją kiekvieniems metams, nepriklausomą nuo SF', function () {
    Payment::factory()->create(['invoice_number' => 'SF-2026-000040', 'paid_at' => '2026-02-01 10:00']);
    $generator = app(InvoiceNumberGenerator::class);

    $numbers = DB::transaction(fn () => [
        $generator->next(CarbonImmutable::parse('2026-03-10 12:00'), InvoiceSeries::CreditNote),
        $generator->next(CarbonImmutable::parse('2026-03-10 12:00')),
        $generator->next(CarbonImmutable::parse('2026-03-11 12:00'), InvoiceSeries::CreditNote),
        $generator->next(CarbonImmutable::parse('2027-01-05 12:00'), InvoiceSeries::CreditNote),
    ]);

    expect($numbers)->toBe(['KS-2026-000001', 'SF-2026-000041', 'KS-2026-000002', 'KS-2027-000001'])
        ->and(DB::table('invoice_sequences')->orderBy('series')->orderBy('year')->get(['series', 'year', 'last_number'])->map(fn ($row) => (array) $row)->all())
        ->toBe([
            ['series' => 'credit_note', 'year' => 2026, 'last_number' => 2],
            ['series' => 'credit_note', 'year' => 2027, 'last_number' => 1],
            ['series' => 'invoice', 'year' => 2026, 'last_number' => 41],
        ]);
});

test('kreditinių sąskaitų skaitiklis pradedamas nuo didžiausio jau esančio KS numerio, prefiksas – iš config', function () {
    Refund::factory()->create(['credit_note_number' => 'KS-2026-000050']);
    config(['invoices.credit_note_prefix' => 'KS']);

    $number = DB::transaction(fn () => app(InvoiceNumberGenerator::class)->next(CarbonImmutable::parse('2026-05-01 12:00'), InvoiceSeries::CreditNote));

    expect($number)->toBe('KS-2026-000051')
        ->and(app(InvoiceNumberGenerator::class)->format(2026, 7, InvoiceSeries::CreditNote))->toBe('KS-2026-000007');
});

test('sąskaitoje – suma žodžiais lietuviškai', function () {
    $payment = Billing::complete(Payment::factory()->fake()->pending()->forPackage($this->package)->for(Billing::provider())->create());

    $data = app(InvoicePdf::class)->data($payment);
    $html = view('invoices.invoice', $data)->render();

    expect($data['amount_in_words'])->toBe('Dvidešimt keturi eurai 20 ct')
        ->and($html)->toContain('Suma žodžiais: <strong>Dvidešimt keturi eurai 20 ct</strong>');
});
