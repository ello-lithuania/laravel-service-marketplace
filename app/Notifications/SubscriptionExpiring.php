<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Models\Subscription;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Teikėjui: prenumerata baigiasi – apmokėkite pratęsimą (nuoroda į laukiantį mokėjimą).
 * pastDue = true – laikotarpis jau baigėsi, liko malonės laikotarpis (Etapas 7, subscriptions:renew).
 */
class SubscriptionExpiring extends BaseNotification
{
    public function __construct(
        public Subscription $subscription,
        public Payment $payment,
        public bool $pastDue = false,
    ) {}

    public function settingsGroup(): string
    {
        return 'billing';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $key = $this->pastDue ? 'past_due' : 'expiring';
        $replace = $this->replacements();

        $message = $this->mailMessage(__("billing.notifications.subscription_{$key}.subject", $replace))
            ->line(__("billing.notifications.subscription_{$key}.intro", $replace))
            ->line(__('billing.notifications.subscription_expiring.amount', $replace))
            ->action(__('billing.notifications.subscription_expiring.action'), route('payments.show', $this->payment))
            ->line(__('billing.notifications.subscription_expiring.outro'));

        return $this->withSettingsHint($message);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $key = $this->pastDue ? 'past_due' : 'expiring';

        return [
            'subscription_id' => $this->subscription->id,
            'payment_uuid' => $this->payment->uuid,
            'message' => __("billing.notifications.subscription_{$key}.message", $this->replacements()),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function replacements(): array
    {
        $this->subscription->loadMissing('plan');
        $graceDays = (int) config('payments.subscriptions.grace_days');

        return [
            'plan' => $this->subscription->plan->name,
            'date' => $this->subscription->ends_at->timezone('Europe/Vilnius')->format('Y-m-d'),
            'grace_date' => $this->subscription->ends_at->addDays($graceDays)->timezone('Europe/Vilnius')->format('Y-m-d'),
            'amount' => Money::format($this->payment->amount_cents),
        ];
    }
}
