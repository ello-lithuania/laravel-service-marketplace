<?php

namespace App\Notifications;

use App\Enums\SubscriptionStatus;
use App\Models\Refund;
use App\Models\SubscriptionPlan;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Teikėjui: administratorius grąžino mokėjimą – kiek pinigų, kodėl, kiek kreditų atimta, kas nutiko prenumeratai,
 * ir nuoroda į kreditinę sąskaitą faktūrą (Etapas 9). Siunčiama po sėkmingos DB transakcijos (RefundPayment).
 * Nustatymų grupė „billing" – kaip PaymentSucceeded.
 */
class PaymentRefunded extends BaseNotification
{
    public function __construct(public Refund $refund) {}

    public function settingsGroup(): string
    {
        return 'billing';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $refund = $this->refund->loadMissing(['payment.purchasable', 'payment.subscription']);
        $payment = $refund->payment;

        $message = $this->mailMessage(__('refunds.notifications.payment_refunded.subject', ['number' => $refund->credit_note_number]))
            ->line(__('refunds.notifications.payment_refunded.intro', [
                'amount' => Money::format($refund->amount_cents),
                'item' => $payment->description(),
            ]))
            ->line(__('refunds.notifications.payment_refunded.reason', ['reason' => $refund->reason]))
            ->line(__('refunds.notifications.payment_refunded.money', ['method' => $payment->gateway->label()]));

        foreach ($this->consequences() as $line) {
            $message->line($line);
        }

        $message->line(__('refunds.notifications.payment_refunded.credit_note', ['number' => $refund->credit_note_number]))
            ->action(__('refunds.notifications.payment_refunded.action'), route('payments.credit-note', $payment));

        return $this->withSettingsHint($message);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $refund = $this->refund->loadMissing('payment.purchasable');

        return [
            'payment_id' => $refund->payment_id,
            'payment_uuid' => $refund->payment->uuid,
            'refund_id' => $refund->id,
            'credit_note_number' => $refund->credit_note_number,
            'credits_reversed' => $refund->credits_reversed,
            'message' => __('refunds.notifications.payment_refunded.message', [
                'amount' => Money::format($refund->amount_cents),
                'item' => $refund->payment->description(),
            ]),
        ];
    }

    /**
     * Kreditų ir prenumeratos pasekmės – tik tos eilutės, kurios šiam grąžinimui aktualios.
     *
     * @return list<string>
     */
    private function consequences(): array
    {
        $refund = $this->refund;
        $lines = [];

        if ($refund->credits_shortfall > 0) {
            $lines[] = __('refunds.notifications.payment_refunded.credits_shortfall', [
                'credits' => $refund->credits_reversed,
                'shortfall' => $refund->credits_shortfall,
            ]);
        } elseif ($refund->credits_reversed > 0) {
            $lines[] = __('refunds.notifications.payment_refunded.credits', ['credits' => $refund->credits_reversed]);
        }

        $subscription = $refund->payment->subscription;
        $plan = $refund->payment->purchasable;

        if ($subscription !== null && $plan instanceof SubscriptionPlan) {
            $lines[] = $subscription->status === SubscriptionStatus::Expired
                ? __('refunds.notifications.payment_refunded.subscription_ended', ['plan' => $plan->name])
                : __('refunds.notifications.payment_refunded.subscription_shortened', [
                    'plan' => $plan->name,
                    'date' => $subscription->ends_at->timezone('Europe/Vilnius')->format('Y-m-d'),
                ]);
        }

        return $lines;
    }
}
