<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Models\CreditPackage;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Payments\PaymentGatewayManager;

/**
 * Naujas laukiantis (pending) mokėjimas per numatytąjį mokėjimų tiekėją.
 * Kaina nukopijuojama į amount_cents: vėliau pakeitus paketo kainą, šis mokėjimas lieka už seną kainą.
 */
class CreatePayment
{
    public function __construct(private readonly PaymentGatewayManager $gateways) {}

    public function handle(User $user, CreditPackage|SubscriptionPlan $purchasable, ?Subscription $subscription = null): Payment
    {
        $payment = new Payment([
            'gateway' => $this->gateways->gateway()->type(),
            'amount_cents' => $purchasable->price_cents,
            'currency' => config('payments.currency'),
            'status' => PaymentStatus::Pending,
        ]);
        $payment->user()->associate($user);
        $payment->purchasable()->associate($purchasable);

        if ($subscription !== null) {
            $payment->subscription()->associate($subscription);
        }

        $payment->save();

        return $payment;
    }
}
