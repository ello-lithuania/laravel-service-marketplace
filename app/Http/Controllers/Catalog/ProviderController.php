<?php

namespace App\Http\Controllers\Catalog;

use App\Enums\ProviderSort;
use App\Enums\ProviderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CatalogFilterRequest;
use App\Http\Resources\Catalog\PortfolioItemResource;
use App\Http\Resources\Catalog\ProviderCardResource;
use App\Http\Resources\Catalog\ProviderProfileResource;
use App\Http\Resources\Catalog\ReviewResource;
use App\Models\ProviderProfile;
use App\Services\Catalog\CachedCategory;
use App\Services\Catalog\CatalogCache;
use App\Services\Catalog\CatalogSeo;
use App\Services\Catalog\ProviderListQuery;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Visų teikėjų sąrašas (/meistrai) ir viešas teikėjo profilis (/meistrai/{slug}).
 */
class ProviderController extends Controller
{
    private const REVIEWS_PER_PAGE = 10;

    public function __construct(
        private readonly CatalogCache $catalog,
        private readonly CatalogSeo $seo,
    ) {}

    public function index(CatalogFilterRequest $request, ProviderListQuery $providers): Response
    {
        $filters = $request->filters();
        $page = $providers->paginate($filters);

        return Inertia::render('public/providers/Index', [
            'providers' => ProviderCardResource::collection($page),
            'filters' => $filters->toArray(),
            'sortOptions' => ProviderSort::selectOptions(),
            'cities' => $this->catalog->geography()->cityOptions(),
            'seo' => $this->seo->providers($filters->city, $page->currentPage())->toArray(),
        ]);
    }

    /**
     * Route model binding pagal slug: /meistrai/{providerProfile:slug}. Soft-deleted profilių binding'as neranda
     * pats, o neaktyvius (laukia, paslėptas, užblokuotas) atmetam čia.
     */
    public function show(ProviderProfile $providerProfile): Response
    {
        // 404, o ne 403: lankytojui nereikia žinoti, kad toks profilis apskritai yra
        abort_unless($providerProfile->status === ProviderStatus::Active, 404);

        // Eager loading: kiekvienas ryšys – viena užklausa, kad ir kiek būtų paslaugų ar darbų
        $providerProfile->load([
            'city:id,name,slug',
            'serviceAreas' => fn (Relation $query) => $query->select(['cities.id', 'cities.name', 'cities.slug']),
            'categories' => fn (Relation $query) => $query->select(['categories.id', 'categories.name', 'categories.slug', 'categories.depth']),
            'portfolioItems' => fn (Relation $query) => $query->with(['category:id,name', 'city:id,name', 'media']),
            // Logotipas ir viršelio nuotrauka (medialibrary)
            'media' => fn (Relation $query) => $query->whereIn('collection_name', ['logo', 'cover']),
            // Etapas 9c: ženkleliui „PRO" (PlanBenefits::hasBadge)
            'currentSubscriptions:id,provider_profile_id,subscription_plan_id,ends_at',
        ]);

        $reviews = $providerProfile->reviews()
            ->published()
            // Ištrynus paskyrą (soft delete) atsiliepimas lieka: be SoftDeletingScope – tas pats, kas withTrashed()
            ->with(['author' => fn (Relation $query) => $query
                ->withoutGlobalScope(SoftDeletingScope::class)
                ->select(['id', 'first_name', 'last_name'])])
            ->latest('published_at')
            ->orderByDesc('id')
            ->paginate(self::REVIEWS_PER_PAGE, pageName: ProviderListQuery::PAGE_NAME)
            ->withQueryString();

        return Inertia::render('public/providers/Show', [
            'provider' => ProviderProfileResource::make($providerProfile)->resolve(),
            'portfolio' => PortfolioItemResource::collection($providerProfile->portfolioItems)->resolve(),
            'reviews' => ReviewResource::collection($reviews),
            'ratingDistribution' => $this->ratingDistribution($providerProfile),
            'mainCategory' => $this->mainCategory($providerProfile)?->toLink(),
            'seo' => $this->seo->provider($providerProfile)->toArray(),
        ]);
    }

    /**
     * Kiek atsiliepimų su 5, 4… 1 žvaigžde – viena GROUP BY užklausa.
     *
     * @return list<array{rating: int, count: int}>
     */
    private function ratingDistribution(ProviderProfile $provider): array
    {
        $counts = $provider->reviews()
            ->published()
            ->toBase()
            ->selectRaw('rating, COUNT(*) as aggregate')
            ->groupBy('rating')
            ->pluck('aggregate', 'rating');

        return array_map(
            fn (int $rating): array => ['rating' => $rating, 'count' => (int) ($counts[$rating] ?? 0)],
            [5, 4, 3, 2, 1],
        );
    }

    /**
     * Pagrindinė sritis „duonos trupiniams": pirmos matomos teikėjo paslaugos 1 lygio kategorija.
     */
    private function mainCategory(ProviderProfile $provider): ?CachedCategory
    {
        $tree = $this->catalog->categories();

        foreach ($provider->categories as $category) {
            if ($tree->find($category->id) !== null) {
                return $tree->ancestors($category->id)[0] ?? $tree->find($category->id);
            }
        }

        return null;
    }
}
