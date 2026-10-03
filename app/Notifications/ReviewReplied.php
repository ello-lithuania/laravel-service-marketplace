<?php

namespace App\Notifications;

use App\Models\Review;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/**
 * Atsiliepimo autoriui: teikėjas viešai atsakė į jo atsiliepimą. Veda į viešą teikėjo profilį.
 */
class ReviewReplied extends BaseNotification
{
    public function __construct(public Review $review) {}

    public function settingsGroup(): string
    {
        return 'reviews';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $provider = $this->review->loadMissing('providerProfile')->providerProfile;
        $replace = ['provider' => $provider->display_name];

        return $this->withSettingsHint($this->mailMessage(__('reviews.notifications.replied.subject', $replace))
            ->line(__('reviews.notifications.replied.intro', $replace))
            ->line('„'.Str::limit((string) $this->review->provider_reply, 300).'"')
            ->action(__('reviews.notifications.replied.action'), route('providers.show', $provider).'#atsiliepimai'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'review_id' => $this->review->id,
            'message' => __('reviews.notifications.replied.message', [
                'provider' => $this->review->loadMissing('providerProfile')->providerProfile->display_name,
            ]),
        ];
    }
}
