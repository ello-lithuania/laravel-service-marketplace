<?php

namespace App\Services\Matching;

use App\Enums\ProviderStatus;
use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Atitikimo taisyklė „kuris teikėjas tinka kuriai užklausai" – VIENOJE vietoje (docs/DB_SCHEMA.md 2.2, 8.1).
 *
 * Teikėjas tinka užklausai, kai:
 *  1. jo profilis aktyvus (status = active), o paskyra neužblokuota ir neištrinta;
 *  2. jis pasirinko užklausos 3 lygio kategoriją ARBA jos tėvą (2 lygis) ARBA senelį (1 lygis);
 *  3. užklausos savivaldybė yra jo zonose ARBA jis aptarnauja visą Lietuvą.
 *
 * Ta pati taisyklė naudojama trimis kryptimis: pranešimams (užklausa → teikėjai), teikėjo srautui
 * (teikėjas → užklausos) ir pasiūlymo siuntimo patikrai (ar šis teikėjas tinka šiai užklausai).
 * Sąmoningai ne ProviderProfile scope: taisyklė apima ir kategorijų medį, ir užklausas, ne vien teikėjo lentelę.
 */
class ProviderMatcher
{
    /**
     * Tinkami užklausai teikėjai (užklausa → teikėjai). Grąžina Builder, kad kviečiantysis galėtų
     * pridėti savo sąlygas, eager loading ar skaidyti dalimis (chunkById).
     *
     * @return Builder<ProviderProfile>
     */
    public function eligibleProviders(ServiceRequest $request): Builder
    {
        $categoryIds = $this->categoryWithAncestors($request->category_id);

        return ProviderProfile::query()
            ->where('provider_profiles.status', ProviderStatus::Active)
            // id IN (pivot subužklausa) naudoja indeksą category_provider_profile(category_id, provider_profile_id)
            ->whereIn('provider_profiles.id', fn (QueryBuilder $query) => $query
                ->select('provider_profile_id')
                ->from('category_provider_profile')
                ->whereIn('category_id', $categoryIds))
            ->where(fn (Builder $query) => $query
                ->where('provider_profiles.serves_whole_country', true)
                ->orWhereIn('provider_profiles.id', fn (QueryBuilder $zones) => $zones
                    ->select('provider_profile_id')
                    ->from('city_provider_profile')
                    ->where('city_id', $request->city_id)))
            // whereHas su User modeliu prideda ir soft deletes sąlygą (deleted_at IS NULL)
            ->whereHas('user', fn (Builder $query) => $query->whereNull('banned_at'));
    }

    /**
     * Ar konkretus teikėjas tinka užklausai – ta pati SQL taisyklė, apribota vienu teikėju.
     */
    public function isEligible(ProviderProfile $provider, ServiceRequest $request): bool
    {
        return $this->eligibleProviders($request)->whereKey($provider->id)->exists();
    }

    /**
     * Teikėjui tinkančios užklausos (teikėjas → užklausos), be statuso sąlygos – ją prideda kviečiantysis.
     *
     * Atvirkštinė kryptis: teikėjo pasirinktos kategorijos paverčiamos 3 lygio ID sąrašu
     * (3 lygis – pati, 2 lygis – jos vaikai, 1 lygis – anūkai), zonos – savivaldybių ID sąrašu.
     * Sąrašai perduodami whereIn, kaip aprašyta docs/DB_SCHEMA.md 8.1.
     *
     * @return Builder<ServiceRequest>
     */
    public function matchingRequests(ProviderProfile $provider): Builder
    {
        $query = ServiceRequest::query();

        if ($provider->status !== ProviderStatus::Active) {
            // Neaktyvus teikėjas užklausų negauna – tuščias rezultatas be papildomos logikos kviečiančiajam
            return $query->whereRaw('1 = 0');
        }

        $query->whereIn('service_requests.category_id', $this->leafCategoryIds($provider));

        if (! $provider->serves_whole_country) {
            $query->whereIn('service_requests.city_id', $this->serviceAreaIds($provider));
        }

        return $query;
    }

    /**
     * Teikėjo pasirinktos kategorijos, išskleistos iki 3 lygio (filtrų sąrašui ir srautui).
     *
     * @return list<int>
     */
    public function leafCategoryIds(ProviderProfile $provider): array
    {
        $selected = fn (QueryBuilder $query) => $query
            ->select('category_id')
            ->from('category_provider_profile')
            ->where('provider_profile_id', $provider->id);

        return array_values(Category::query()
            ->where('depth', Category::MAX_DEPTH)
            ->where(fn (Builder $query) => $query
                ->whereIn('id', $selected)
                ->orWhereIn('parent_id', $selected)
                ->orWhereIn('parent_id', Category::query()->select('id')->whereIn('parent_id', $selected)))
            ->pluck('id')
            ->all());
    }

    /**
     * Teikėjo zonų savivaldybės.
     *
     * @return list<int>
     */
    public function serviceAreaIds(ProviderProfile $provider): array
    {
        return array_values(DB::table('city_provider_profile')
            ->where('provider_profile_id', $provider->id)
            ->pluck('city_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all());
    }

    /**
     * Kategorija ir jos protėviai viena užklausa (savęs JOIN): [lapas, tėvas, senelis].
     *
     * @return list<int>
     */
    public function categoryWithAncestors(int $categoryId): array
    {
        $row = DB::table('categories as leaf')
            ->leftJoin('categories as parent', 'parent.id', '=', 'leaf.parent_id')
            ->where('leaf.id', $categoryId)
            ->first(['leaf.id', 'leaf.parent_id', 'parent.parent_id as grandparent_id']);

        if ($row === null) {
            return [];
        }

        return array_values(array_map('intval', array_filter(
            [$row->id, $row->parent_id, $row->grandparent_id],
            fn (mixed $id): bool => $id !== null,
        )));
    }
}
