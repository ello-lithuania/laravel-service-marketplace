<?php

namespace App\Actions\ServiceRequests;

use App\Enums\ServiceRequestStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\ProviderProfile;
use App\Models\ServiceRequest;
use Illuminate\Support\Facades\DB;

/**
 * in_progress → completed (docs/STATES.md 1 sk.). Pažymėti „Darbas atliktas" gali tik klientas (tikrina Policy).
 * Teikėjo completed_jobs_count + 1 – denormalizuotas skaitliukas (docs/DB_SCHEMA.md 2.10).
 * Kvietimas palikti atsiliepimą – Etape 6 (atsiliepimai).
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

        return $serviceRequest;
    }
}
