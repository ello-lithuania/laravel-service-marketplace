<?php

namespace App\Notifications;

use App\Models\ServiceRequest;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Klientui: užklausa vykdoma jau ilgai (SendCompletionReminder, kasdienė komanda). Automatiškai jos neužbaigiam –
 * kad nebūtų kvietimų vertinti darbus, kurie galbūt neįvyko (docs/STATES.md 1 sk.). Grupė „request_updates".
 */
class CompletionReminder extends BaseNotification
{
    public function __construct(public ServiceRequest $serviceRequest, public int $days) {}

    public function settingsGroup(): string
    {
        return 'request_updates';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $replace = ['title' => $this->serviceRequest->title, 'days' => $this->days];

        return $this->withSettingsHint($this->mailMessage(__('service_requests.completion.reminder.subject', $replace))
            ->line(__('service_requests.completion.reminder.intro', $replace))
            ->line(__('service_requests.completion.reminder.outro'))
            ->action(__('service_requests.completion.reminder.action'), route('service-requests.show', $this->serviceRequest)));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'service_request_id' => $this->serviceRequest->id,
            'message' => __('service_requests.completion.reminder.message', [
                'title' => $this->serviceRequest->title,
                'days' => $this->days,
            ]),
        ];
    }
}
