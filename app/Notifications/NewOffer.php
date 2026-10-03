<?php

namespace App\Notifications;

use App\Models\Offer;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Klientui: teikėjas atsiuntė pasiūlymą jo užklausai.
 */
class NewOffer extends BaseNotification
{
    public function __construct(public Offer $offer) {}

    public function settingsGroup(): string
    {
        return 'new_offers';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $offer = $this->loadOffer();
        $replace = ['provider' => $offer->providerProfile->display_name, 'title' => $offer->serviceRequest->title];

        $message = $this->mailMessage(__('notifications.new_offer.subject', $replace))
            ->line(__('notifications.new_offer.intro', $replace));

        if ($offer->price_cents !== null) {
            $message->line(__('notifications.new_offer.price', [
                'price' => Money::format($offer->price_cents).' ('.mb_strtolower($offer->price_type->label()).')',
            ]));
        }

        return $this->withSettingsHint($message->action(
            __('notifications.new_offer.action'),
            route('offers.show', [$offer->serviceRequest, $offer]),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $offer = $this->loadOffer();

        return [
            'offer_id' => $offer->id,
            'service_request_id' => $offer->service_request_id,
            'message' => __('notifications.new_offer.message', [
                'provider' => $offer->providerProfile->display_name,
                'title' => $offer->serviceRequest->title,
            ]),
        ];
    }

    private function loadOffer(): Offer
    {
        return $this->offer->loadMissing(['providerProfile', 'serviceRequest']);
    }
}
