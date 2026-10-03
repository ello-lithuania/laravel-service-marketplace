<?php

namespace App\Services\Seo;

use App\Enums\ProviderStatus;
use App\Services\Catalog\CachedCity;
use App\Services\Catalog\CatalogCache;
use App\Services\Catalog\CategoryTree;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Kurie „{Paslauga} {mieste}" puslapiai turi bent vieną teikėją (tik tokie dedami į sitemap.xml –
 * tuščius CatalogSeo žymi noindex, „plonas" turinys).
 *
 * Taisyklė ta pati kaip kategorijos puslapyje (CategoryController + ProviderListQuery): puslapyje X rodomi
 * aktyvūs teikėjai, pasirinkę kategoriją iš CategoryTree::relatedIds(X) (X, jos tėvai ir vaikai) ir aptarnaujantys
 * miestą (zona arba „visa Lietuva"). Atvirkščiai: teikėjo kategorija k „užpildo" puslapius k, k tėvus ir k vaikus.
 *
 * Kaip skaičiuojam: dvi užklausos vietoj 250 × 60 = 15 000 atskirų COUNT – (kategorija, miestas) poros iš zonų ir
 * „visos Lietuvos" teikėjų kategorijos; medis išskleidžiamas PHP'e iš cache. Rezultatas – cache 6 val.
 */
final class CategoryCityIndex
{
    public const CACHE_KEY = 'seo:category-city-index:v1';

    public function __construct(private readonly CatalogCache $catalog) {}

    /**
     * @return array<int, list<int>> kategorijos id → miestų id (surikiuoti kaip geografijoje: didmiesčiai pirmi)
     */
    public function pages(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addHours(6), fn (): array => $this->build());
    }

    /**
     * @return array<int, list<int>>
     */
    private function build(): array
    {
        $tree = $this->catalog->categories();
        $cityIds = array_map(fn (CachedCity $city): int => $city->id, $this->catalog->geography()->popularCities(PHP_INT_MAX));

        /** @var array<int, array<int, true>> $pages */
        $pages = [];

        // „Visa Lietuva" teikėjų kategorijos užpildo visus miestus
        $wholeCountry = $this->activeProviderCategories()
            ->where('provider_profiles.serves_whole_country', true)
            ->distinct()
            ->pluck('category_provider_profile.category_id');

        foreach ($wholeCountry as $categoryId) {
            foreach ($this->affectedPages($tree, (int) $categoryId) as $pageId) {
                $pages[$pageId] = array_fill_keys($cityIds, true);
            }
        }

        // Kiti – tik savo zonų miestus
        $zonePairs = $this->activeProviderCategories()
            ->join('city_provider_profile', 'city_provider_profile.provider_profile_id', '=', 'provider_profiles.id')
            ->where('provider_profiles.serves_whole_country', false)
            ->distinct()
            ->get(['category_provider_profile.category_id', 'city_provider_profile.city_id']);

        foreach ($zonePairs as $pair) {
            foreach ($this->affectedPages($tree, (int) $pair->category_id) as $pageId) {
                $pages[$pageId][(int) $pair->city_id] = true;
            }
        }

        // Tvarka – kaip medyje ir geografijoje, kad sitemap failai būtų pastovūs
        $result = [];

        foreach ($tree->all() as $category) {
            if (isset($pages[$category->id])) {
                $result[$category->id] = array_values(array_filter($cityIds, fn (int $id): bool => isset($pages[$category->id][$id])));
            }
        }

        return $result;
    }

    private function activeProviderCategories(): Builder
    {
        return DB::table('category_provider_profile')
            ->join('provider_profiles', 'provider_profiles.id', '=', 'category_provider_profile.provider_profile_id')
            ->where('provider_profiles.status', ProviderStatus::Active->value)
            ->whereNull('provider_profiles.deleted_at');
    }

    /**
     * Puslapiai, kuriuose rodomas kategoriją $categoryId pasirinkęs teikėjas: ji pati, jos tėvai ir vaikai.
     * Išjungtos kategorijos (jų nėra medyje) neturi ir puslapių.
     *
     * @return list<int>
     */
    private function affectedPages(CategoryTree $tree, int $categoryId): array
    {
        return $tree->find($categoryId) === null ? [] : $tree->relatedIds($categoryId);
    }
}
