<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Pirkėjas Paysera puslapyje paspaudė „Atšaukti" (cancelurl) arba prenumerata baigėsi, o jos pratęsimas liko neapmokėtas.
 * Keičiamas tik laukiantis mokėjimas. Jei tiekėjas vėliau vis tiek atsiųs „apmokėta", pinigus užskaitysim
 * (ProcessPaymentResult leidžia cancelled → paid).
 */
class CancelPayment
{
    public function handle(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment): Payment {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->isPending()) {
                $locked->forceFill(['status' => PaymentStatus::Cancelled])->save();
            }

            $payment->setRawAttributes($locked->getAttributes(), true);

            return $payment;
        });
    }
}
