<?php

namespace App\Actions\ServiceRequests;

use App\Enums\ServiceRequestStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\ProviderProfile;
use App\Models\ServiceRequest;
use App\Notifications\ReviewInvitation;
use Illuminate\Support\Facades\DB;

/**
 * in_progress → completed (docs/STATES.md 1 sk.). Pažymėti „Darbas atliktas" gali tik klientas (tikrina Policy).
 * Teikėjo completed_jobs_count + 1 – denormalizuotas skaitliukas (docs/DB_SCHEMA.md 2.10).
 * Po užbaigimo klientas gauna kvietimą palikti atsiliepimą (ReviewInvitation, Etapas 6).
 */
class CompleteServiceRequest
{
    public function handle(ServiceRequest $serviceRequest): ServiceRequest
    {
        DB::transaction(function () use ($serviceRequest): void {
            $locked = ServiceRequest::query()->whereKey($serviceRequest->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->canTransitionTo(ServiceRequestStatus::Completed)) {
                throw InvalidStateTransitionException::for($locked->status, ServiceRequestStatus::Completed);
            }

            $serviceRequest->forceFill([
                'status' => ServiceRequestStatus::Completed,
                'completed_at' => now(),
            ])->save();

            // increment() – vienas atominis UPDATE … SET x = x + 1, todėl užrakto nereikia
            ProviderProfile::withTrashed()
                ->whereIn('id', $locked->acceptedOffer()->select('provider_profile_id'))
                ->increment('completed_jobs_count');
        });

        // --- Etapas 6: kvietimas palikti atsiliepimą (po transakcijos – tik jei užbaigimas tikrai įvyko) ---
        $serviceRequest->loadMissing('client')->client->notify(new ReviewInvitation($serviceRequest));

        return $serviceRequest;
    }
}
