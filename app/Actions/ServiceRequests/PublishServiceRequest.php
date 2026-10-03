<?php

namespace App\Actions\ServiceRequests;

use App\Enums\ServiceRequestStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Jobs\NotifyMatchingProviders;
use App\Models\ServiceRequest;
use App\Notifications\ServiceRequestPublished;
use Illuminate\Support\Facades\DB;

/**
 * pending → open (docs/STATES.md 1 sk.): published_at, expires_at = +30 d., pranešimai tinkamiems teikėjams.
 * Kviečia automatinis moderavimas (CreateServiceRequest) arba administratorius (Filament).
 */
class PublishServiceRequest
{
    public const LIFETIME_DAYS = 30;

    /**
     * @param  bool  $byAdmin  true – patvirtino administratorius, todėl klientui siunčiamas pranešimas
     */
    public function handle(ServiceRequest $serviceRequest, bool $byAdmin = false): ServiceRequest
    {
        DB::transaction(function () use ($serviceRequest): void {
            // Užrakinam eilutę: du administratoriai, vienu metu spaudžiantys „Patvirtinti", nepaskelbs jos dukart
            $locked = ServiceRequest::query()->whereKey($serviceRequest->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->canTransitionTo(ServiceRequestStatus::Open)) {
                throw InvalidStateTransitionException::for($locked->status, ServiceRequestStatus::Open);
            }

            $serviceRequest->forceFill([
                'status' => ServiceRequestStatus::Open,
                'published_at' => now(),
                'expires_at' => now()->addDays(self::LIFETIME_DAYS),
            ])->save();

            // afterCommit – job'as į eilę patenka tik transakcijai pavykus (kitaip galėtų pranešti apie
            // užklausą, kurios paskelbimas buvo atšauktas)
            NotifyMatchingProviders::dispatch($serviceRequest)->afterCommit();
        });

        if ($byAdmin) {
            $serviceRequest->loadMissing('client')->client->notify(new ServiceRequestPublished($serviceRequest));
        }

        return $serviceRequest;
    }
}
