<?php

namespace App\Actions\Subscriptions;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Subscription;

/**
 * Prenumerata nebepratęsiama (atšaukta, pasibaigė, pakeista kitu planu) – jos laukiantys pratęsimo mokėjimai
 * atšaukiami, kad teikėjas per klaidą nesumokėtų už tai, ko negaus.
 */
class CancelPendingRenewalPayments
{
    public function handle(Subscription $subscription, ?Payment $except = null): int
    {
        return Payment::query()
            ->where('subscription_id', $subscription->id)
            ->where('status', PaymentStatus::Pending)
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except?->id))
            ->update(['status' => PaymentStatus::Cancelled, 'updated_at' => now()]);
    }
}
