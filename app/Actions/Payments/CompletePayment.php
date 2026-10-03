<?php

namespace App\Actions\Payments;

use App\Actions\Subscriptions\ActivateSubscription;
use App\Actions\Subscriptions\RenewSubscription;
use App\Enums\CreditTransactionType;
use App\Enums\PaymentStatus;
use App\Models\CreditPackage;
use App\Models\Payment;
use App\Models\ProviderProfile;
use App\Models\SubscriptionPlan;
use App\Services\Credits\CreditLedger;
use App\Services\Invoices\BillingDetails;
use App\Services\Invoices\InvoiceNumberGenerator;
use App\Services\Payments\PaymentResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * Mokėjimas apmokėtas: būsena „paid", nupirkto dalyko suteikimas, sąskaitos numeris ir rekvizitų „nuotrauka".
 *
 * Kviečiama tik iš ProcessPaymentResult – jos transakcijos viduje, kai mokėjimo eilutė jau užrakinta.
 * Užraktų tvarka visada ta pati: mokėjimas → teikėjas → prenumerata → sąskaitų skaitiklis (taip išvengiam deadlock'ų).
 */
class CompletePayment
{
    public function __construct(
        private readonly CreditLedger $ledger,
        private readonly ActivateSubscription $activateSubscription,
        private readonly RenewSubscription $renewSubscription,
        private readonly InvoiceNumberGenerator $invoiceNumbers,
        private readonly BillingDetails $billingDetails,
    ) {}

    public function handle(Payment $payment, PaymentResult $result): Payment
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('CompletePayment vykdomas tik DB transakcijos viduje (ProcessPaymentResult).');
        }

        $payment->loadMissing(['user', 'purchasable', 'subscription']);
        $paidAt = now();

        $payment->forceFill([
            'status' => PaymentStatus::Paid,
            'paid_at' => $paidAt,
            'gateway_reference' => $result->gatewayReference ?? $payment->gateway_reference,
            'meta' => [...($payment->meta ?? []), ...$result->meta],
        ])->save();

        $this->fulfil($payment);

        // Numeris – paskutinis: skaitiklio eilutė užrakinta trumpiausią laiką (iki COMMIT)
        $payment->forceFill([
            'invoice_number' => $this->invoiceNumbers->next($paidAt),
            'billing_details' => $this->billingDetails->snapshot($payment->user),
        ])->save();

        return $payment;
    }

    /**
     * Suteikia tai, už ką sumokėta: kreditus (paketas) arba prenumeratą (planas).
     */
    private function fulfil(Payment $payment): void
    {
        $purchasable = $payment->purchasable;

        if ($purchasable instanceof SubscriptionPlan) {
            $payment->subscription === null
                ? $this->activateSubscription->handle($payment, $purchasable)
                : $this->renewSubscription->handle($payment, $payment->subscription);

            return;
        }

        if (! $purchasable instanceof CreditPackage) {
            Log::error('Apmokėtas mokėjimas be žinomo pirkinio – reikia sutvarkyti rankiniu būdu.', ['payment' => $payment->uuid]);

            return;
        }

        $provider = ProviderProfile::withTrashed()->where('user_id', $payment->user_id)->first();

        if ($provider === null) {
            Log::error('Apmokėtas kreditų paketas, bet teikėjo profilio nėra – kreditus suteikti rankiniu būdu.', ['payment' => $payment->uuid]);

            return;
        }

        // Paketo kreditai + dovanų kreditai; source = payment – iš ledger'io matyti, už kurį mokėjimą
        $this->ledger->credit(
            $provider,
            $purchasable->totalCredits(),
            CreditTransactionType::Purchase,
            $payment,
            __('billing.ledger.purchase', ['name' => $purchasable->name]),
        );
    }
}
