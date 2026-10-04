<?php

namespace App\Http\Controllers\Catalog;

use App\Enums\SitePhotoKey;
use App\Http\Controllers\Controller;
use App\Http\Resources\Catalog\ProviderCardResource;
use App\Services\Catalog\CachedCategory;
use App\Services\Catalog\CachedCity;
use App\Services\Catalog\CatalogCache;
use App\Services\Catalog\CatalogSeo;
use App\Services\Catalog\ProviderListQuery;
use App\Services\Site\SitePhotos;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pradžios puslapis: 1 lygio kategorijos, paieška, populiarūs miestai, geriausiai įvertinti teikėjai.
 * Kategorijos ir miestai – iš cache, todėl DB užklausa reikalinga tik teikėjams.
 */
class HomeController extends Controller
{
    private const POPULAR_CITIES = 12;

    private const FEATURED_PROVIDERS = 6;

    /** Kiek 2 lygio kategorijų parodyti po 1 lygio pavadinimu. */
    private const CATEGORY_PREVIEW = 3;

    public function __invoke(CatalogCache $catalog, ProviderListQuery $providers, CatalogSeo $seo, SitePhotos $sitePhotos): Response
    {
        $tree = $catalog->categories();
        $geography = $catalog->geography();

        return Inertia::render('public/Home', [
            'categories' => array_map(fn (CachedCategory $root): array => [
                ...$root->toCard(),
                'children' => array_map(
                    fn (CachedCategory $child): array => $child->toLink(),
                    array_slice($tree->children($root->id), 0, self::CATEGORY_PREVIEW),
                ),
            ], $tree->roots()),
            'popularCities' => array_map(
                fn (CachedCity $city): array => $city->toOption(),
                $geography->popularCities(self::POPULAR_CITIES),
            ),
            'cities' => $geography->cityOptions(),
            'featuredProviders' => ProviderCardResource::collection($providers->topRated(self::FEATURED_PROVIDERS))->resolve(),
            'seo' => $seo->home()->toArray(),
            // Etapas 10: dizaino nuotraukos (null – nuotraukos nėra, Vue rodo atsarginį dizainą)
            'photos' => $sitePhotos->forPage(SitePhotoKey::Hero, SitePhotoKey::Providers, SitePhotoKey::Request),
        ]);
    }
}
