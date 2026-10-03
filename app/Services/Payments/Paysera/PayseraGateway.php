<?php

namespace App\Services\Payments\Paysera;

use App\Enums\PaymentGateway as GatewayType;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidPaymentCallbackException;
use App\Models\Payment;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentResult;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Paysera (WebToPay / Checkout) mokėjimai be SDK paketo.
 *
 * Eiga:
 *   1. startPayment() – pirkėjas nukreipiamas į Paysera su užsakymo duomenimis ir parašu;
 *   2. Paysera SERVERIS kviečia mūsų callbackurl (mokėjimų tiekėjo „webhook'as") – tik juo tikim, nes
 *      pirkėjo naršyklė (accepturl) gali grįžti ir be apmokėjimo, o URL'ą galima suklastoti;
 *   3. handleCallback() patikrina parašą, projekto numerį, testinį režimą ir grąžina PaymentResult;
 *   4. atsakom „OK" – kitaip Paysera callback'ą kartos (todėl apdorojimas turi būti idempotentiškas).
 * https://developers.paysera.com/en/checkout/basic
 */
class PayseraGateway implements PaymentGateway
{
    private const API_VERSION = '1.6';

    /** Callback'o laukai, kuriuos saugom payments.meta (vardas, pavardė ir el. paštas – ne). */
    private const META_FIELDS = ['status', 'requestid', 'payment', 'payamount', 'paycurrency', 'test', 'type', 'country', 'version'];

    public function __construct(
        private readonly PayseraSigner $signer,
        private readonly PayseraPublicKey $publicKey,
        private readonly ?string $projectId,
        private readonly bool $test,
        private readonly string $payUrl,
        private readonly ?string $callbackUrl,
        private readonly string $signature,
    ) {}

    public function type(): GatewayType
    {
        return GatewayType::Paysera;
    }

    public function startPayment(Payment $payment): string
    {
        if (blank($this->projectId)) {
            throw new RuntimeException('Paysera nesukonfigūruota: .env faile nurodykite PAYSERA_PROJECT_ID ir PAYSERA_SIGN_PASSWORD.');
        }

        $data = $this->signer->encode($this->requestParams($payment));

        return $this->payUrl.'?'.http_build_query(['data' => $data, 'sign' => $this->signer->sign($data)]);
    }

    /**
     * Užklausos parametrai (Paysera „specification"). amount – centais, kaip ir mūsų DB.
     *
     * @return array<string, scalar|null>
     */
    public function requestParams(Payment $payment): array
    {
        $payment->loadMissing(['user', 'purchasable']);

        return [
            'projectid' => $this->projectId,
            'orderid' => $payment->uuid,
            'accepturl' => route('payments.show', $payment),
            'cancelurl' => route('payments.cancel', $payment),
            'callbackurl' => $this->callbackUrl ?? route('payments.callback', ['gateway' => 'paysera']),
            'version' => self::API_VERSION,
            'lang' => 'LIT',
            'amount' => $payment->amount_cents,
            'currency' => $payment->currency,
            'country' => 'LT',
            // [order_nr] ir [site_name] Paysera pakeičia pati (privalomi, jei paytext nurodomas)
            'paytext' => Str::limit($payment->description(), 150, '…').' (užsakymas [order_nr]) ([site_name])',
            'p_email' => $payment->user->email,
            'test' => $this->test ? 1 : 0,
        ];
    }

    public function handleCallback(Request $request): PaymentResult
    {
        $data = $request->input('data');

        if (! is_string($data) || $data === '') {
            throw new InvalidPaymentCallbackException('Trūksta Paysera „data" parametro.');
        }

        $this->verifySignature($data, $request);

        $params = $this->signer->decode($data);

        if (($params['projectid'] ?? null) !== (string) $this->projectId) {
            throw new InvalidPaymentCallbackException('Callback\'as skirtas kitam Paysera projektui.');
        }

        // Testinis mokėjimas (be pinigų) produkcijoje kreditų suteikti negali
        if (($params['test'] ?? '0') !== '0' && ! $this->test) {
            throw new InvalidPaymentCallbackException('Gautas testinis Paysera mokėjimas, nors testinis režimas išjungtas.');
        }

        $orderId = $params['orderid'] ?? '';

        if (! Str::isUuid($orderId)) {
            throw new InvalidPaymentCallbackException('Netinkamas užsakymo numeris.');
        }

        return new PaymentResult(
            paymentUuid: $orderId,
            status: self::status($params['status'] ?? ''),
            gatewayReference: isset($params['requestid']) && $params['requestid'] !== '' ? $params['requestid'] : null,
            amountCents: isset($params['amount']) && is_numeric($params['amount']) ? (int) $params['amount'] : null,
            currency: $params['currency'] ?? null,
            meta: Arr::only($params, self::META_FIELDS),
        );
    }

    public function acknowledge(PaymentResult $result): Response
    {
        return response('OK', 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * Paysera būsenos: 1 – apmokėta; 0 – neapmokėta; 2 – priimta, bet dar nebaigta; 3 – papildoma informacija;
     * kitos – dar nežinomos. Viskas, kas ne 1 ir ne 0, mums reiškia „dar laukiam" (nieko nekeičiam).
     */
    public static function status(string $status): PaymentStatus
    {
        return match ($status) {
            '1' => PaymentStatus::Paid,
            '0' => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };
    }

    /**
     * ss2 (RSA, numatytasis) arba ss1 (md5 su slaptažodžiu) – pagal config('payments.paysera.signature').
     */
    private function verifySignature(string $data, Request $request): void
    {
        if ($this->signature === 'ss1') {
            $ss1 = $request->input('ss1');

            if (! is_string($ss1) || ! $this->signer->verifySs1($data, $ss1)) {
                throw new InvalidPaymentCallbackException('Neteisingas Paysera ss1 parašas.');
            }

            return;
        }

        $ss2 = $request->input('ss2');
        $publicKey = $this->publicKey->get();

        if ($publicKey === null) {
            throw new InvalidPaymentCallbackException('Nėra Paysera viešojo rakto (paleiskite „php artisan payments:paysera-key").');
        }

        if (! is_string($ss2) || ! $this->signer->verifySs2($data, $ss2, $publicKey)) {
            throw new InvalidPaymentCallbackException('Neteisingas Paysera ss2 parašas.');
        }
    }
}
