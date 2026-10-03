<?php

namespace App\Http\Controllers\ServiceRequests;

use App\Enums\ServiceRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceRequestSummaryResource;
use App\Models\Category;
use App\Models\City;
use App\Models\User;
use App\Services\Matching\ProviderMatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Teikėjo užklausų srautas: atviros, jam tinkančios užklausos, kurioms jis dar nesiuntė pasiūlymo
 * (docs/DB_SCHEMA.md 8.1). Filtrai URL'e (?kategorija=…&miestas=…&laikotarpis=…), todėl nuorodą galima išsaugoti.
 */
class ProviderFeedController extends Controller
{
    /** Leidžiami laikotarpiai dienomis: „per paskutines N d.". */
    private const PERIODS = [1, 3, 7, 30];

    public function __invoke(Request $request, ProviderMatcher $matcher): Response
    {
        /** @var User $user */
        $user = $request->user();
        $provider = $user->providerProfile;

        $filters = [
            'kategorija' => $request->integer('kategorija') ?: null,
            'miestas' => $request->integer('miestas') ?: null,
            'laikotarpis' => in_array($request->integer('laikotarpis'), self::PERIODS, true) ? $request->integer('laikotarpis') : null,
        ];

        if ($provider === null) {
            // Teikėjas dar neužpildė profilio (vedlys – Etapas 3)
            return Inertia::render('provider-feed/Index', [
                'serviceRequests' => null,
                'filters' => $filters,
                'options' => ['categories' => [], 'cities' => [], 'periods' => self::PERIODS],
                'provider' => null,
            ]);
        }

        $requests = $matcher->matchingRequests($provider)
            ->where('service_requests.status', ServiceRequestStatus::Open)
            // NOT EXISTS su indeksu offers(service_request_id, provider_profile_id)
            ->whereDoesntHave('offers', fn (Builder $query) => $query->where('provider_profile_id', $provider->id))
            ->when($filters['kategorija'], fn (Builder $query, int $id) => $query->where('service_requests.category_id', $id))
            ->when($filters['miestas'], fn (Builder $query, int $id) => $query->where('service_requests.city_id', $id))
            ->when($filters['laikotarpis'], fn (Builder $query, int $days) => $query->where('service_requests.published_at', '>=', now()->subDays($days)))
            ->with(['category:id,name,offer_cost_credits', 'city:id,name'])
            ->latest('published_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('provider-feed/Index', [
            'serviceRequests' => ServiceRequestSummaryResource::collection($requests),
            'filters' => $filters,
            'options' => [
                'categories' => Category::query()->whereKey($matcher->leafCategoryIds($provider))->orderBy('name')->get(['id', 'name']),
                'cities' => City::query()
                    ->when(! $provider->serves_whole_country, fn (Builder $query) => $query->whereKey($matcher->serviceAreaIds($provider)))
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'periods' => self::PERIODS,
            ],
            'provider' => [
                'status' => ['value' => $provider->status->value, 'label' => $provider->status->label()],
                'credits_balance' => $provider->credits_balance,
                'serves_whole_country' => $provider->serves_whole_country,
            ],
        ]);
    }
}
