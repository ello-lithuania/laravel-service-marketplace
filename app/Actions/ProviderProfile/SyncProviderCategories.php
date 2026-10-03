<?php

namespace App\Actions\ProviderProfile;

use App\Models\Category;
use App\Models\ProviderProfile;
use Illuminate\Support\Facades\DB;

/**
 * Vedlio 2 žingsnis: teikiamos kategorijos (pivot category_provider_profile).
 *
 * Taisyklė (docs/DB_SCHEMA.md 2.2): pasirinktas tėvas reiškia „visi jo vaikai". Todėl jei kartu su tėvu
 * atsiųsti ir jo vaikai, vaikus išmetam – DB lieka tik aukščiausi pasirinkti mazgai.
 */
class SyncProviderCategories
{
    public function __construct(private ActivateCompletedProfile $activate) {}

    /**
     * @param  list<int>  $categoryIds
     */
    public function handle(ProviderProfile $profile, array $categoryIds): void
    {
        $categoryIds = $this->withoutCoveredDescendants($categoryIds);

        DB::transaction(function () use ($profile, $categoryIds): void {
            // sync() ištrina nebepasirinktas eilutes, prideda naujas, o likusių nekeičia –
            // todėl jau įvestos kainos (price_from_cents) neprarandamos
            $profile->categories()->sync($categoryIds);

            $this->activate->handle($profile);
        });
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
