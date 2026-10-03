<?php

namespace App\Notifications;

use App\Enums\OfferDeclineReason;
use App\Models\Offer;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Teikėjui: jo pasiūlymas atmestas – klientas atmetė, pasirinko kitą, užklausa atšaukta ar pasibaigė.
 * Jei kreditai grąžinti (docs/STATES.md 3 sk.), tai pasakoma pranešime.
 */
class OfferDeclined extends BaseNotification
{
    public function __construct(
        public Offer $offer,
        public OfferDeclineReason $reason,
        public int $creditsRefunded = 0,
    ) {}

    public function settingsGroup(): string
    {
        return 'offer_updates';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->offer->loadMissing('serviceRequest')->serviceRequest;

        return $this->withSettingsHint($this->mailMessage(__('notifications.offer_declined.subject', ['title' => $request->title]))
            ->line($this->text($request->title))
            ->action(__('notifications.offer_declined.action'), route('service-requests.show', $request)));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $request = $this->offer->loadMissing('serviceRequest')->serviceRequest;

        return [
            'offer_id' => $this->offer->id,
            'service_request_id' => $request->id,
            'reason' => $this->reason->value,
            'credits_refunded' => $this->creditsRefunded,
            'message' => $this->text($request->title),
        ];
    }

    private function text(string $title): string
    {
        $text = __('notifications.offer_declined.'.$this->reason->value, ['title' => $title]).'.';

        if ($this->creditsRefunded > 0) {
            $text .= ' '.__('notifications.offer_declined.refunded', ['credits' => $this->creditsRefunded]);
        }

        return $text;
    }
}
