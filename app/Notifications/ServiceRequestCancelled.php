<?php

namespace App\Notifications;

use App\Models\ServiceRequest;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Išrinktam teikėjui: klientas (ar admin) atšaukė jau vykdomą užklausą. Kreditai negrąžinami (STATES.md 3 sk.).
 */
class ServiceRequestCancelled extends BaseNotification
{
    public function __construct(public ServiceRequest $serviceRequest) {}

    public function settingsGroup(): string
    {
        return 'offer_updates';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->serviceRequest;
        $message = $this->mailMessage(__('notifications.service_request_cancelled.subject', ['title' => $request->title]))
            ->line(__('notifications.service_request_cancelled.intro', ['title' => $request->title]));

        if ($request->cancellation_reason !== null) {
            $message->line(__('notifications.service_request_cancelled.reason', ['reason' => $request->cancellation_reason]));
        }

        return $this->withSettingsHint($message->action(
            __('notifications.service_request_cancelled.action'),
            route('service-requests.show', $request),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'service_request_id' => $this->serviceRequest->id,
            'offer_id' => $this->serviceRequest->accepted_offer_id,
            'message' => __('notifications.service_request_cancelled.message', ['title' => $this->serviceRequest->title]),
        ];
    }
}
