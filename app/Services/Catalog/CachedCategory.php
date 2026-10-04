<?php

namespace App\Services\Catalog;

/**
 * Viena kategorija iš cache – paprasti duomenys, ne Eloquent modelis.
 *
 * Cache saugom masyvus, o ne modelius: config/cache.php → serializable_classes = false
 * neleidžia iš cache atkurti PHP objektų (apsauga nuo „gadget chain" atakų).
 */
final readonly class CachedCategory
{
    public function __construct(
        public int $id,
        public ?int $parentId,
        public int $depth,
        public string $name,
        public string $slug,
        public ?string $icon,
        public ?string $description,
        public ?string $metaTitle,
        public ?string $metaDescription,
        // Etapas 10: kategorijos nuotrauka (medialibrary „image"), null – nuotraukos nėra
        public ?string $imageUrl = null,
        public ?string $imageWideUrl = null,
    ) {}

    /**
     * @param  array{id: int, parent_id: int|null, depth: int, name: string, slug: string, icon: string|null, description: string|null, meta_title: string|null, meta_description: string|null, image_url?: string|null, image_wide_url?: string|null}  $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            id: $row['id'],
            parentId: $row['parent_id'],
            depth: $row['depth'],
            name: $row['name'],
            slug: $row['slug'],
            icon: $row['icon'],
            description: $row['description'],
            metaTitle: $row['meta_title'],
            metaDescription: $row['meta_description'],
            imageUrl: $row['image_url'] ?? null,
            imageWideUrl: $row['image_wide_url'] ?? null,
        );
    }

    /**
     * Trumpiausias pavidalas nuorodai Vue pusėje.
     *
     * @return array{id: int, name: string, slug: string}
     */
    public function toLink(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'slug' => $this->slug];
    }

    /**
     * Etapas 10: kortelė su nuotrauka ir ikona (pradžios puslapis, kategorijų sąrašai).
     *
     * @return array{id: int, name: string, slug: string, icon: string|null, image_url: string|null}
     */
    public function toCard(): array
    {
        return [...$this->toLink(), 'icon' => $this->icon, 'image_url' => $this->imageUrl];
    }
}
