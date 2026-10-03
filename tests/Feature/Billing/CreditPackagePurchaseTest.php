<?php

use App\Enums\CreditTransactionType;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\CreditPackage;
use App\Models\CreditTransaction;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentSucceeded;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Billing;

beforeEach(function () {
    config(['payments.default' => 'fake']);
    Notification::fake();

    $this->provider = Billing::provider(credits: 2);
    $this->package = CreditPackage::factory()->create(['name' => '60 kreditų', 'credits' => 60, 'bonus_credits' => 5, 'price_cents' => 4990]);
});

test('„Pirkti" sukuria laukiantį mokėjimą ir nukreipia į mokėjimų tiekėją (Inertia location)', function () {
    $response = $this->actingAs($this->provider)
        ->withHeader('X-Inertia', 'true')
        ->post("/teikejas/kreditai/{$this->package->id}/pirkti");

    $payment = Payment::query()->sole();

    // Inertia užklausai – 409 + X-Inertia-Location: naršyklė atidaro tiekėjo puslapį visu langu
    $response->assertStatus(409)->assertHeader('X-Inertia-Location', route('payments.fake.show', $payment));

    expect($payment)
        ->status->toBe(PaymentStatus::Pending)
        ->gateway->toBe(PaymentGateway::Fake)
        ->amount_cents->toBe(4990)
        ->user_id->toBe($this->provider->id)
        ->purchasable_type->toBe('credit_package')
        ->invoice_number->toBeNull()
        ->and($this->provider->providerProfile->refresh()->credits_balance)->toBe(2);
});

test('testinio tiekėjo puslapis rodomas tik mokėjimo savininkui', function () {
    $payment = Payment::factory()->fake()->pending()->forPackage($this->package)->for($this->provider)->create();

    $this->actingAs($this->provider)->get("/mokejimai/{$payment->uuid}/testinis")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('billing/FakeCheckout')
            ->where('payment.uuid', $payment->uuid)
            ->where('payment.description', 'Kreditų paketas „60 kreditų"'));

    $this->actingAs(Billing::provider())->get("/mokejimai/{$payment->uuid}/testinis")->assertForbidden();
});

test('apmokėjus: kreditai su dovanų, ledger įrašas, sąskaitos numeris, rekvizitai ir pranešimas', function () {
    $payment = Payment::factory()->fake()->pending()->forPackage($this->package)->for($this->provider)->create();

    $this->actingAs($this->provider)
        ->post("/mokejimai/{$payment->uuid}/testinis", ['result' => 'paid'])
        ->assertRedirect(route('payments.show', $payment))
        ->assertInertiaFlash('toast.message', 'Ačiū! Mokėjimas gautas.');

    $payment->refresh();
    $transaction = CreditTransaction::query()->sole();

    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->paid_at)->not->toBeNull()
        ->and($payment->gateway_reference)->toStartWith('FAKE-')
        ->and($payment->invoice_number)->toMatch('/^SF-\d{4}-\d{6}$/')
        ->and($payment->billing_details['buyer']['email'])->toBe($this->provider->email)
        ->and($payment->billing_details['seller']['name'])->toBe(config('app.name'))
        ->and($this->provider->providerProfile->refresh()->credits_balance)->toBe(2 + 65)
        ->and($transaction->amount)->toBe(65)
        ->and($transaction->balance_after)->toBe(67)
        ->and($transaction->type)->toBe(CreditTransactionType::Purchase)
        ->and($transaction->source_type)->toBe('payment')
        ->and($transaction->source_id)->toBe($payment->id)
        ->and($transaction->description)->toBe('Kreditų paketas „60 kreditų"');

    Notification::assertSentTo($this->provider, PaymentSucceeded::class, fn (PaymentSucceeded $n) => $n->payment->is($payment));
});

test('pakartotas callback\'as kreditų antrą kartą neužskaito (idempotencija)', function () {
    $payment = Payment::factory()->fake()->pending()->forPackage($this->package)->for($this->provider)->create();
    $payload = Billing::callbackPayload($payment, PaymentStatus::Paid, 'FAKE-REPEATED');

    // Tiekėjo serveris kartoja tą patį callback'ą (pvz. negavo mūsų „OK")
    $this->post('/mokejimai/callback/fake', $payload)->assertOk()->assertContent('OK');
    $this->post('/mokejimai/callback/fake', $payload)->assertOk()->assertContent('OK');
    $this->get('/mokejimai/callback/fake?'.http_build_query($payload))->assertOk()->assertContent('OK');

    expect(CreditTransaction::query()->count())->toBe(1)
        ->and($this->provider->providerProfile->refresh()->credits_balance)->toBe(67)
        ->and($payment->refresh()->invoice_number)->not->toBeNull();

    Notification::assertSentToTimes($this->provider, PaymentSucceeded::class, 1);
});

