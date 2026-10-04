<?php

namespace App\Http\Controllers\Catalog;

use App\Enums\ProviderSort;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CatalogFilterRequest;
use App\Http\Resources\Catalog\CategoryProviderCardResource;
use App\Models\Category;
use App\Models\City;
use App\Services\Catalog\CachedCategory;
use App\Services\Catalog\CachedCity;
use App\Services\Catalog\CatalogCache;
use App\Services\Catalog\CatalogSeo;
use App\Services\Catalog\CategoryTree;
use App\Services\Catalog\ProviderListQuery;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kategorijų puslapiai: visas katalogas, kategorija (bet kuris lygis) ir SEO puslapis „paslauga mieste".
 *
 * Kategorija ir miestas randami per route model binding pagal slug ({category:slug}, {city:slug}),
 * o medis (tėvai, vaikai) imamas iš cache.
 */
class CategoryController extends Controller
{
    private const POPULAR_CITIES = 12;

    public function __construct(
        private readonly CatalogCache $catalog,
        private readonly ProviderListQuery $providers,
        private readonly CatalogSeo $seo,
    ) {}

    /**
     * /paslaugos – visas medis: 1 lygis → 2 lygis → 3 lygis.
     */
    public function index(): Response
    {
        $tree = $this->catalog->categories();

        return Inertia::render('public/categories/Index', [
            'categories' => array_map(fn (CachedCategory $root): array => [
                // Etapas 10: toCard() – ir nuotrauka (image_url)
                ...$root->toCard(),
                'children' => $this->linksWithChildren($tree, $root->id),
            ], $tree->roots()),
            'seo' => $this->seo->categoriesIndex()->toArray(),
        ]);
    }

    /**
     * /paslaugos/{kategorija}
     */
    public function show(CatalogFilterRequest $request, Category $category): Response|RedirectResponse
    {
        return $this->render($request, $category, null);
    }

    /**
     * /paslaugos/{kategorija}/{miestas} – „Plytelių klijavimas Vilniuje".
     */
    public function city(CatalogFilterRequest $request, Category $category, City $city): Response|RedirectResponse
    {
        return $this->render($request, $category, $city);
    }

    private function render(CatalogFilterRequest $request, Category $category, ?City $city): Response|RedirectResponse
    {
        $tree = $this->catalog->categories();
        $geography = $this->catalog->geography();

        // Binding'as kategoriją rado, bet išjungtos (ar esančios po išjungtu tėvu) viešai nerodom
        $node = $tree->find($category->id) ?? abort(404);
        $cityNode = $city === null ? null : $geography->findCity($city->id);
        $filters = $request->filters();

        // ?miestas=vilnius (pvz. iš paieškos formos) → SEO adresas /paslaugos/{kategorija}/vilnius
        if ($filters->city !== null && $filters->city->id !== $cityNode?->id) {
            return to_route('categories.city', [
                'category' => $node->slug,
                'city' => $filters->city->slug,
                ...$request->except(['miestas', ProviderListQuery::PAGE_NAME]),
            ]);
        }

        $filters = $filters->withCity($cityNode);
        $providers = $this->providers->paginate($filters, $tree->relatedIds($node->id));
        $ancestors = $tree->ancestors($node->id);
        $root = $ancestors[0] ?? null;

        return Inertia::render('public/categories/Show', [
            'category' => [
                ...$node->toLink(),
                'depth' => $node->depth,
                'description' => $node->description,
                // Ikonas turi tik 1 lygis – 2–3 lygiai paveldi savo srities ikoną
                'icon' => $node->icon ?? $root?->icon,
                // Etapas 10: juosta puslapio viršuje (1600×700) – sava nuotrauka arba srities (1 lygio); null – atsarginis dizainas
                'image_url' => $node->imageUrl ?? $root?->imageUrl,
                'image_wide_url' => $node->imageWideUrl ?? $root?->imageWideUrl,
            ],
            'breadcrumbs' => array_map(fn (CachedCategory $ancestor): array => $ancestor->toLink(), $ancestors),
            'subcategories' => $this->subcategories($tree, $node),
            'city' => $cityNode?->toOption(),
            'providers' => CategoryProviderCardResource::collection($providers),
            'filters' => $filters->toArray(),
            'sortOptions' => ProviderSort::selectOptions(),
            'cities' => $geography->cityOptions(),
            'popularCities' => array_map(
                fn (CachedCity $popular): array => $popular->toOption(),
                $geography->popularCities(self::POPULAR_CITIES),
            ),
            'seo' => $this->seo->category($node, $cityNode, $providers->currentPage(), $providers->total())->toArray(),
        ]);
    }

    /**
     * 1 lygis – 2 lygio kategorijos su jų vaikais; 2 lygis – jo vaikai; 3 lygis – „broliai" (to paties tėvo vaikai).
     *
     * @return list<array{id: int, name: string, slug: string, image_url: string|null, children: list<array{id: int, name: string, slug: string}>}>
     */
    private function subcategories(CategoryTree $tree, CachedCategory $category): array
    {
        if ($category->depth === 1) {
            return $this->linksWithChildren($tree, $category->id);
        }

        $parentId = $category->depth === Category::MAX_DEPTH ? $category->parentId : $category->id;

        return array_map(
            fn (CachedCategory $child): array => [...$child->toLink(), 'image_url' => $child->imageUrl, 'children' => []],
            $tree->children($parentId),
        );
    }

    /**
     * Etapas 10: su image_url – 2 lygio grupės kortelėje rodoma nuotrauka, jei administratorius ją įkėlė.
     *
     * @return list<array{id: int, name: string, slug: string, image_url: string|null, children: list<array{id: int, name: string, slug: string}>}>
     */
    private function linksWithChildren(CategoryTree $tree, int $parentId): array
    {
        return array_map(fn (CachedCategory $child): array => [
            ...$child->toLink(),
            'image_url' => $child->imageUrl,
            'children' => array_map(fn (CachedCategory $grandchild): array => $grandchild->toLink(), $tree->children($child->id)),
        ], $tree->children($parentId));
    }
}
