<?php

namespace App\Actions\Payments;

use App\Enums\PaymentGateway as GatewayType;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidPaymentCallbackException;
use App\Models\Payment;
use App\Notifications\PaymentSucceeded;
use App\Services\Payments\PaymentResult;
use Illuminate\Support\Facades\DB;

/**
 * Patikrinto mokėjimų tiekėjo callback'o apdorojimas – IDEMPOTENTIŠKAS: tą patį callback'ą galima gauti
 * 2, 5 ar 10 kartų (Paysera kartoja, kol negauna „OK"; tinklas gali nutrūkti po mūsų atsakymo), o kreditai
 * užskaitomi lygiai vieną kartą.
 *
 * Kaip tai užtikrinta:
 *   1. viskas vienoje DB transakcijoje, mokėjimo eilutė užrakinta (lockForUpdate) – du lygiagretūs callback'ai
 *      vyksta po vieną, ir antrasis jau mato „paid";
 *   2. jau apmokėtas mokėjimas nebekeičiamas – antram kartui tiesiog atsakom „OK";
 *   3. saugikliai DB lygiu: UNIQUE(gateway, gateway_reference) ir kreditų ledger'is su source = payment.
 */
class ProcessPaymentResult
{
    public function __construct(private readonly CompletePayment $complete) {}

    /**
     * @throws InvalidPaymentCallbackException kai mokėjimas nerastas ar nesutampa suma
     */
    public function handle(GatewayType $gateway, PaymentResult $result): Payment
    {
        /** @var array{0: Payment, 1: bool} $outcome */
        $outcome = DB::transaction(function () use ($gateway, $result): array {
            $payment = Payment::query()->where('uuid', $result->paymentUuid)->lockForUpdate()->first();

            if ($payment === null || $payment->gateway !== $gateway) {
                throw new InvalidPaymentCallbackException('Mokėjimas nerastas.');
            }

            $this->ensureAmountMatches($payment, $result);

            // Idempotencija: apmokėtas (ar grąžintas) mokėjimas nebekeičiamas
            if (in_array($payment->status, [PaymentStatus::Paid, PaymentStatus::Refunded], true)) {
                return [$payment, false];
            }

            return match ($result->status) {
                // Ir anksčiau nepavykęs ar atšauktas mokėjimas gali būti apmokėtas vėliau – pinigus gavom, juos užskaitom
                PaymentStatus::Paid => [$this->complete->handle($payment, $result), true],
                PaymentStatus::Failed, PaymentStatus::Cancelled => [$this->close($payment, $result), false],
                // Pending ir kt. – tiekėjas dar nebaigė, laukiam galutinio atsakymo
                default => [$payment, false],
            };
        });

        [$payment, $paidNow] = $outcome;

        // Pranešimas – tik transakcijai pavykus (kitaip žmogus gautų laišką apie neįvykusį apmokėjimą)
        if ($paidNow) {
            $payment->loadMissing('user')->user->notify(new PaymentSucceeded($payment));
        }

        return $payment;
    }

    private function ensureAmountMatches(Payment $payment, PaymentResult $result): void
    {
        $amountDiffers = $result->amountCents !== null && $result->amountCents !== $payment->amount_cents;
        $currencyDiffers = $result->currency !== null && $result->currency !== $payment->currency;

        if ($amountDiffers || $currencyDiffers) {
            throw new InvalidPaymentCallbackException('Mokėjimo suma ar valiuta nesutampa su užsakymu.');
        }
    }

    /**
     * Nepavyko arba pirkėjas atšaukė – keičiam tik laukiantį mokėjimą, kreditų nėra.
     */
    private function close(Payment $payment, PaymentResult $result): Payment
    {
        if ($payment->isPending()) {
            $payment->forceFill([
                'status' => $result->status,
                'meta' => [...($payment->meta ?? []), ...$result->meta],
            ])->save();
        }

        return $payment;
    }
}
