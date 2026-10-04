<?php

namespace App\Notifications;

use App\Models\ProviderProfile;
use App\Notifications\Concerns\IgnoresNotificationSettings;
use App\Support\NotificationTarget;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Etapas 9c: teikėjui – administratorius pažymėjo profilį „Patikrintas" (SetProviderVerification).
 * Neišjungiamas, kaip ir ProviderStatusChanged: žinia apie paties žmogaus paskyrą.
 */
class ProviderVerified extends BaseNotification
{
    use IgnoresNotificationSettings;

    public function __construct(public ProviderProfile $profile) {}

    public function toMail(object $notifiable): MailMessage
    {
        return $this->mailMessage(__('moderation.provider_notifications.verified.subject'))
            ->line(__('moderation.provider_notifications.verified.intro', ['name' => $this->profile->display_name]))
            ->action(
                ProviderStatusChanged::actionLabel($this->profile->status),
                NotificationTarget::providerAccountUrl($this->profile),
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'provider_profile_id' => $this->profile->id,
            'message' => __('moderation.provider_notifications.verified.message'),
        ];
    }
}
