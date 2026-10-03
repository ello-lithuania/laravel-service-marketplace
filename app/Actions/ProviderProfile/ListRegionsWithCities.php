<?php

namespace App\Actions\ProviderProfile;

use App\Models\City;
use App\Models\Region;

/**
 * Apskritys su savivaldybėmis formoms (miesto pasirinkimas, aptarnavimo zonos).
 * Dvi SQL užklausos iš viso: regions + cities (eager loading per with()), ne 1 + 10.
 */
class ListRegionsWithCities
{
    /**
     * @return list<array{id: int, name: string, cities: list<array{id: int, name: string}>}>
     */
    public function handle(): array
    {
        return array_values(Region::query()
            ->with(['cities' => fn ($query) => $query->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Region $region): array => [
                'id' => $region->id,
                'name' => $region->name,
                'cities' => array_values($region->cities
                    ->map(fn (City $city): array => ['id' => $city->id, 'name' => $city->name])
                    ->all()),
            ])
            ->all());
    }
}
