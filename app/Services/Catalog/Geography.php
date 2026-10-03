<?php

namespace App\Services\Catalog;

use App\Models\City;
use App\Models\Region;
use Collator;

/**
 * Apskritys ir savivaldybės atmintyje (docs/DB_SCHEMA.md 2.3). Lentelėse tik 10 ir 60 eilučių,
 * todėl visa geografija laikoma cache, o paieška vyksta PHP'e.
 */
final class Geography
{
    /** @var array<int, CachedCity> surikiuota pagal sort_order (didmiesčiai pirmi) */
    private array $citiesById = [];

    /** @var array<string, int> */
    private array $cityIdBySlug = [];

    /**
     * @param  array<int, string>  $regionNames  apskrities id → pavadinimas
     * @param  list<CachedCity>  $cities  surikiuotos pagal sort_order
     */
    public function __construct(private array $regionNames, array $cities)
    {
        foreach ($cities as $city) {
            $this->citiesById[$city->id] = $city;
            $this->cityIdBySlug[$city->slug] = $city->id;
        }
    }

    /**
     * Duomenys cache'ui: tik skaliarai masyvuose.
     *
     * @return array{regions: array<int, string>, cities: list<array{id: int, region_id: int, name: string, name_locative: string, slug: string, sort_order: int}>}
     */
    public static function loadRows(): array
    {
        return [
            'regions' => Region::query()
                ->get(['id', 'name'])
                ->mapWithKeys(fn (Region $region): array => [$region->id => $region->name])
                ->all(),
            'cities' => array_values(City::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'region_id', 'name', 'name_locative', 'slug', 'sort_order'])
                ->map(fn (City $city): array => [
                    'id' => $city->id,
                    'region_id' => $city->region_id,
                    'name' => $city->name,
                    'name_locative' => $city->name_locative,
                    'slug' => $city->slug,
                    'sort_order' => $city->sort_order,
                ])
                ->all()),
        ];
    }

    /**
     * @param  array{regions: array<int, string>, cities: list<array{id: int, region_id: int, name: string, name_locative: string, slug: string, sort_order: int}>}  $rows
     */
    public static function fromRows(array $rows): self
    {
        return new self($rows['regions'], array_map(CachedCity::fromArray(...), $rows['cities']));
    }

    public function findCity(int $id): ?CachedCity
    {
        return $this->citiesById[$id] ?? null;
    }

    public function findCityBySlug(string $slug): ?CachedCity
    {
        $id = $this->cityIdBySlug[$slug] ?? null;

        return $id === null ? null : $this->citiesById[$id];
    }

    /**
     * @return list<string>
     */
    public function citySlugs(): array
    {
        return array_keys($this->cityIdBySlug);
    }

    /**
     * Didžiausi miestai (sort_order – vieta pagal gyventojų skaičių).
     *
     * @return list<CachedCity>
     */
    public function popularCities(int $limit): array
    {
        return array_slice(array_values($this->citiesById), 0, $limit);
    }

    /**
     * Visos savivaldybės abėcėlės tvarka pasirinkimo laukui. Collator rikiuoja pagal lietuvių
     * abėcėlę (Č po C, Š po S, Ž po Z); paprastas sort() „Šiauliai" nukeltų į sąrašo galą.
     *
     * @return list<array{name: string, slug: string, name_locative: string, region: string}>
     */
    public function cityOptions(): array
    {
        $cities = array_values($this->citiesById);
        $collator = new Collator('lt_LT');
        usort($cities, fn (CachedCity $a, CachedCity $b): int => (int) $collator->compare($a->name, $b->name));

        return array_map(fn (CachedCity $city): array => [
            ...$city->toOption(),
            'region' => $this->regionNames[$city->regionId] ?? '',
        ], $cities);
    }
}
