<?php

namespace App\Actions\ServiceRequests;

use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Notifications\CompletionReminder;
use Illuminate\Support\Facades\DB;

/**
 * Vieną kartą primena klientui, kad užklausa vykdoma jau ServiceRequest::COMPLETION_REMINDER_AFTER_DAYS d.
 * (docs/STATES.md 1 sk.). Automatiškai neužbaigiam. completion_reminded_at – kad rytoj nesiųstume vėl.
 */
class SendCompletionReminder
{
    /**
     * @return bool ar priminimas išsiųstas
     */
    public function handle(ServiceRequest $serviceRequest): bool
    {
        $sent = DB::transaction(function () use ($serviceRequest): bool {
            $locked = ServiceRequest::query()->whereKey($serviceRequest->id)->lockForUpdate()->firstOrFail();

            // Kol laukė eilėje, klientas galėjo užbaigti ar atšaukti, o kitas paleidimas – jau priminti
            if ($locked->status !== ServiceRequestStatus::InProgress || $locked->completion_reminded_at !== null) {
                return false;
            }

            $serviceRequest->forceFill(['completion_reminded_at' => now()])->save();

            return true;
        });

        if ($sent) {
            $acceptedAt = $serviceRequest->loadMissing('acceptedOffer')->acceptedOffer?->responded_at;
            $days = $acceptedAt === null ? ServiceRequest::COMPLETION_REMINDER_AFTER_DAYS : (int) $acceptedAt->diffInDays(now());

            $serviceRequest->loadMissing('client')->client->notify(new CompletionReminder($serviceRequest, $days));
        }

        return $sent;
    }
}
