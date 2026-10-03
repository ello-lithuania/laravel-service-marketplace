<?php

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\CreditPackage;
use App\Models\CreditTransaction;
use App\Models\Payment;
use App\Services\Payments\Paysera\PayseraGateway;
use App\Services\Payments\Paysera\PayseraSigner;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Billing;
use Tests\Support\PayseraKeys;

/*
 * Paysera be tinklo: viešasis raktas – testuose sugeneruotas (failas laikinajame kataloge),
 * callback'ai pasirašomi jo privačiu raktu taip, kaip tai darytų Paysera.
 */

beforeEach(function () {
    Notification::fake();
    // Jokių tikrų HTTP užklausų (pvz. į paysera.com) – nesuklastota užklausa meta išimtį
    Http::preventStrayRequests();

    $this->keys = PayseraKeys::generate();
    $this->keyPath = tempnam(sys_get_temp_dir(), 'paysera-key-');
    file_put_contents($this->keyPath, $this->keys['public']);

    config([
        'payments.default' => 'paysera',
        'payments.paysera.project_id' => '123456',
        'payments.paysera.sign_password' => 'slaptazodis',
        'payments.paysera.test' => true,
        'payments.paysera.signature' => 'ss2',
        'payments.paysera.public_key_path' => $this->keyPath,
        'payments.paysera.callback_url' => null,
    ]);

    $this->provider = Billing::provider();
    $this->package = CreditPackage::factory()->create(['credits' => 30, 'bonus_credits' => 0, 'price_cents' => 2690]);
});

afterEach(function () {
    if (is_file($this->keyPath)) {
        unlink($this->keyPath);
    }
});

/**
 * Callback'o parametrai: data + ss1 + ss2 (pasirašyta „Paysera" privačiu raktu).
 *
 * @param  array<string, scalar>  $params
 * @return array{data: string, ss1: string, ss2: string}
 */
function payseraCallbackParams(array $params, string $privateKey, string $password = 'slaptazodis'): array
{
    $signer = new PayseraSigner($password);
    $data = $signer->encode($params);

    return ['data' => $data, 'ss1' => $signer->sign($data), 'ss2' => PayseraKeys::ss2($data, $privateKey)];
}

/**
 * Tipinis sėkmingo apmokėjimo callback'as.
 *
 * @return array<string, scalar>
 */
function payseraPaidParams(Payment $payment, array $overrides = []): array
{
    return [
        'projectid' => '123456',
        'orderid' => $payment->uuid,
        'amount' => (string) $payment->amount_cents,
        'currency' => 'EUR',
        'status' => '1',
        'requestid' => '987654321',
        'payment' => 'hanza',
        'test' => '1',
        'name' => 'Jonas',
        'surename' => 'Jonaitis',
        'p_email' => 'jonas@example.test',
        ...$overrides,
    ];
}

test('pirkimas nukreipia į Paysera su pasirašytais užsakymo duomenimis', function () {
    $response = $this->actingAs($this->provider)
        ->withHeader('X-Inertia', 'true')
        ->post("/teikejas/kreditai/{$this->package->id}/pirkti")
        ->assertStatus(409);

    $payment = Payment::query()->sole();
    $location = $response->headers->get('X-Inertia-Location');
    parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);
    $params = (new PayseraSigner('slaptazodis'))->decode($query['data']);

    expect($payment->gateway)->toBe(PaymentGateway::Paysera)
        ->and($location)->toStartWith('https://bank.paysera.com/pay/?')
        ->and($query['sign'])->toBe(md5($query['data'].'slaptazodis'))
        ->and($params)->toMatchArray([
            'projectid' => '123456',
            'orderid' => $payment->uuid,
            'amount' => '2690',
            'currency' => 'EUR',
            'version' => '1.6',
            'lang' => 'LIT',
            'test' => '1',
            'accepturl' => route('payments.show', $payment),
            'cancelurl' => route('payments.cancel', $payment),
            'callbackurl' => route('payments.callback', ['gateway' => 'paysera']),
            'p_email' => $this->provider->email,
        ])
        ->and($params['paytext'])->toContain('[order_nr]')->toContain('[site_name]');
});

test('be projekto numerio Paysera mokėjimo pradėti negalima', function () {
    config(['payments.paysera.project_id' => null]);
    $payment = Payment::factory()->pending()->forPackage($this->package)->for($this->provider)->create(['gateway' => PaymentGateway::Paysera]);

    app(PayseraGateway::class)->startPayment($payment);
})->throws(RuntimeException::class, 'Paysera nesukonfigūruota');

test('teisingas ss2 callback\'as apmoka mokėjimą, atsakymas „OK", kartojant – vis tiek vienas užskaitymas', function () {
    $payment = Payment::factory()->pending()->forPackage($this->package)->for($this->provider)->create(['gateway' => PaymentGateway::Paysera]);
    $callback = payseraCallbackParams(payseraPaidParams($payment), $this->keys['private']);

    // Paysera gali siųsti ir GET, ir POST – be CSRF žetono ir be prisijungimo
    $this->get('/mokejimai/callback/paysera?'.http_build_query($callback))->assertOk()->assertContent('OK');
    $this->post('/mokejimai/callback/paysera', $callback)->assertOk()->assertContent('OK');

    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->gateway_reference)->toBe('987654321')
        ->and($payment->meta)->toMatchArray(['status' => '1', 'payment' => 'hanza', 'test' => '1'])
        // Asmens duomenų iš callback'o nesaugom
        ->and($payment->meta)->not->toHaveKeys(['name', 'surename', 'p_email'])
        ->and(CreditTransaction::query()->count())->toBe(1)
        ->and($this->provider->providerProfile->refresh()->credits_balance)->toBe(30);
});

