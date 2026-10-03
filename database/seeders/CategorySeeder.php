<?php

namespace Database\Seeders;

use App\Models\Category;
use Database\Seeders\Support\ReferenceData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * 3 lygių kategorijų medis iš database/data/categories.php (rekursiškai).
 * offer_cost_credits paveldimas: lapas → 2 lygis → 1 lygis.
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (ReferenceData::categories() as $sort => $root) {
            $this->createNode($root, null, 1, $sort, $root['cost']);
        }
    }

    /**
     * @param  string|array{name: string, icon?: string, cost?: int, children?: list<mixed>}  $node
     */
    private function createNode(string|array $node, ?Category $parent, int $depth, int $sort, int $inheritedCost): void
    {
        $data = is_string($node) ? ['name' => $node] : $node;
        $cost = $data['cost'] ?? $inheritedCost;

        $category = Category::query()->updateOrCreate(['slug' => Str::slug($data['name'])], [
            'parent_id' => $parent?->id,
            'depth' => $depth,
            'name' => $data['name'],
            'icon' => $data['icon'] ?? null,
            'offer_cost_credits' => $cost,
            'sort_order' => $sort,
            'is_active' => true,
        ]);

        foreach ($data['children'] ?? [] as $childSort => $child) {
            /** @var string|array{name: string, cost?: int, children?: list<mixed>} $child */
            $this->createNode($child, $category, $depth + 1, $childSort, $cost);
        }
    }
}
