<?php

namespace App\Actions\Offers;

use App\Enums\OfferDeclineReason;
use App\Enums\OfferStatus;
use App\Enums\ServiceRequestStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Offer;
use App\Models\ServiceRequest;
use App\Notifications\OfferAccepted;
use App\Notifications\OfferDeclined;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Klientas priima pasiūlymą (docs/STATES.md 1–2 sk.):
 *  - užklausa open → in_progress, accepted_offer_id;
 *  - šis pasiūlymas pending → accepted (responded_at);
 *  - kiti pending pasiūlymai → declined (sistema, todėl be responded_at) ir jų teikėjams pranešama;
 *  - išrinktam teikėjui atskleidžiamas adresas ir telefonas (tai sprendžia užklausos puslapio controller'is).
 * Kreditai už atmestus pasiūlymus negrąžinami – tai įprasta konkurencija (STATES.md 3 sk.).
 */
class AcceptOffer
{
    public function handle(Offer $offer): Offer
    {
        /** @var Collection<int, Offer> $others */
        $others = new Collection;

        DB::transaction(function () use ($offer, &$others): void {
            $request = ServiceRequest::query()->whereKey($offer->service_request_id)->lockForUpdate()->firstOrFail();
            $locked = Offer::query()->whereKey($offer->id)->lockForUpdate()->firstOrFail();

            if (! $request->status->canTransitionTo(ServiceRequestStatus::InProgress)) {
                throw InvalidStateTransitionException::for($request->status, ServiceRequestStatus::InProgress);
            }

            if (! $locked->status->canTransitionTo(OfferStatus::Accepted)) {
                throw InvalidStateTransitionException::for($locked->status, OfferStatus::Accepted, 'Pasiūlymo');
            }

            $now = now();

            $offer->forceFill([
                'status' => OfferStatus::Accepted,
                'responded_at' => $now,
                'viewed_at' => $locked->viewed_at ?? $now,
            ])->save();

            $request->forceFill([
                'status' => ServiceRequestStatus::InProgress,
                'accepted_offer_id' => $offer->id,
            ])->save();

            $others = $request->offers()
                ->where('status', OfferStatus::Pending)
                ->whereKeyNot($offer->id)
                ->with('providerProfile.user')
                ->lockForUpdate()
                ->get();

            // Vienas UPDATE visiems, o ne po vieną kiekvienam
            Offer::query()->whereKey($others->modelKeys())->update(['status' => OfferStatus::Declined]);
        });

        $offer->loadMissing('providerProfile.user')->providerProfile->user->notify(new OfferAccepted($offer));

        foreach ($others as $other) {
            $other->providerProfile->user->notify(new OfferDeclined($other, OfferDeclineReason::OtherAccepted));
        }

        // Jei kviečiantysis turėjo užkrautą užklausą – ji jau pasenusi (status, accepted_offer_id)
        $offer->unsetRelation('serviceRequest');

        return $offer;
    }
}
