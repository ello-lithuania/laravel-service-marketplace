<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Bendra platformos pranešimų dalis: kanalai pagal vartotojo nustatymus ir laiško pradžia.
 *
 * ShouldQueue – laiškas siunčiamas eilėje (job'e), todėl vartotojas nelaukia, kol SMTP serveris atsakys.
 * Kanalai: mail (el. laiškas) ir database (varpelis, lentelė notifications).
 * https://laravel.com/docs/13.x/notifications#queueing-notifications
 */
abstract class BaseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Kuriai users.notification_settings grupei priklauso pranešimas (docs/DB_SCHEMA.md → users).
     */
    abstract public function settingsGroup(): string;

    /**
     * Laravel kviečia via() prieš siųsdamas: grąžinti kanalai = kur pranešimas keliaus.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['mail'];
        }

        $channels = NotificationSettings::for($notifiable)->channels($this->settingsGroup());

        // Nepatvirtintu el. pašto adresu laiškų nesiunčiam – gal jis net ne šio žmogaus
        if (! $notifiable->hasVerifiedEmail()) {
            $channels = array_values(array_diff($channels, ['mail']));
        }

        return $channels;
    }

    protected function mailMessage(string $subject): MailMessage
    {
        return (new MailMessage)
            ->subject($subject)
            ->greeting(__('notifications.greeting'));
    }

    /**
     * Paskutinė laiško eilutė – kur išjungti tokius laiškus.
     */
    protected function withSettingsHint(MailMessage $message): MailMessage
    {
        return $message->line(__('notifications.settings_hint'));
    }
}
