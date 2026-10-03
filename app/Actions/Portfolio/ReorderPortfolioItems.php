<?php

namespace App\Actions\Portfolio;

use App\Models\ProviderProfile;
use Illuminate\Support\Facades\DB;

/**
 * Nauja atliktų darbų tvarka: sort_order = pozicija sąraše.
 * Atnaujinami tik šio teikėjo darbai (where provider_profile_id), todėl svetimo ID atsiųsti nepavyks.
 */
class ReorderPortfolioItems
{
    /**
     * @param  list<int>  $orderedIds
     */
    public function handle(ProviderProfile $profile, array $orderedIds): void
    {
        DB::transaction(function () use ($profile, $orderedIds): void {
            foreach ($orderedIds as $position => $id) {
                $profile->portfolioItems()->reorder()->whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });
    }
}
