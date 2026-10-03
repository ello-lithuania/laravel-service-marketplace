<?php

namespace App\Notifications;

use App\Models\ServiceRequest;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Klientui: administratorius patvirtino jo užklausą (po rankinio moderavimo).
 * Automatiškai paskelbus nesiunčiama – klientas tai mato iškart po formos išsiuntimo.
 */
class ServiceRequestPublished extends BaseNotification
{
    public function __construct(public ServiceRequest $serviceRequest) {}

    public function settingsGroup(): string
    {
        return 'request_updates';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $title = $this->serviceRequest->title;

        return $this->withSettingsHint($this->mailMessage(__('notifications.service_request_published.subject', ['title' => $title]))
            ->line(__('notifications.service_request_published.intro', ['title' => $title]))
            ->action(__('notifications.service_request_published.action'), route('service-requests.show', $this->serviceRequest)));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'service_request_id' => $this->serviceRequest->id,
            'message' => __('notifications.service_request_published.message', ['title' => $this->serviceRequest->title]),
        ];
    }
}
