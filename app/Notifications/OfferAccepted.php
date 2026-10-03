<?php

namespace App\Notifications;

use App\Models\Offer;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Teikėjui: klientas priėmė jo pasiūlymą. Kontaktai ir adresas – užklausos puslapyje (tik prisijungus).
 */
class OfferAccepted extends BaseNotification
{
    public function __construct(public Offer $offer) {}

    public function settingsGroup(): string
    {
        return 'offer_updates';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->offer->loadMissing('serviceRequest:id,slug,title')->serviceRequest;

        return $this->withSettingsHint($this->mailMessage(__('notifications.offer_accepted.subject', ['title' => $request->title]))
            ->line(__('notifications.offer_accepted.intro', ['title' => $request->title]))
            ->line(__('notifications.offer_accepted.contacts'))
            ->action(__('notifications.offer_accepted.action'), route('service-requests.show', $request)));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $request = $this->offer->loadMissing('serviceRequest:id,slug,title')->serviceRequest;

        return [
            'offer_id' => $this->offer->id,
            'service_request_id' => $request->id,
            'message' => __('notifications.offer_accepted.message', ['title' => $request->title]),
        ];
    }
}
