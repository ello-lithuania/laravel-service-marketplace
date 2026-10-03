<?php

namespace App\Actions\ProviderProfile;

use App\Models\Category;

/**
 * Aktyvių kategorijų medis vedlio formai: [{id, name, children: [...]}, ...].
 *
 * Visas medis paimamas viena užklausa (~250 eilučių) ir sudėliojamas PHP'e pagal parent_id
 * (docs/DB_SCHEMA.md 2.2 – adjacency list). Rekursinių SQL užklausų nereikia.
 */
class BuildCategoryTree
{
    /**
     * @return list<array{id: int, name: string, children: list<mixed>}>
     */
    public function handle(): array
    {
        $categories = Category::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name']);

        // parent_id => [vaikai]; 1 lygio kategorijos – po raktu 0
        $byParent = [];

        foreach ($categories as $category) {
            $byParent[$category->parent_id ?? 0][] = $category;
        }

        return $this->children($byParent, 0);
    }

    /**
     * @param  array<int, list<Category>>  $byParent
     * @return list<array{id: int, name: string, children: list<mixed>}>
     */
    private function children(array $byParent, int $parentId): array
    {
        return array_map(fn (Category $category): array => [
            'id' => $category->id,
            'name' => $category->name,
            'children' => $this->children($byParent, $category->id),
        ], $byParent[$parentId] ?? []);
    }
}
