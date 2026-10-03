<?php

namespace App\Http\Controllers\Offers;

use App\Actions\Offers\AcceptOffer;
use App\Actions\Offers\DeclineOffer;
use App\Actions\Offers\WithdrawOffer;
use App\Http\Controllers\Controller;
use App\Models\Offer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Pasiūlymo būsenų perėjimai (docs/STATES.md 2 sk.): priimti, atmesti (klientas), atšaukti (teikėjas).
 * Kiekvienas metodas: Policy → Action → pranešimas vartotojui → atgal į užklausos puslapį.
 */
class OfferTransitionController extends Controller
{
    public function accept(Offer $offer, AcceptOffer $accept): RedirectResponse
    {
        $slug = $this->authorize('accept', $offer);

        $accept->handle($offer);

        return $this->done($slug, __('offers.flash.accepted'));
    }

    public function decline(Offer $offer, DeclineOffer $decline): RedirectResponse
    {
        $slug = $this->authorize('decline', $offer);

        $decline->handle($offer);

        return $this->done($slug, __('offers.flash.declined'));
    }

    public function withdraw(Offer $offer, WithdrawOffer $withdraw): RedirectResponse
    {
        $slug = $this->authorize('withdraw', $offer);

        $withdraw->handle($offer);

        return $this->done($slug, __('offers.flash.withdrawn'));
    }

    /**
     * Policy tikrina $offer->serviceRequest, todėl jį užkraunam iš anksto; grąžinam slug nukreipimui.
     */
    private function authorize(string $ability, Offer $offer): string
    {
        $offer->load('serviceRequest:id,slug,client_id,status');
        Gate::authorize($ability, $offer);

        return $offer->serviceRequest->slug;
    }

    private function done(string $slug, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('service-requests.show', ['serviceRequest' => $slug]);
    }
}
