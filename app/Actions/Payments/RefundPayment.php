<?php

namespace App\Actions\Payments;

use App\Actions\Subscriptions\CancelPendingRenewalPayments;
use App\Enums\CreditTransactionType;
use App\Enums\InvoiceSeries;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Payment;
use App\Models\ProviderProfile;
use App\Models\Refund;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\PaymentRefunded;
use App\Services\Credits\CreditLedger;
use App\Services\Invoices\BillingDetails;
use App\Services\Invoices\InvoiceNumberGenerator;
use App\Services\Payments\RefundCalculation;
use App\Services\Payments\RefundCalculator;
use Illuminate\Support\Facades\DB;

/**
 * Administratorius grąžina mokėjimą (Filament → Mokėjimai → „Grąžinti pinigus"). Taisyklės – docs/DB_SCHEMA.md → refunds.
 *
 * Pačius pinigus administratorius grąžina Paysera savitarnoje (API nekviečiam – žr. DB_SCHEMA), o čia
 * užfiksuojama buhalterinė pusė: būsena „refunded", kreditų atėmimas per ledger'į, prenumeratos sutrumpinimas,
 * kreditinė sąskaita su savo numeriu ir pranešimas teikėjui.
 *
 * IDEMPOTENTIŠKA ta prasme, kad dvigubai negrąžins: mokėjimo eilutė užrakinama (lockForUpdate) ir būsena
 * tikrinama užrakinus – antras (ar lygiagretus) bandymas gauna InvalidStateTransitionException. Paskutinis
 * saugiklis – UNIQUE(refunds.payment_id).
 * Užraktų tvarka kaip CompletePayment: mokėjimas → teikėjas → prenumerata → numerių skaitiklis (be deadlock'ų).
 */
class RefundPayment
{
    public function __construct(
        private readonly RefundCalculator $calculator,
        private readonly CreditLedger $ledger,
        private readonly InvoiceNumberGenerator $numbers,
        private readonly BillingDetails $billingDetails,
        private readonly CancelPendingRenewalPayments $cancelRenewals,
    ) {}

    /**
     * @throws InvalidStateTransitionException kai mokėjimas ne „paid" (pvz. jau grąžintas)
     */
    public function handle(Payment $payment, string $reason, User $admin): Refund
    {
        $refund = DB::transaction(function () use ($payment, $reason, $admin): Refund {
            $locked = Payment::query()->with(['user', 'purchasable'])->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->canTransitionTo(PaymentStatus::Refunded)) {
                throw InvalidStateTransitionException::for($locked->status, PaymentStatus::Refunded, 'Mokėjimo');
            }

            $provider = ProviderProfile::withTrashed()->where('user_id', $locked->user_id)->lockForUpdate()->first();
            $subscription = $locked->subscription_id === null
                ? null
                : Subscription::query()->with('plan')->whereKey($locked->subscription_id)->lockForUpdate()->first();

            // Skaičiuojam tik dabar – su užrakintomis, šviežiomis eilutėmis (peržiūra lange galėjo pasenti)
            $calculation = $this->calculator->calculate($locked, $provider, $subscription);
            $creditNoteNumber = $this->numbers->next(now(), InvoiceSeries::CreditNote);

            if ($provider !== null && $calculation->creditsReversed > 0) {
                $this->ledger->debit($provider, $calculation->creditsReversed, CreditTransactionType::PaymentRefund, $locked, __('refunds.ledger', [
                    'invoice' => $locked->invoice_number ?? $locked->uuid,
                    'credit_note' => $creditNoteNumber,
                ]));
            }

            if ($subscription !== null) {
                $this->shortenSubscription($subscription, $calculation);
            }

            $locked->forceFill(['status' => PaymentStatus::Refunded])->save();

            $refund = new Refund([
                'amount_cents' => $locked->amount_cents,
                'reason' => mb_substr(trim($reason), 0, 500),
                'credits_reversed' => $calculation->creditsReversed,
                'credits_shortfall' => $calculation->creditsShortfall,
                'credit_note_number' => $creditNoteNumber,
                // Ta pati „nuotrauka" kaip sąskaitoje faktūroje; seed'ų mokėjimams jos nėra – fiksuojam dabartinę
                'billing_details' => $locked->billing_details ?? $this->billingDetails->snapshot($locked->user),
            ]);
            $refund->payment()->associate($locked);
            $refund->refundedBy()->associate($admin);
            $refund->save();

            $payment->setRawAttributes($locked->getAttributes(), true);
            $refund->setRelation('payment', $locked);

            return $refund;
        });

        $this->notify($refund);

        return $refund;
    }

    /**
     * „Vienas mokėjimas = vienas laikotarpis": prenumerata sutrumpinama (ar baigiama), nebepratęsiama,
     * laukiantys pratęsimo mokėjimai atšaukiami.
     */
    private function shortenSubscription(Subscription $subscription, RefundCalculation $calculation): void
    {
        $subscription->forceFill([
            'status' => $calculation->subscriptionStatus,
            'ends_at' => $calculation->subscriptionEndsAt,
            'credits_granted_until' => $calculation->subscriptionCreditsGrantedUntil,
            'auto_renew' => false,
            'cancelled_at' => $subscription->cancelled_at ?? now(),
        ])->save();

        $this->cancelRenewals->handle($subscription);
    }

    /**
     * Po COMMIT (ne transakcijos viduje): laiškas apie neįvykusį grąžinimą būtų blogiau nei jokio.
     * „Ištrintam" (anonimizuotam) vartotojui nerašom – jo el. paštas jau netikras.
     */
    private function notify(Refund $refund): void
    {
        $user = $refund->payment->user;

        if (! $user->trashed()) {
            $user->notify(new PaymentRefunded($refund));
        }
    }
}
