<?php

namespace App\Actions\ProviderProfile;

use App\Models\Category;
use App\Models\ProviderProfile;
use App\Services\Subscriptions\PlanBenefits;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Vedlio 2 žingsnis: teikiamos kategorijos (pivot category_provider_profile).
 *
 * Taisyklė (docs/DB_SCHEMA.md 2.2): pasirinktas tėvas reiškia „visi jo vaikai". Todėl jei kartu su tėvu
 * atsiųsti ir jo vaikai, vaikus išmetam – DB lieka tik aukščiausi pasirinkti mazgai.
 *
 * Etapas 9c: kiek eilučių galima turėti, lemia prenumerata (PlanBenefits::maxCategories()).
 */
class SyncProviderCategories
{
    public function __construct(
        private ActivateCompletedProfile $activate,
        private PlanBenefits $benefits,
    ) {}

    /**
     * @param  list<int>  $categoryIds
     *
     * @throws ValidationException kai viršijama plano kategorijų riba
     */
    public function handle(ProviderProfile $profile, array $categoryIds): void
    {
        $categoryIds = $this->withoutCoveredDescendants($categoryIds);

        $this->ensureWithinLimit($profile, $categoryIds);

        DB::transaction(function () use ($profile, $categoryIds): void {
            // sync() ištrina nebepasirinktas eilutes, prideda naujas, o likusių nekeičia –
            // todėl jau įvestos kainos (price_from_cents) neprarandamos
            $profile->categories()->sync($categoryIds);

            $this->activate->handle($profile);
        });
    }

    /**
     * Etapas 9c: kategorijų riba pagal planą. Skaičiuojamos eilutės, kurios liks DB (po withoutCoveredDescendants),
     * todėl visa grupė – viena kategorija.
     *
     * Kas ribą jau viršija (seni duomenys, pasibaigusi prenumerata, sumažinta riba), tas savo kategorijas pasilieka:
     * leidžiam jas palikti ar pašalinti, bet ne pridėti naujų, kol iš viso nebus ne daugiau nei riba.
     * Kodėl tikrinam čia, o ne Form Request'e: riba priklauso nuo dabartinio pasirinkimo ir nuo sąrašo po
     * normalizavimo – tai verslo taisyklė, kaip kreditų pakankamumas SendOffer'yje.
     *
     * @param  list<int>  $categoryIds
     */
    private function ensureWithinLimit(ProviderProfile $profile, array $categoryIds): void
    {
        $limit = $this->benefits->maxCategories($profile);

        if (count($categoryIds) <= $limit) {
            return;
        }

        $current = $profile->categories()->pluck('categories.id')->all();

        if (array_diff($categoryIds, $current) === []) {
            return; // nieko naujo – tik paliko ar pašalino dalį senų
        }

        $replace = [
            'limit' => $limit,
            'noun' => trans_choice('plan_benefits.categories.noun_genitive', $limit),
        ];

        throw ValidationException::withMessages([
            'category_ids' => count($current) > $limit
                ? __('plan_benefits.categories.over_limit_legacy', [...$replace, 'count' => count($current)])
                : __('plan_benefits.categories.over_limit', [...$replace, 'count' => count($categoryIds)]),
        ]);
    }

    /**
     * @param  list<int>  $categoryIds
     * @return list<int>
     */
    private function withoutCoveredDescendants(array $categoryIds): array
    {
        // Visas medis – tik ~250 eilučių (id => parent_id), todėl protėvius randam PHP'e
        $parents = Category::query()->pluck('parent_id', 'id')->all();
        $selected = array_flip($categoryIds);

        return array_values(array_filter($categoryIds, function (int $id) use ($parents, $selected): bool {
            for ($parent = $parents[$id] ?? null; $parent !== null; $parent = $parents[$parent] ?? null) {
                if (isset($selected[$parent])) {
                    return false;
                }
            }

            return true;
        }));
    }
}
