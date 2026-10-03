<?php

namespace App\Actions\Offers;

use App\Enums\OfferDeclineReason;
use App\Enums\OfferStatus;
use App\Enums\ServiceRequestStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Offer;
use App\Models\ServiceRequest;
use App\Notifications\OfferDeclined;
use Illuminate\Support\Facades\DB;

/**
 * Klientas atmeta pasiūlymą: pending → declined, responded_at (docs/STATES.md 2 sk.).
 * Kreditai negrąžinami – tai įprasta konkurencija (STATES.md 3 sk.).
 */
class DeclineOffer
{
    public function handle(Offer $offer): Offer
    {
        DB::transaction(function () use ($offer): void {
            $request = ServiceRequest::query()->whereKey($offer->service_request_id)->lockForUpdate()->firstOrFail();
            $locked = Offer::query()->whereKey($offer->id)->lockForUpdate()->firstOrFail();

            if ($request->status !== ServiceRequestStatus::Open) {
                throw new InvalidStateTransitionException(__('offers.errors.request_closed_for_action'));
            }

            if (! $locked->status->canTransitionTo(OfferStatus::Declined)) {
                throw InvalidStateTransitionException::for($locked->status, OfferStatus::Declined, 'Pasiūlymo');
            }

            $now = now();

            $offer->forceFill([
                'status' => OfferStatus::Declined,
                'responded_at' => $now,
                'viewed_at' => $locked->viewed_at ?? $now,
            ])->save();
        });

        $offer->loadMissing('providerProfile.user')->providerProfile->user->notify(
            new OfferDeclined($offer, OfferDeclineReason::ClientDeclined),
        );

        return $offer;
    }
}
