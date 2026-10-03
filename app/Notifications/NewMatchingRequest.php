<?php

namespace App\Notifications;

use App\Models\ServiceRequest;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Teikėjui: jo srityje ir zonoje paskelbta nauja užklausa. Siunčia job'as NotifyMatchingProviders.
 */
class NewMatchingRequest extends BaseNotification
{
    public function __construct(public ServiceRequest $serviceRequest) {}

    public function settingsGroup(): string
    {
        return 'new_requests';
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Eilėje modelis atkuriamas iš DB be ryšių, todėl juos užkraunam aiškiai (ne lazy loading)
        $request = $this->serviceRequest->loadMissing(['category:id,name,offer_cost_credits', 'city:id,name']);

        return $this->withSettingsHint($this->mailMessage(__('notifications.new_matching_request.subject', ['title' => $request->title]))
            ->line(__('notifications.new_matching_request.intro', ['title' => $request->title]))
            ->line(__('notifications.new_matching_request.details', [
                'category' => $request->category->name,
                'city' => $request->city->name,
                'budget' => Money::range($request->budget_min_cents, $request->budget_max_cents),
            ]))
            ->action(__('notifications.new_matching_request.action'), route('service-requests.show', $request))
            ->line(__('notifications.new_matching_request.outro', ['credits' => $request->category->offer_cost_credits])));
    }

    /**
     * Įrašas varpeliui (notifications.data). Raktai – kaip seed'uose (NotificationGenerator).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'service_request_id' => $this->serviceRequest->id,
            'message' => __('notifications.new_matching_request.message', ['title' => $this->serviceRequest->title]),
        ];
    }
}
