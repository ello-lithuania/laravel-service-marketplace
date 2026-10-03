<?php

namespace App\Services\Catalog;

/**
 * Savivaldybė iš cache (paprasti duomenys, ne Eloquent modelis).
 */
final readonly class CachedCity
{
    public function __construct(
        public int $id,
        public int $regionId,
        public string $name,
        public string $nameLocative,
        public string $slug,
        public int $sortOrder,
    ) {}

    /**
     * @param  array{id: int, region_id: int, name: string, name_locative: string, slug: string, sort_order: int}  $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            id: $row['id'],
            regionId: $row['region_id'],
            name: $row['name'],
            nameLocative: $row['name_locative'],
            slug: $row['slug'],
            sortOrder: $row['sort_order'],
        );
    }

    /**
     * @return array{name: string, slug: string, name_locative: string}
     */
    public function toOption(): array
    {
        return ['name' => $this->name, 'slug' => $this->slug, 'name_locative' => $this->nameLocative];
    }
}
