<?php

namespace App\Actions\Offers;

use App\Enums\OfferStatus;
use App\Enums\ServiceRequestStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Offer;
use App\Models\ServiceRequest;
use Illuminate\Support\Facades\DB;

/**
 * Teikėjas atšaukia savo pasiūlymą: pending → withdrawn (docs/STATES.md 2 sk.).
 * offers_count − 1; kreditai NEGRĄŽINAMI – kitaip pasiūlymus būtų galima siųsti ir atšaukti nemokamai.
 */
class WithdrawOffer
{
    public function handle(Offer $offer): Offer
    {
        DB::transaction(function () use ($offer): void {
            $request = ServiceRequest::query()->whereKey($offer->service_request_id)->lockForUpdate()->firstOrFail();
            $locked = Offer::query()->whereKey($offer->id)->lockForUpdate()->firstOrFail();

            if ($request->status !== ServiceRequestStatus::Open) {
                throw new InvalidStateTransitionException(__('offers.errors.request_closed_for_action'));
            }

            if (! $locked->status->canTransitionTo(OfferStatus::Withdrawn)) {
                throw InvalidStateTransitionException::for($locked->status, OfferStatus::Withdrawn, 'Pasiūlymo');
            }

            $offer->forceFill(['status' => OfferStatus::Withdrawn])->save();
            $request->decrement('offers_count');
        });

        return $offer;
    }
}