test('ss1 režimas: teisingas md5 parašas priimamas, netinkamas – ne', function () {
    config(['payments.paysera.signature' => 'ss1']);
    $payment = Payment::factory()->pending()->forPackage($this->package)->for($this->provider)->create(['gateway' => PaymentGateway::Paysera]);
    $callback = payseraCallbackParams(payseraPaidParams($payment), $this->keys['private']);

    $this->post('/mokejimai/callback/paysera', [...$callback, 'ss1' => md5('suklastota')])->assertStatus(400);
    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);

    $this->post('/mokejimai/callback/paysera', $callback)->assertOk()->assertContent('OK');
    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid);
});

test('atmetami: suklastotas parašas, svetimas projektas, testinis mokėjimas ne testiniame režime, kita suma', function (Closure $makeCallback, ?Closure $configure = null) {
    $configure?->__invoke();
    $payment = Payment::factory()->pending()->forPackage($this->package)->for($this->provider)->create(['gateway' => PaymentGateway::Paysera]);

    $this->post('/mokejimai/callback/paysera', $makeCallback($payment, $this->keys['private']))->assertStatus(400);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and(CreditTransaction::query()->count())->toBe(0);
})->with([
    'pasirašyta kitu raktu' => [fn (Payment $p) => payseraCallbackParams(payseraPaidParams($p), PayseraKeys::generate()['private'])],
    'pakeisti duomenys po pasirašymo' => [function (Payment $p, string $key) {
        $callback = payseraCallbackParams(payseraPaidParams($p), $key);

        return [...$callback, 'data' => (new PayseraSigner('slaptazodis'))->encode(payseraPaidParams($p, ['amount' => '1']))];
    }],
    'svetimas projektas' => [fn (Payment $p, string $key) => payseraCallbackParams(payseraPaidParams($p, ['projectid' => '999']), $key)],
    'kita suma' => [fn (Payment $p, string $key) => payseraCallbackParams(payseraPaidParams($p, ['amount' => '100']), $key)],
    'testinis mokėjimas produkciniame režime' => [
        fn (Payment $p, string $key) => payseraCallbackParams(payseraPaidParams($p), $key),
        fn () => config(['payments.paysera.test' => false]),
    ],
    'be data parametro' => [fn () => ['ss1' => 'x', 'ss2' => 'y']],
]);

test('būsena 0 – nepavyko, būsena 2 – dar laukiam (nieko nekeičiam, bet atsakom „OK")', function () {
    $failed = Payment::factory()->pending()->forPackage($this->package)->for($this->provider)->create(['gateway' => PaymentGateway::Paysera]);
    $waiting = Payment::factory()->pending()->forPackage($this->package)->for($this->provider)->create(['gateway' => PaymentGateway::Paysera]);

    $this->post('/mokejimai/callback/paysera', payseraCallbackParams(payseraPaidParams($failed, ['status' => '0']), $this->keys['private']))
        ->assertOk()->assertContent('OK');
    $this->post('/mokejimai/callback/paysera', payseraCallbackParams(payseraPaidParams($waiting, ['status' => '2', 'requestid' => '111']), $this->keys['private']))
        ->assertOk()->assertContent('OK');

    expect($failed->refresh()->status)->toBe(PaymentStatus::Failed)
        ->and($waiting->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and(CreditTransaction::query()->count())->toBe(0);
});

test('be rakto failo viešasis raktas parsiunčiamas vieną kartą ir laikomas cache', function () {
    unlink($this->keyPath);
    Http::fake(['www.paysera.com/download/public.key' => Http::response($this->keys['public'])]);

    $first = Payment::factory()->pending()->forPackage($this->package)->for($this->provider)->create(['gateway' => PaymentGateway::Paysera]);
    $second = Payment::factory()->pending()->forPackage($this->package)->for($this->provider)->create(['gateway' => PaymentGateway::Paysera]);

    $this->post('/mokejimai/callback/paysera', payseraCallbackParams(payseraPaidParams($first, ['requestid' => '1']), $this->keys['private']))->assertOk();
    $this->post('/mokejimai/callback/paysera', payseraCallbackParams(payseraPaidParams($second, ['requestid' => '2']), $this->keys['private']))->assertOk();

    Http::assertSentCount(1);
    expect($first->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and($second->refresh()->status)->toBe(PaymentStatus::Paid);
});

test('nepavykus gauti rakto callback\'as atmetamas (fail closed), o ne priimamas be patikros', function () {
    unlink($this->keyPath);
    Http::fake(['www.paysera.com/*' => Http::response('Not found', 404)]);
    $payment = Payment::factory()->pending()->forPackage($this->package)->for($this->provider)->create(['gateway' => PaymentGateway::Paysera]);

    $this->post('/mokejimai/callback/paysera', payseraCallbackParams(payseraPaidParams($payment), $this->keys['private']))
        ->assertStatus(400)
        ->assertSee('payments:paysera-key');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

test('payments:paysera-key parsiunčia ir įrašo raktą', function () {
    unlink($this->keyPath);
    Http::fake(['www.paysera.com/download/public.key' => Http::response($this->keys['public'])]);

    $this->artisan('payments:paysera-key')->assertSuccessful();

    expect(file_get_contents($this->keyPath))->toBe($this->keys['public']);
});
