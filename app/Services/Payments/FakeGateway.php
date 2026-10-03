<?php

namespace App\Services\Payments;

use App\Enums\PaymentGateway as GatewayType;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidPaymentCallbackException;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Netikras mokėjimų tiekėjas dev'ui ir testams: vietoje Paysera atidaromas vietinis puslapis su mygtukais
 * „Apmokėti", „Nepavyko" ir „Atšaukti". Paspaudus sukuriamas toks pat pasirašytas callback'as, kokį atsiųstų
 * tikras tiekėjas, ir jis apdorojamas TUO PAČIU kodu (ProcessPaymentResult) – todėl patikrinam visą srautą
 * be Paysera paskyros ir be interneto.
 *
 * Parašas – HMAC SHA256 su APP_KEY: be jo callback'o suklastoti negalima net dev'e.
 * Produkcijoje šis tiekėjas uždraustas (PaymentGatewayManager::createFakeDriver).
 */
class FakeGateway implements PaymentGateway
{
    public function __construct(private readonly string $key) {}

    public function type(): GatewayType
    {
        return GatewayType::Fake;
    }

    public function startPayment(Payment $payment): string
    {
        return route('payments.fake.show', $payment);
    }

    /**
     * Pasirašyti callback'o duomenys – tokius „atsiųstų" tiekėjas.
     *
     * @return array{payment: string, status: string, reference: string, amount: int, signature: string}
     */
    public function callbackPayload(Payment $payment, PaymentStatus $status, ?string $reference = null): array
    {
        $reference ??= 'FAKE-'.Str::upper(Str::random(16));

        return [
            'payment' => $payment->uuid,
            'status' => $status->value,
            'reference' => $reference,
            'amount' => $payment->amount_cents,
            'signature' => $this->sign($payment->uuid, $status->value, $reference, $payment->amount_cents),
        ];
    }

    public function handleCallback(Request $request): PaymentResult
    {
        $uuid = (string) $request->input('payment');
        $status = PaymentStatus::tryFrom((string) $request->input('status'));
        $reference = (string) $request->input('reference');
        $amount = (int) $request->input('amount');
        $signature = (string) $request->input('signature');

        if ($status === null || ! hash_equals($this->sign($uuid, $status->value, $reference, $amount), $signature)) {
            throw new InvalidPaymentCallbackException('Neteisingas testinio mokėjimo parašas.');
        }

        return new PaymentResult(
            paymentUuid: $uuid,
            status: $status,
            gatewayReference: $reference,
            amountCents: $amount,
            currency: 'EUR',
            meta: ['status' => $status->value, 'test' => '1'],
        );
    }

    public function acknowledge(PaymentResult $result): Response
    {
        return response('OK', 200, ['Content-Type' => 'text/plain']);
    }

    private function sign(string $uuid, string $status, string $reference, int $amount): string
    {
        return hash_hmac('sha256', implode('|', [$uuid, $status, $reference, $amount]), $this->key);
    }
}
