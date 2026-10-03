<?php

namespace App\Services\Catalog;

use App\Models\ProviderProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Teikėjų sąrašas katalogui: kategorijų, pradžios ir paieškos puslapiams.
 *
 * Užklausa sudedama iš modelio scope'ų (ProviderProfile → „Katalogas ir paieška"), o čia nusprendžiama,
 * kuriuos stulpelius ir ryšius užkrauti, kad kortelei užtektų vienos užklausos kiekvienam ryšiui (be N+1).
 */
class ProviderListQuery
{
    public const PER_PAGE = 20;

    /** Lietuviškas puslapio parametras URL: /meistrai?puslapis=2 */
    public const PAGE_NAME = 'puslapis';

    /**
     * Tik kortelei reikalingi stulpeliai: ilgo aprašymo (TEXT) sąraše nereikia.
     */
    private const CARD_COLUMNS = [
        'provider_profiles.id', 'provider_profiles.slug', 'provider_profiles.display_name',
        'provider_profiles.headline', 'provider_profiles.city_id', 'provider_profiles.serves_whole_country',
        'provider_profiles.verified_at', 'provider_profiles.rating_avg', 'provider_profiles.reviews_count',
        'provider_profiles.completed_jobs_count', 'provider_profiles.years_experience',
        'provider_profiles.created_at',
    ];

    /**
     * @param  list<int>|null  $categoryIds  null – visos kategorijos
     * @return LengthAwarePaginator<int, ProviderProfile>
     */
    public function paginate(ProviderFilters $filters, ?array $categoryIds = null): LengthAwarePaginator
    {
        return $this->query($filters, $categoryIds)
            ->paginate(self::PER_PAGE, pageName: self::PAGE_NAME)
            // Puslapių nuorodose išlieka filtrai: ?miestas=vilnius&puslapis=2
            ->withQueryString();
    }

    /**
     * Geriausiai įvertinti teikėjai su bent keliais atsiliepimais (pradžios puslapiui).
     *
     * @return Collection<int, ProviderProfile>
     */
    public function topRated(int $limit, int $minReviews = 3): Collection
    {
        return $this->query(new ProviderFilters)
            ->where('provider_profiles.reviews_count', '>=', $minReviews)
            ->limit($limit)
            ->get();
    }

    /**
     * @param  list<int>|null  $categoryIds
     * @return Builder<ProviderProfile>
     */
    private function query(ProviderFilters $filters, ?array $categoryIds = null): Builder
    {
        return ProviderProfile::query()
            ->select(self::CARD_COLUMNS)
            ->active()
            ->when($categoryIds !== null, fn (Builder $query) => $query->inCategories($categoryIds ?? []))
            ->when($filters->city, fn (Builder $query, CachedCity $city) => $query->servingCity($city->id))
            ->when($filters->verifiedOnly, fn (Builder $query) => $query->verified())
            ->when($filters->minRating, fn (Builder $query, float $rating) => $query->withMinRating($rating))
            ->when($filters->search, fn (Builder $query, SearchTerms $terms) => $query->matchingText($terms))
            ->sortedBy($filters->sort, $filters->search)
            // Eager loading: miestai ir kategorijos visiems puslapio teikėjams – dviem užklausomis, ne 2 × 20
            ->with([
                'city:id,name,slug',
                'categories' => function (Relation $query) use ($categoryIds): void {
                    $query->select(['categories.id', 'categories.name', 'categories.slug', 'categories.depth'])
                        ->where('categories.is_active', true)
                        ->orderByDesc('categories.depth')
                        ->orderBy('categories.sort_order');

                    // Kategorijos puslapyje – tik su ja susijusios paslaugos (kaina „nuo" kortelėje)
                    if ($categoryIds !== null) {
                        $query->whereIn('categories.id', $categoryIds);
                    }
                },
            ]);
    }
}
