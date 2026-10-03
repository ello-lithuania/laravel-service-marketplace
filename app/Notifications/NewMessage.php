<?php

namespace App\Notifications;

use App\Models\Message;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Gavėjui: nauja žinutė pokalbyje (grupė „messages").
 *
 * Kaip išvengiam „šlamšto" (kiekviena žinutė – laiškas būtų per daug):
 *  1. Pranešimas siunčiamas tik pirmai neperskaitytai žinutei – jei gavėjas dar neperskaitė ankstesnės,
 *     apie šį pokalbį jam jau pranešta (sprendžia SendMessage). Perskaitęs gaus pranešimą apie kitą.
 *  2. Varpelis (database) – iš karto, o laiškas (mail) – po MAIL_DELAY_MINUTES (withDelay()).
 *  3. Prieš siunčiant laišką shouldSend() dar kartą patikrina, ar žinutė vis dar neperskaityta: jei gavėjas
 *     tuo metu buvo svetainėje ir ją perskaitė, laiškas nebesiunčiamas.
 *
 * type ir data – kaip seed'uose (NotificationGenerator): {conversation_id, message_id, message}.
 */
class NewMessage extends BaseNotification
{
    public const MAIL_DELAY_MINUTES = 5;

    /** Jei kol laukė eilėje žinutė buvo paslėpta (soft delete) – job'ą tiesiog išmetam, o ne kartojam. */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public Message $message) {}

    public function settingsGroup(): string
    {
        return 'messages';
    }

    /**
     * Kiekvienam kanalui – savas uždelsimas. Laravel jį pritaiko eilės job'ui.
     * https://laravel.com/docs/13.x/notifications#delaying-notifications
     */
    public function withDelay(object $notifiable, string $channel): ?DateTimeInterface
    {
        return $channel === 'mail' ? now()->addMinutes(self::MAIL_DELAY_MINUTES) : null;
    }

    /**
     * Laravel kviečia prieš pat siųsdamas (eilės job'e, jau po uždelsimo).
     * https://laravel.com/docs/13.x/notifications#determining-if-the-queued-notification-should-be-sent
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        if ($channel !== 'mail' || ! $notifiable instanceof User) {
            return true;
        }

        $lastRead = DB::table('conversation_user')
            ->where('conversation_id', $this->message->conversation_id)
            ->where('user_id', $notifiable->id)
            ->value('last_read_message_id');

        return ! is_numeric($lastRead) || (int) $lastRead < $this->message->id;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $replace = ['sender' => $this->senderName()];
        $message = $this->mailMessage(__('messages.notification.subject', $replace))
            ->line(__('messages.notification.intro', $replace));

        if ($this->message->body !== '') {
            $message->line('„'.Str::limit($this->message->body, 300).'"');
        }

        return $this->withSettingsHint($message->action(
            __('messages.notification.action'),
            route('conversations.show', $this->message->conversation_id),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'conversation_id' => $this->message->conversation_id,
            'message_id' => $this->message->id,
            'message' => __('messages.notification.message', ['sender' => $this->senderName()]),
        ];
    }

    /**
     * Teikėjas – profilio pavadinimu, klientas – „Vardas P.". Eilėje modelis atkuriamas be ryšių,
     * todėl juos užkraunam patys (loadMissing – ne lazy loading).
     */
    private function senderName(): string
    {
        $offer = $this->message->loadMissing(['conversation.offer.providerProfile', 'sender'])->conversation->offer;

        if ($offer !== null && $this->message->sender_id === $offer->providerProfile->user_id) {
            return $offer->providerProfile->display_name;
        }

        return $this->message->sender->public_name ?? __('messages.system_sender');
    }
}