test('net ir kitu tiekėjo ID pakartotas apmokėjimas neužskaito antrą kartą', function () {
    $payment = Payment::factory()->fake()->pending()->forPackage($this->package)->for($this->provider)->create();

    Billing::complete($payment);
    Billing::complete($payment);

    expect(CreditTransaction::query()->count())->toBe(1)
        ->and($this->provider->providerProfile->refresh()->credits_balance)->toBe(67);
});

test('nepavykęs ir atšauktas mokėjimas kreditų nesuteikia', function (string $result, PaymentStatus $status) {
    $payment = Payment::factory()->fake()->pending()->forPackage($this->package)->for($this->provider)->create();

    $this->actingAs($this->provider)
        ->post("/mokejimai/{$payment->uuid}/testinis", ['result' => $result])
        ->assertRedirect(route('payments.show', $payment));

    expect($payment->refresh()->status)->toBe($status)
        ->and($payment->invoice_number)->toBeNull()
        ->and(CreditTransaction::query()->count())->toBe(0)
        ->and($this->provider->providerProfile->refresh()->credits_balance)->toBe(2);

    Notification::assertNothingSent();
})->with([
    'nepavyko' => ['failed', PaymentStatus::Failed],
    'atšauktas' => ['cancelled', PaymentStatus::Cancelled],
]);

test('Paysera cancelurl atšaukia laukiantį mokėjimą, o vėlesnis „apmokėta" vis tiek užskaitomas', function () {
    $payment = Payment::factory()->fake()->pending()->forPackage($this->package)->for($this->provider)->create();

    $this->actingAs($this->provider)
        ->get("/mokejimai/{$payment->uuid}/atsaukti")
        ->assertRedirect(route('payments.show', $payment))
        ->assertInertiaFlash('toast.message', 'Mokėjimas atšauktas – pinigai nenuskaičiuoti.');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Cancelled);

    // Pirkėjas vis dėlto apmokėjo kitame skirtuke – pinigus gavom, juos užskaitom
    Billing::complete($payment);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and($this->provider->providerProfile->refresh()->credits_balance)->toBe(67);
});

test('netinkamas parašas ar nesutampanti suma – 400, niekas nekeičiama', function (Closure $tamper) {
    $payment = Payment::factory()->fake()->pending()->forPackage($this->package)->for($this->provider)->create();

    $this->post('/mokejimai/callback/fake', $tamper(Billing::callbackPayload($payment), $payment))->assertStatus(400);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and(CreditTransaction::query()->count())->toBe(0);
})->with([
    'suklastotas parašas' => [fn (array $payload) => [...$payload, 'signature' => str_repeat('a', 64)]],
    'pakeista suma (parašas nebetinka)' => [fn (array $payload) => [...$payload, 'amount' => 1]],
    'kita suma su teisingu parašu' => [function (array $payload, Payment $payment) {
        $payment->forceFill(['amount_cents' => 100])->save();

        return $payload;
    }],
    'nežinomas mokėjimas' => [fn (array $payload) => [...$payload, 'payment' => '00000000-0000-4000-8000-000000000000']],
]);

test('kitam tiekėjui priklausantis mokėjimas per testinį callback\'ą neapmokamas', function () {
    $payment = Payment::factory()->pending()->forPackage($this->package)->for($this->provider)->create(['gateway' => PaymentGateway::Paysera]);

    $this->post('/mokejimai/callback/fake', Billing::callbackPayload($payment))->assertStatus(400);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

test('nebeparduodamo paketo pirkti negalima', function () {
    $this->package->update(['is_active' => false]);

    $this->actingAs($this->provider)
        ->from('/kainos')
        ->post("/teikejas/kreditai/{$this->package->id}/pirkti")
        ->assertRedirect('/kainos')
        ->assertSessionHasErrors(['purchase' => 'Šis kreditų paketas nebeparduodamas.']);

    expect(Payment::query()->count())->toBe(0);
});

test('pirkti gali tik teikėjas su profiliu', function () {
    $this->post("/teikejas/kreditai/{$this->package->id}/pirkti")->assertRedirect('/login');

    $this->actingAs(User::factory()->create())
        ->post("/teikejas/kreditai/{$this->package->id}/pirkti")
        ->assertForbidden();

    $this->actingAs(User::factory()->provider()->create())
        ->post("/teikejas/kreditai/{$this->package->id}/pirkti")
        ->assertRedirect(route('provider.details.edit'));

    expect(Payment::query()->count())->toBe(0);
});

test('produkcijoje testinio tiekėjo nėra: callback\'as ir puslapis – 404', function () {
    $payment = Payment::factory()->fake()->pending()->forPackage($this->package)->for($this->provider)->create();
    $payload = Billing::callbackPayload($payment);

    app()->detectEnvironment(fn () => 'production');

    $this->post('/mokejimai/callback/fake', $payload)->assertNotFound();
    $this->actingAs($this->provider)->get("/mokejimai/{$payment->uuid}/testinis")->assertNotFound();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
});
