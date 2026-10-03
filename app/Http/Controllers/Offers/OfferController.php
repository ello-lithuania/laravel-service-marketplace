<?php

namespace App\Http\Controllers\Offers;

use App\Actions\Offers\MarkOfferViewed;
use App\Actions\Offers\SendOffer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Offers\StoreOfferRequest;
use App\Http\Resources\OfferResource;
use App\Http\Resources\ServiceRequestSummaryResource;
use App\Models\Offer;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pasiūlymai: teikėjo „Mano pasiūlymai", siuntimas ir kliento pasiūlymo puslapis.
 */
class OfferController extends Controller
{
    /**
     * „Mano pasiūlymai" – naudoja indeksą offers(provider_profile_id, created_at).
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $provider = $user->providerProfile;

        $offers = $provider?->offers()
            ->with('serviceRequest:id,slug,title,status')
            ->latest()
            ->paginate(15);

        return Inertia::render('offers/Index', [
            'offers' => $offers === null ? null : OfferResource::collection($offers),
            'creditsBalance' => $provider->credits_balance ?? 0,
        ]);
    }

    public function store(StoreOfferRequest $request, ServiceRequest $serviceRequest, SendOffer $send): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        // Policy (StoreOfferRequest::authorize) jau patikrino, kad profilis yra
        $provider = $user->providerProfile()->firstOrFail();

        $offer = $send->handle($provider, $serviceRequest, $request->toOfferAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('offers.flash.sent', ['credits' => $offer->credits_spent])]);

        return to_route('service-requests.show', $serviceRequest);
    }

    /**
     * Klientas atidaro pasiūlymą → viewed_at (nuo jo priklauso kreditų grąžinimas, docs/STATES.md 3 sk.).
     * Maršrutas su scopeBindings(): pasiūlymas turi priklausyti URL'e nurodytai užklausai, kitaip 404.
     */
    public function show(Request $request, ServiceRequest $serviceRequest, Offer $offer, MarkOfferViewed $markViewed): Response|RedirectResponse
    {
        $offer->setRelation('serviceRequest', $serviceRequest);
        Gate::authorize('view', $offer);

        /** @var User $user */
        $user = $request->user();

        // Teikėjas savo pasiūlymą mato užklausos puslapyje
        if ($serviceRequest->client_id !== $user->id && ! $user->isAdmin()) {
            return to_route('service-requests.show', $serviceRequest);
        }

        if ($serviceRequest->client_id === $user->id) {
            $markViewed->handle($offer);
        }

        $offer->load('providerProfile.city:id,name');
        $serviceRequest->load(['category:id,name,offer_cost_credits', 'city:id,name']);

        return Inertia::render('offers/Show', [
            'offer' => OfferResource::make($offer)->withFullMessage()->resolve(),
            'serviceRequest' => ServiceRequestSummaryResource::make($serviceRequest)->resolve(),
            'can' => ['accept' => $user->can('accept', $offer), 'decline' => $user->can('decline', $offer)],
        ]);
    }
}
