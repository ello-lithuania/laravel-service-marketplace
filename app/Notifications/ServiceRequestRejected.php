<?php

namespace App\Notifications;

use App\Models\ServiceRequest;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Klientui: administratorius atmetė (pending) arba atšaukė (open / in_progress) jo užklausą su priežastimi.
 */
class ServiceRequestRejected extends BaseNotification
{
    public function __construct(public ServiceRequest $serviceRequest) {}

    public function settingsGroup(): string
    {
        return 'request_updates';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->serviceRequest;

        return $this->withSettingsHint($this->mailMessage(__('notifications.service_request_rejected.subject', ['title' => $request->title]))
            ->line(__('notifications.service_request_rejected.intro', ['title' => $request->title]))
            ->line(__('notifications.service_request_rejected.reason', ['reason' => (string) $request->cancellation_reason]))
            ->line(__('notifications.service_request_rejected.outro'))
            ->action(__('notifications.service_request_rejected.action'), route('service-requests.create')));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'service_request_id' => $this->serviceRequest->id,
            'message' => __('notifications.service_request_rejected.message', [
                'title' => $this->serviceRequest->title,
                'reason' => (string) $this->serviceRequest->cancellation_reason,
            ]),
        ];
    }
}
