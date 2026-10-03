<?php

namespace App\Actions\ServiceRequests;

use App\Enums\OfferDeclineReason;
use App\Enums\OfferStatus;
use App\Enums\ServiceRequestStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Offer;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\OfferDeclined;
use App\Notifications\ServiceRequestCancelled;
use App\Notifications\ServiceRequestRejected;
use App\Services\Credits\CreditLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * → cancelled (docs/STATES.md 1 sk.). Atšaukti gali klientas arba administratorius:
 *
 * - pending: tiesiog atšaukiama (administratoriui tai – „atmesti" su priežastimi);
 * - open: visi pending pasiūlymai → declined, kreditai už juos GRĄŽINAMI (STATES.md 3 sk.);
 * - in_progress: tik su priežastimi; priimtas pasiūlymas lieka accepted, kreditai NEGRĄŽINAMI.
 */
class CancelServiceRequest
{
    public function __construct(private readonly CreditLedger $ledger) {}

    public function handle(ServiceRequest $serviceRequest, User $actor, ?string $reason = null): ServiceRequest
    {
        $reason = filled($reason) ? trim((string) $reason) : null;

        /** @var list<array{offer: Offer, refunded: int}> $declined */
        $declined = [];
        $previous = $serviceRequest->status;

        DB::transaction(function () use ($serviceRequest, $reason, &$declined, &$previous): void {
            // Užraktų tvarka visada ta pati: pirma užklausa, paskui teikėjai (CreditLedger) – taip išvengiam deadlock'ų
            $locked = ServiceRequest::query()->whereKey($serviceRequest->id)->lockForUpdate()->firstOrFail();
            $previous = $locked->status;

            if (! $previous->canTransitionTo(ServiceRequestStatus::Cancelled)) {
                throw InvalidStateTransitionException::for($previous, ServiceRequestStatus::Cancelled);
            }

            if ($previous === ServiceRequestStatus::InProgress && $reason === null) {
                throw ValidationException::withMessages(['reason' => __('service_requests.cancel.reason_required')]);
            }

            if ($previous === ServiceRequestStatus::Open) {
                $declined = $this->declinePendingOffers($locked);
            }

            $serviceRequest->forceFill([
                'status' => ServiceRequestStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ])->save();
        });

        $this->notify($serviceRequest, $actor, $previous, $declined);

        return $serviceRequest;
    }

    /**
     * Pending pasiūlymai → declined + kreditų grąžinimas už kiekvieną.
     *
     * @return list<array{offer: Offer, refunded: int}>
     */
    private function declinePendingOffers(ServiceRequest $serviceRequest): array
    {
        $offers = $serviceRequest->offers()
            ->where('status', OfferStatus::Pending)
            ->with('providerProfile.user')
            // Teikėjų tvarka pastovi (pagal ID), kad lygiagrečios operacijos rakintų eilutes ta pačia tvarka
            ->orderBy('provider_profile_id')
            ->lockForUpdate()
            ->get();

        $result = [];

        foreach ($offers as $offer) {
            $offer->forceFill(['status' => OfferStatus::Declined])->save();
            $refund = $this->ledger->refund($offer, __('service_requests.refund.cancelled', ['title' => $serviceRequest->title]));
            $result[] = ['offer' => $offer, 'refunded' => $refund->amount ?? 0];
        }

        return $result;
    }

    /**
     * Pranešimai siunčiami PO transakcijos: jei ji nepavyktų, žmonės negautų žinios apie neįvykusį atšaukimą.
     *
     * @param  list<array{offer: Offer, refunded: int}>  $declined
     */
    private function notify(ServiceRequest $serviceRequest, User $actor, ServiceRequestStatus $previous, array $declined): void
    {
        foreach ($declined as ['offer' => $offer, 'refunded' => $refunded]) {
            $offer->providerProfile->user->notify(new OfferDeclined($offer, OfferDeclineReason::RequestCancelled, $refunded));
        }

        if ($previous === ServiceRequestStatus::InProgress) {
            $serviceRequest->load('acceptedOffer.providerProfile.user');
            $serviceRequest->acceptedOffer?->providerProfile->user->notify(new ServiceRequestCancelled($serviceRequest));
        }

        // Administratoriaus atmetimas ar atšaukimas – klientas turi sužinoti priežastį
        if ($actor->isAdmin()) {
            $serviceRequest->loadMissing('client')->client->notify(new ServiceRequestRejected($serviceRequest));
        }
    }
}
