<?php

namespace App\Notifications;

use App\Models\Review;
use App\Models\ServiceRequest;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Klientui: darbas pažymėtas atliktu – pakvietimas palikti patvirtintą atsiliepimą (docs/STATES.md 1 sk.
 * in_progress → completed). Paspaudus veda į užklausos puslapį, kur yra atsiliepimo forma.
 */
class ReviewInvitation extends BaseNotification
{
    public function __construct(public ServiceRequest $serviceRequest) {}

    public function settingsGroup(): string
    {
        return 'reviews';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $replace = $this->replacements();

        return $this->withSettingsHint($this->mailMessage(__('reviews.notifications.invitation.subject', $replace))
            ->line(__('reviews.notifications.invitation.intro', $replace))
            ->line(__('reviews.notifications.invitation.deadline', ['days' => Review::VERIFIED_WINDOW_DAYS]))
            ->action(
                __('reviews.notifications.invitation.action'),
                route('service-requests.show', $this->serviceRequest).'#atsiliepimas',
            ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'service_request_id' => $this->serviceRequest->id,
            'message' => __('reviews.notifications.invitation.message', $this->replacements()),
        ];
    }

    /**
     * @return array{title: string, provider: string}
     */
    private function replacements(): array
    {
        $provider = $this->serviceRequest->loadMissing('acceptedOffer.providerProfile')->acceptedOffer?->providerProfile;

        return ['title' => $this->serviceRequest->title, 'provider' => $provider->display_name ?? 'teikėjui'];
    }
}
