<?php

namespace App\Http\Controllers\Catalog;

use App\Enums\ProviderSort;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CatalogFilterRequest;
use App\Http\Resources\Catalog\ProviderCardResource;
use App\Services\Catalog\CachedCategory;
use App\Services\Catalog\CatalogCache;
use App\Services\Catalog\CatalogSeo;
use App\Services\Catalog\ProviderListQuery;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Paieška tekstu: /paieska?q=plytelių+klijavimas&miestas=vilnius.
 * Rodo tinkančias kategorijas (iš cache) ir teikėjus (FULLTEXT / LIKE – ProviderProfile::matchingText).
 */
class SearchController extends Controller
{
    private const MAX_CATEGORIES = 8;

    public function __invoke(
        CatalogFilterRequest $request,
        CatalogCache $catalog,
        ProviderListQuery $providers,
        CatalogSeo $seo,
    ): Response|RedirectResponse {
        $filters = $request->filters(defaultSort: ProviderSort::Relevance);

        // Be paieškos teksto tai tiesiog teikėjų sąrašas – nukreipiam į jį su tais pačiais filtrais
        if ($filters->search === null) {
            return to_route('providers.index', $request->except(['q', 'rikiuoti', ProviderListQuery::PAGE_NAME]));
        }

        $tree = $catalog->categories();
        $page = $providers->paginate($filters);

        return Inertia::render('public/Search', [
            'categories' => array_map(fn (CachedCategory $category): array => [
                ...$category->toLink(),
                'path' => implode(' › ', array_map(fn (CachedCategory $ancestor): string => $ancestor->name, $tree->ancestors($category->id))),
            ], $tree->search($filters->search->words, self::MAX_CATEGORIES)),
            'providers' => ProviderCardResource::collection($page),
            'filters' => $filters->toArray(),
            'sortOptions' => ProviderSort::selectOptions(withRelevance: true),
            'cities' => $catalog->geography()->cityOptions(),
            'seo' => $seo->search($filters->search->input)->toArray(),
        ]);
    }
}
