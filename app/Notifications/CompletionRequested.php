<?php

namespace App\Notifications;

use App\Models\ServiceRequest;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Klientui: išrinktas teikėjas prašo pažymėti darbą atliktu (docs/STATES.md 1 sk. „Papildomos taisyklės" –
 * pažymėti gali tik klientas, teikėjas gali tik paprašyti). Grupė „request_updates".
 */
class CompletionRequested extends BaseNotification
{
    public function __construct(public ServiceRequest $serviceRequest) {}

    public function settingsGroup(): string
    {
        return 'request_updates';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $replace = $this->replacements();

        return $this->withSettingsHint($this->mailMessage(__('service_requests.completion.requested.subject', $replace))
            ->line(__('service_requests.completion.requested.intro', $replace))
            ->line(__('service_requests.completion.requested.outro'))
            ->action(__('service_requests.completion.requested.action'), route('service-requests.show', $this->serviceRequest)));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'service_request_id' => $this->serviceRequest->id,
            'message' => __('service_requests.completion.requested.message', $this->replacements()),
        ];
    }

    /**
     * @return array{title: string, provider: string}
     */
    private function replacements(): array
    {
        $provider = $this->serviceRequest->loadMissing('acceptedOffer.providerProfile')->acceptedOffer?->providerProfile;

        return ['title' => $this->serviceRequest->title, 'provider' => $provider->display_name ?? 'Teikėjas'];
    }
}
