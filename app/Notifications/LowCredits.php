<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Teikėjui: kreditų balansas nukrito žemiau ribos (config payments.low_credits_threshold) – metas papildyti.
 * Siunčiama vieną kartą, kai balansas PEREINA ribą (CreditTransactionObserver), o ne po kiekvieno pasiūlymo.
 */
class LowCredits extends BaseNotification
{
    public function __construct(public int $balance) {}

    public function settingsGroup(): string
    {
        return 'billing';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->mailMessage(__('billing.notifications.low_credits.subject'))
            ->line(__('billing.notifications.low_credits.intro', ['balance' => $this->balance]))
            ->action(__('billing.notifications.low_credits.action'), route('credits.index'));

        return $this->withSettingsHint($message);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'balance' => $this->balance,
            'message' => __('billing.notifications.low_credits.message', ['balance' => $this->balance]),
        ];
    }
}
