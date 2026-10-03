<?php

namespace App\Actions\ServiceRequests;

use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Notifications\CompletionRequested;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Teikėjas prašo klientą pažymėti darbą atliktu (docs/STATES.md 1 sk. „Papildomos taisyklės"): pats pažymėti
 * negali – tik klientas, todėl atsiliepimas visada atsiranda po kliento patvirtinimo.
 *
 * Kartoti – ne dažniau kaip kas ServiceRequest::COMPLETION_REQUEST_COOLDOWN_DAYS d. Riba saugoma DB stulpelyje
 * completion_requested_at, o ne cache (RateLimiter): ji turi išlikti išvalius cache, o puslapis rodo, kada paprašyta.
 */
class RequestCompletion
{
    public function handle(ServiceRequest $serviceRequest): ServiceRequest
    {
        DB::transaction(function () use ($serviceRequest): void {
            // Užraktas: du vienu metu paspausti mygtukai nenusiųs dviejų priminimų
            $locked = ServiceRequest::query()->whereKey($serviceRequest->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ServiceRequestStatus::InProgress || $locked->nextCompletionRequestAt() !== null) {
                throw ValidationException::withMessages(['completion' => __('service_requests.completion.too_soon', [
                    'days' => ServiceRequest::COMPLETION_REQUEST_COOLDOWN_DAYS,
                ])]);
            }

            $serviceRequest->forceFill(['completion_requested_at' => now()])->save();
        });

        $serviceRequest->loadMissing('client')->client->notify(new CompletionRequested($serviceRequest));

        return $serviceRequest;
    }
}
