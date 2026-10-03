<?php

namespace App\Http\Controllers\ServiceRequests;

use App\Actions\ServiceRequests\CreateServiceRequest;
use App\Enums\OfferPriceType;
use App\Enums\OfferStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\StartPreference;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequests\StoreServiceRequestRequest;
use App\Http\Resources\OfferResource;
use App\Http\Resources\Reviews\AccountReviewResource;
use App\Http\Resources\ServiceRequestResource;
use App\Http\Resources\ServiceRequestSummaryResource;
use App\Models\Category;
use App\Models\City;
use App\Models\Offer;
use App\Models\Review;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Support\OfferMessaging;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Užklausos: kūrimas, kliento sąrašas ir užklausos puslapis (klientui ir teikėjui – skirtingi Vue puslapiai).
 * Controller'is plonas: validacija – Form Request, teisės – Policy, logika – Action.
 */
class ServiceRequestController extends Controller
{
    public function create(Request $request): Response
    {
        Gate::authorize('create', ServiceRequest::class);

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('service-requests/Create', [
            'categories' => $this->categoryTree(),
            'cities' => City::query()
                ->with('region:id,name')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'region_id', 'name'])
                ->map(fn (City $city): array => ['id' => $city->id, 'name' => $city->name, 'region' => $city->region->name]),
            'startPreferences' => array_map(
                fn (StartPreference $preference): array => ['value' => $preference->value, 'label' => $preference->label()],
                StartPreference::cases(),
            ),
            // Kitų puslapių nuorodos gali iš anksto parinkti paslaugą ir miestą: /uzklausos/nauja?kategorija=…&miestas=…
            'defaults' => [
                'category_id' => Category::query()
                    ->where('slug', $this->querySlug($request, 'kategorija'))
                    ->where('depth', Category::MAX_DEPTH)
                    ->where('is_active', true)
                    ->value('id'),
                'city_id' => City::query()->where('slug', $this->querySlug($request, 'miestas'))->value('id') ?? $user->city_id,
            ],
        ]);
    }

    public function store(StoreServiceRequestRequest $request, CreateServiceRequest $create): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $serviceRequest = $create->handle($user, $request->toServiceRequestAttributes());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $serviceRequest->status === ServiceRequestStatus::Open
                ? __('service_requests.flash.published')
                : __('service_requests.flash.pending'),
        ]);

        return to_route('service-requests.show', $serviceRequest);
    }

    /**
     * „Mano užklausos" – naudoja indeksą (client_id, created_at).
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $serviceRequests = $user->serviceRequests()
            ->with(['category:id,name,offer_cost_credits', 'city:id,name'])
            ->latest()
            ->paginate(10);

        return Inertia::render('service-requests/Index', [
            'serviceRequests' => ServiceRequestSummaryResource::collection($serviceRequests),
        ]);
    }

    public function show(Request $request, ServiceRequest $serviceRequest): Response
    {
        Gate::authorize('view', $serviceRequest);

        /** @var User $user */
        $user = $request->user();
        $serviceRequest->load(['category:id,name,offer_cost_credits', 'city:id,name']);

        return $serviceRequest->client_id === $user->id || $user->isAdmin()
            ? $this->clientView($user, $serviceRequest)
            : $this->providerView($user, $serviceRequest);
    }

    private function clientView(User $user, ServiceRequest $serviceRequest): Response
    {
        $offers = $serviceRequest->offers()
            // Etapas 6: conversation – „Rašyti žinutę" mygtukui (OfferMessaging), be N+1
            ->with(['providerProfile.city:id,name', 'conversation'])
            // Pirma priimtas, tada laukiantys, tada kiti; tame pačiame lygyje – naujausi viršuje
            ->orderByRaw('CASE status WHEN ? THEN 0 WHEN ? THEN 1 ELSE 2 END', [OfferStatus::Accepted->value, OfferStatus::Pending->value])
            ->latest()
            ->get()
            // Policy tikrina $offer->serviceRequest – priskiriam jau turimą modelį, be papildomos užklausos
            ->each(fn (Offer $offer) => $offer->setRelation('serviceRequest', $serviceRequest));

        $accepted = $offers->firstWhere('id', $serviceRequest->accepted_offer_id);
        $accepted?->loadMissing('providerProfile.user');

        return Inertia::render('service-requests/Show', [
            'serviceRequest' => ServiceRequestResource::make($serviceRequest)->withPrivateDetails()->resolve(),
            'offers' => $offers->map(fn (Offer $offer): array => [
                ...OfferResource::make($offer)->resolve(),
                'can' => ['accept' => $user->can('accept', $offer), 'decline' => $user->can('decline', $offer)],
                'messaging' => OfferMessaging::for($user, $offer),
            ]),
            // Išrinkto teikėjo kontaktai – tik po priėmimo
            'acceptedContact' => $accepted === null ? null : [
                'name' => $accepted->providerProfile->display_name,
                'phone' => $accepted->providerProfile->user->phone,
                'email' => $accepted->providerProfile->user->email,
            ],
            'can' => [
                'cancel' => $user->can('cancel', $serviceRequest),
                'complete' => $user->can('complete', $serviceRequest),
                // --- Etapas 6 ---
                'review' => $user->can('createVerified', [Review::class, $serviceRequest]),
            ],
            // --- Etapas 6: kliento atsiliepimas apie šį darbą (jei jau paliktas) ---
            'review' => $this->clientReview($serviceRequest),
        ]);
    }

    private function providerView(User $user, ServiceRequest $serviceRequest): Response
    {
        $provider = $user->providerProfile;
        $myOffer = $provider === null ? null : $serviceRequest->offers()
            ->where('provider_profile_id', $provider->id)
            ->first()
            ?->setRelation('serviceRequest', $serviceRequest);

        $isChosen = $myOffer !== null && $serviceRequest->accepted_offer_id === $myOffer->id;
        $offerPermission = Gate::inspect('create', [Offer::class, $serviceRequest]);
        $serviceRequest->loadMissing('client');

        // Paprastas peržiūrų skaitliukas (tik teikėjų peržiūros) – atominis UPDATE
        ServiceRequest::query()->whereKey($serviceRequest->id)->increment('views_count');

        return Inertia::render('service-requests/ProviderShow', [
            'serviceRequest' => ServiceRequestResource::make($serviceRequest)->withPrivateDetails($isChosen)->resolve(),
            'client' => [
                'public_name' => $serviceRequest->client->public_name,
                // Telefonas ir el. paštas – tik išrinktam teikėjui (docs/STATES.md: atskleidžiama priėmus)
                'phone' => $isChosen ? $serviceRequest->client->phone : null,
                'email' => $isChosen ? $serviceRequest->client->email : null,
            ],
            'myOffer' => $myOffer === null ? null : [
                ...OfferResource::make($myOffer)->withFullMessage()->resolve(),
                'is_chosen' => $isChosen,
                'can' => ['withdraw' => $user->can('withdraw', $myOffer)],
                'messaging' => OfferMessaging::for($user, $myOffer),
            ],
            'offerForm' => [
                'allowed' => $offerPermission->allowed(),
                'reason' => $offerPermission->denied() ? $offerPermission->message() : null,
                'cost' => $serviceRequest->category->offer_cost_credits,
                'balance' => $provider->credits_balance ?? 0,
                'priceTypes' => array_map(
                    fn (OfferPriceType $type): array => ['value' => $type->value, 'label' => $type->label()],
                    OfferPriceType::cases(),
                ),
            ],
        ]);
    }

    /**
     * Etapas 6: atsiliepimas apie šią užklausą (patvirtintas – vienas užklausai).
     *
     * @return array<string, mixed>|null
     */
    private function clientReview(ServiceRequest $serviceRequest): ?array
    {
        $review = $serviceRequest->review()->with('author')->first();

        return $review === null ? null : AccountReviewResource::make($review->setRelation('serviceRequest', $serviceRequest))->resolve();
    }

    /**
     * URL parametras kaip eilutė. Vartotojas gali atsiųsti ir masyvą (?kategorija[]=…) – tada tiesiog ignoruojam.
     */
    private function querySlug(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_string($value) ? $value : '';
    }

    /**
     * Aktyvių kategorijų medis paslaugos pasirinkimui: [{id, name, children: [{id, name, children: […]}]}].
     * ~250 eilučių – viena užklausa, medis sudedamas PHP'e (docs/DB_SCHEMA.md 2.2).
     *
     * @return list<array<string, mixed>>
     */
    private function categoryTree(): array
    {
        $byParent = Category::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name'])
            ->groupBy(fn (Category $category): int => $category->parent_id ?? 0);

        $build = function (int $parentId) use (&$build, $byParent): array {
            return array_values($byParent->get($parentId, collect())
                ->map(fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'children' => $build($category->id),
                ])
                ->all());
        };

        return $build(0);
    }
}
