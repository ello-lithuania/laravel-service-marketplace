<?php

namespace App\Notifications;

use App\Models\User;
use App\Services\Privacy\DataExportStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * BDAR archyvas paruoštas. Ne BaseNotification palikuonis: tai atsakymas į paties vartotojo prašymą, todėl
 * siunčiamas visada (nepriklausomai nuo pranešimų nustatymų), bet laiškas – tik patvirtintu el. pašto adresu.
 * Laiške nuoroda veda į nustatymų puslapį (reikia prisijungti), o ne tiesiai į failą: persiųstas ar
 * nutekėjęs laiškas neatskleidžia duomenų.
 */
class DataExportReady extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User && ! $notifiable->hasVerifiedEmail() ? ['database'] : ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('privacy.export.mail_subject'))
            ->greeting(__('notifications.greeting'))
            ->line(__('privacy.export.mail_intro'))
            ->line(__('privacy.export.mail_expiry', ['days' => DataExportStorage::RETENTION_DAYS]))
            ->action(__('privacy.export.mail_action'), route('privacy.edit'))
            ->line(__('privacy.export.mail_outro'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['message' => __('privacy.export.message')];
    }
}
