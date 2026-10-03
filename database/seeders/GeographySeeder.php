<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Region;
use Database\Seeders\Support\ReferenceData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * 10 apskričių ir 60 savivaldybių iš database/data/cities.php.
 * Idempotentiškas: updateOrCreate pagal slug, todėl galima leisti kelis kartus.
 */
class GeographySeeder extends Seeder
{
    public function run(): void
    {
        $data = ReferenceData::cities();

        // Didmiesčiai sąrašo viršuje: sort_order = vieta pagal gyventojų skaičių
        $rankByName = collect($data)->flatten(1)
            ->sortByDesc(fn (array $city) => $city[4])
            ->values()
            ->mapWithKeys(fn (array $city, int $rank) => [$city[0] => $rank]);

        $regionOrder = 0;

        foreach ($data as $regionName => $cities) {
            $region = Region::query()->updateOrCreate(
                ['slug' => Str::slug($regionName)],
                ['name' => $regionName, 'sort_order' => $regionOrder++],
            );

            foreach ($cities as [$name, $locative, $latitude, $longitude]) {
                City::query()->updateOrCreate(['slug' => Str::slug($name)], [
                    'region_id' => $region->id,
                    'name' => $name,
                    'name_locative' => $locative,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'sort_order' => $rankByName[$name],
                ]);
            }
        }
    }
}
