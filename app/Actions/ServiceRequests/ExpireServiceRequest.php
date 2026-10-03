<?php

namespace App\Actions\ServiceRequests;

use App\Enums\OfferDeclineReason;
use App\Enums\OfferStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\Offer;
use App\Models\ServiceRequest;
use App\Notifications\OfferDeclined;
use App\Services\Credits\CreditLedger;
use Illuminate\Support\Facades\DB;

/**
 * open → expired (docs/STATES.md 1 sk.), kai praėjo expires_at ir niekas nepriimta. Kviečia Scheduler'io komanda.
 *
 * Pending pasiūlymai → declined. Kreditai grąžinami TIK už tuos, kurių klientas neatidarė (viewed_at IS NULL):
 * atidarytas pasiūlymas reiškia, kad teikėjas gavo galimybę (STATES.md 3 sk.).
 */
class ExpireServiceRequest
{
    public function __construct(private readonly CreditLedger $ledger) {}

    /**
     * @return bool false – užklausa jau nebe atvira ar dar negalioja (praleidžiam, ne klaida)
     */
    public function handle(ServiceRequest $serviceRequest): bool
    {
        /** @var list<array{offer: Offer, refunded: int}> $declined */
        $declined = [];

        $expired = DB::transaction(function () use ($serviceRequest, &$declined): bool {
            $locked = ServiceRequest::query()->whereKey($serviceRequest->id)->lockForUpdate()->firstOrFail();

            // Kol komanda dirbo, klientas galėjo priimti pasiūlymą ar atšaukti užklausą – tada nieko nedarom
            if (! $locked->status->canTransitionTo(ServiceRequestStatus::Expired)
                || $locked->expires_at === null
                || $locked->expires_at->isFuture()) {
                return false;
            }

            $offers = $locked->offers()
                ->where('status', OfferStatus::Pending)
                ->with('providerProfile.user')
                ->orderBy('provider_profile_id')
                ->lockForUpdate()
                ->get();

            foreach ($offers as $offer) {
                $offer->forceFill(['status' => OfferStatus::Declined])->save();

                $refunded = $offer->viewed_at === null
                    ? $this->ledger->refund($offer, __('service_requests.refund.expired', ['title' => $locked->title]))->amount ?? 0
                    : 0;

                $declined[] = ['offer' => $offer, 'refunded' => $refunded];
            }

            $serviceRequest->forceFill(['status' => ServiceRequestStatus::Expired])->save();

            return true;
        });

        foreach ($declined as ['offer' => $offer, 'refunded' => $refunded]) {
            $offer->providerProfile->user->notify(new OfferDeclined($offer, OfferDeclineReason::RequestExpired, $refunded));
        }

        return $expired;
    }
}
