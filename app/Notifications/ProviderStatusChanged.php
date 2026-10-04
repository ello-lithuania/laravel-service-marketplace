<?php

namespace App\Notifications;

use App\Enums\ProviderStatus;
use App\Models\ProviderProfile;
use App\Notifications\Concerns\IgnoresNotificationSettings;
use App\Support\NotificationTarget;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Etapas 9c: teikėjui – administratorius pakeitė jo profilio būseną (paslėpė, užblokavo, aktyvavo) arba
 * atblokavus paskyrą profilis atkurtas (active / pending). Siunčia ChangeProviderStatus ir UnbanUser.
 *
 * Neišjungiamas nustatymuose: tai žinia apie paties žmogaus paskyrą (ar jį mato klientai, ar jis gali dirbti),
 * kaip ComplaintResolved. Priežastis – neprivaloma, ją administratorius įrašo Filament veiksmo formoje.
 */
class ProviderStatusChanged extends BaseNotification
{
    use IgnoresNotificationSettings;

    public function __construct(
        public ProviderProfile $profile,
        public ProviderStatus $status,
        public ?string $reason = null,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        $key = 'moderation.provider_notifications.status.';
        $message = $this->mailMessage(__($key.$this->status->value.'.subject'))
            ->line(__($key.$this->status->value.'.intro', ['name' => $this->profile->display_name]));

        if ($this->reason !== null) {
            $message->line(__($key.'reason', ['reason' => $this->reason]));
        }

        $message->action(self::actionLabel($this->status), NotificationTarget::providerAccountUrl($this->profile));

        if (in_array($this->status, [ProviderStatus::Hidden, ProviderStatus::Suspended], true)) {
            $message->line(__($key.'outro'));
        }

        return $message;
    }

    /**
     * Varpeliui: trumpas tekstas ir ID, iš kurio NotificationTarget apskaičiuoja nuorodą.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $message = __('moderation.provider_notifications.status.'.$this->status->value.'.message');

        if ($this->reason !== null) {
            $message .= ' '.__('moderation.provider_notifications.status.reason', ['reason' => $this->reason]);
        }

        return [
            'provider_profile_id' => $this->profile->id,
            'status' => $this->status->value,
            'reason' => $this->reason,
            'message' => $message,
        ];
    }

    /**
     * Laiško mygtuko tekstas – pagal tai, kur veda NotificationTarget::providerAccountUrl().
     */
    public static function actionLabel(ProviderStatus $status): string
    {
        return __('moderation.provider_notifications.status.actions.'.match ($status) {
            ProviderStatus::Active => 'profile',
            ProviderStatus::Pending => 'wizard',
            default => 'dashboard',
        });
    }
}
