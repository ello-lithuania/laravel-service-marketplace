<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Teikėjui: mokėjimas gautas, kreditai ar prenumerata suteikti, sąskaita faktūra paruošta (Etapas 7).
 * Siunčiama tik po sėkmingos DB transakcijos (ProcessPaymentResult).
 */
class PaymentSucceeded extends BaseNotification
{
    public function __construct(public Payment $payment) {}

    public function settingsGroup(): string
    {
        return 'billing';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->payment->loadMissing('purchasable');

        $message = $this->mailMessage(__('billing.notifications.payment_succeeded.subject', ['number' => $this->payment->invoice_number]))
            ->line(__('billing.notifications.payment_succeeded.intro', [
                'amount' => Money::format($this->payment->amount_cents),
                'item' => $this->payment->description(),
            ]))
            ->line(__('billing.notifications.payment_succeeded.invoice', ['number' => $this->payment->invoice_number]))
            ->action(__('billing.notifications.payment_succeeded.action'), route('payments.invoice', $this->payment));

        return $this->withSettingsHint($message);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->payment->loadMissing('purchasable');

        return [
            'payment_id' => $this->payment->id,
            'payment_uuid' => $this->payment->uuid,
            'message' => __('billing.notifications.payment_succeeded.message', [
                'amount' => Money::format($this->payment->amount_cents),
                'item' => $this->payment->description(),
            ]),
        ];
    }
}
