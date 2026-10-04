<?php

namespace App\Services\Catalog;

use App\Models\ProviderProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Cache;

/**
 * Teikėjų sąrašas katalogui: kategorijų, pradžios ir paieškos puslapiams.
 *
 * Užklausa sudedama iš modelio scope'ų (ProviderProfile → „Katalogas ir paieška"), o čia nusprendžiama,
 * kuriuos stulpelius ir ryšius užkrauti, kad kortelei užtektų vienos užklausos kiekvienam ryšiui (be N+1).
 */
class ProviderListQuery
{
    public const PER_PAGE = 20;

    /** Kiek minučių laikomas bendras teikėjų skaičius (cachedTotal). */
    public const COUNT_TTL_MINUTES = 5;

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
        $query = $this->query($filters, $categoryIds);

        return $query
            ->paginate(self::PER_PAGE, pageName: self::PAGE_NAME, total: $this->cachedTotal($query, $filters))
            // Puslapių nuorodose išlieka filtrai: ?miestas=vilnius&puslapis=2
            ->withQueryString();
    }

    /**
     * Etapas 8: bendras teikėjų skaičius (puslapiavimui) laikomas cache kelias minutes.
     *
     * Kodėl: pilname seed'e (20 000 teikėjų) sąrašo užklausa su indeksu trunka 1–2 ms, o COUNT per plačią kategoriją
     * su miestu – 25–85 ms (MySQL turi patikrinti kiekvieną aktyvų teikėją; docs/PERFORMANCE.md). Kategorijų ir
     * „paslauga mieste" puslapius nuolat lanko ir robotai, o skaičius kelias minutes gali būti ir nevisai tikslus.
     * Paieškos tekstu – ne: užklausų begalė, cache beveik nepasikartotų.
     * Raktas – SQL su parametrais, todėl kiekvienas filtrų derinys turi savo skaičių.
     *
     * @param  Builder<ProviderProfile>  $query
     */
    private function cachedTotal(Builder $query, ProviderFilters $filters): ?int
    {
        if ($filters->search !== null) {
            return null;
        }

        $base = $query->toBase();

        return (int) Cache::remember(
            'catalog:count:v1:'.sha1($base->toRawSql()),
            now()->addMinutes(self::COUNT_TTL_MINUTES),
            fn (): int => $base->getCountForPagination(),
        );
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
                // Tik logotipas (medialibrary „logo" kolekcija) – kortelei kitų failų nereikia
                'media' => fn (Relation $query) => $query->where('collection_name', 'logo'),
                // Etapas 9c: ženkleliui „PRO" – dabar galiojančios prenumeratos, viena užklausa visam puslapiui
                // (WHERE provider_profile_id IN (…20 ID) – indeksas (provider_profile_id, status)). Ne withExists():
                // tada subužklausa būtų pagrindinės užklausos SELECT'e, o jos laikas (now()) – COUNT cache rakte
                'currentSubscriptions' => fn (Relation $query) => $query->select([
                    'subscriptions.id', 'subscriptions.provider_profile_id', 'subscriptions.subscription_plan_id',
                    'subscriptions.ends_at',
                ]),
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
