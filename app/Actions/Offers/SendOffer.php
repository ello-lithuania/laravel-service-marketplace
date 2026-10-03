<?php

namespace App\Actions\Offers;

use App\Enums\CreditTransactionType;
use App\Enums\OfferStatus;
use App\Enums\ServiceRequestStatus;
use App\Exceptions\InsufficientCreditsException;
use App\Models\Offer;
use App\Models\ProviderProfile;
use App\Models\ServiceRequest;
use App\Notifications\NewOffer;
use App\Services\Credits\CreditLedger;
use App\Services\Matching\ProviderMatcher;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Teikėjas siunčia pasiūlymą: (naujas) → pending (docs/STATES.md 2 sk.).
 *
 * Sąlygos: užklausa open; teikėjas tinka (ProviderMatcher); šiai užklausai dar nesiuntė; kreditų pakanka.
 * Pasekmės: nurašomi kreditai (ledger įrašas „offer"), credits_spent, offers_count + 1, pranešimas klientui.
 *
 * Viskas vyksta vienoje DB transakcijoje su užraktais (lockForUpdate): arba įvyksta VISKAS (pasiūlymas,
 * nurašymas, skaitliukas), arba NIEKAS – negali likti pasiūlymo be nurašytų kreditų ar atvirkščiai.
 */
class SendOffer
{
    public function __construct(
        private readonly CreditLedger $ledger,
        private readonly ProviderMatcher $matcher,
    ) {}

    /**
     * @param  array{message: string, price_cents: ?int, price_type: string, duration_text: ?string, start_date: ?string}  $attributes
     *
     * @throws ValidationException kai pažeista kuri nors sąlyga (pranešimas lietuviškai)
     */
    public function handle(ProviderProfile $provider, ServiceRequest $serviceRequest, array $attributes): Offer
    {
        try {
            $offer = DB::transaction(function () use ($provider, $serviceRequest, $attributes): Offer {
                // 1. Užklausos eilutė: kol siunčiam, klientas negali jos atšaukti ar priimti kito pasiūlymo
                $request = ServiceRequest::query()->with('category')->whereKey($serviceRequest->id)->lockForUpdate()->firstOrFail();

                // 2. Teikėjo eilutė: du vienu metu siunčiami pasiūlymai laukia vienas kito, todėl abu nepamatys
                //    to paties balanso (MySQL; SQLite rašymus ir taip vykdo po vieną)
                $locked = ProviderProfile::query()->whereKey($provider->id)->lockForUpdate()->firstOrFail();

                $this->ensureCanSend($locked, $request);

                $cost = $request->category->offer_cost_credits;

                $offer = new Offer($attributes);
                $offer->serviceRequest()->associate($request);
                $offer->providerProfile()->associate($locked);
                $offer->forceFill(['status' => OfferStatus::Pending, 'credits_spent' => $cost])->save();

                $this->ledger->debit($locked, $cost, CreditTransactionType::Offer, $offer,
                    __('offers.ledger.offer', ['title' => $request->title]));

                $request->increment('offers_count');

                $provider->forceFill(['credits_balance' => $locked->credits_balance])->syncOriginalAttribute('credits_balance');

                return $offer;
            });
        } catch (InsufficientCreditsException $e) {
            throw ValidationException::withMessages(['credits' => __('offers.errors.insufficient_credits', [
                'required' => $e->required,
                'balance' => $e->balance,
            ])]);
        } catch (UniqueConstraintViolationException) {
            // Paskutinė apsauga – UNIQUE(service_request_id, provider_profile_id) DB lygiu (dvigubas paspaudimas)
            throw ValidationException::withMessages(['offer' => __('offers.errors.duplicate')]);
        }

        $serviceRequest->loadMissing('client')->client->notify(new NewOffer($offer));

        return $offer;
    }

    /**
     * Visos sąlygos tikrinamos jau UŽRAKINUS eilutes – tik tada rezultatas tikrai galioja iki transakcijos pabaigos.
     */
    private function ensureCanSend(ProviderProfile $provider, ServiceRequest $request): void
    {
        if ($request->status !== ServiceRequestStatus::Open) {
            throw ValidationException::withMessages(['offer' => __('offers.errors.request_not_open')]);
        }

        if (! $this->matcher->isEligible($provider, $request)) {
            throw ValidationException::withMessages(['offer' => __('offers.errors.not_eligible')]);
        }

        if ($request->offers()->where('provider_profile_id', $provider->id)->exists()) {
            throw ValidationException::withMessages(['offer' => __('offers.errors.duplicate')]);
        }

        $cost = $request->category->offer_cost_credits;

        if ($provider->credits_balance < $cost) {
            throw new InsufficientCreditsException($provider->credits_balance, $cost);
        }
    }
}
