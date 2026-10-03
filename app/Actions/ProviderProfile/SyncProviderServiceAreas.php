<?php

namespace App\Actions\ProviderProfile;

use App\Models\ProviderProfile;
use Illuminate\Support\Facades\DB;

/**
 * Vedlio 3 žingsnis: aptarnavimo zonos (pivot city_provider_profile) arba „visa Lietuva".
 * Taisyklė (docs/DB_SCHEMA.md → city_provider_profile): kai serves_whole_country = true, zonų eilučių nekuriam.
 */
class SyncProviderServiceAreas
{
    public function __construct(private ActivateCompletedProfile $activate) {}

    /**
     * @param  list<int>  $cityIds
     */
    public function handle(ProviderProfile $profile, bool $wholeCountry, array $cityIds): void
    {
        DB::transaction(function () use ($profile, $wholeCountry, $cityIds): void {
            $profile->update(['serves_whole_country' => $wholeCountry]);
            $profile->serviceAreas()->sync($wholeCountry ? [] : $cityIds);

            $this->activate->handle($profile);
        });
    }
}
