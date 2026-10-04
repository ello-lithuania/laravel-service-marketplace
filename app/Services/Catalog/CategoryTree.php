<?php

namespace App\Services\Catalog;

use App\Models\Category;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Visas matomas kategorijų medis atmintyje (docs/DB_SCHEMA.md 2.2: adjacency list + cache).
 *
 * Kategorijų tik ~250, todėl tėvus, vaikus ir pomedžius pigiau rasti PHP'e, nei kiekvieną kartą klausti DB.
 * Matomos tik aktyvios kategorijos, kurių visi tėvai irgi aktyvūs: išjungus 1 lygį, dingsta ir jo vaikai.
 */
final class CategoryTree
{
    /** @var array<int, CachedCategory> */
    private array $byId = [];

    /** @var array<string, int> */
    private array $idBySlug = [];

    /** @var array<int, list<int>> tėvo id (0 – šaknys) → vaikų id nurodyta tvarka */
    private array $childIds = [];

    /**
     * @param  list<CachedCategory>  $categories  surikiuotos pagal depth, sort_order, name
     */
    public function __construct(array $categories)
    {
        foreach ($categories as $category) {
            // Tėvas visada apdorojamas anksčiau (rikiuota pagal depth). Jei jo nėra – jis išjungtas,
            // todėl ir šios kategorijos nerodom.
            if ($category->parentId !== null && ! isset($this->byId[$category->parentId])) {
                continue;
            }

            $this->byId[$category->id] = $category;
            $this->idBySlug[$category->slug] = $category->id;
            $this->childIds[$category->parentId ?? 0][] = $category->id;
        }
    }

    /**
     * Duomenys cache'ui: tik skaliarai masyvuose (jokių modelių ar Carbon objektų).
     *
     * @return list<array{id: int, parent_id: int|null, depth: int, name: string, slug: string, icon: string|null, description: string|null, meta_title: string|null, meta_description: string|null, image_url: string|null, image_wide_url: string|null}>
     */
    public static function loadRows(): array
    {
        $images = self::loadImageUrls();

        return array_values(Category::query()
            ->active()
            ->orderBy('depth')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'depth', 'name', 'slug', 'icon', 'description', 'meta_title', 'meta_description'])
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'parent_id' => $category->parent_id,
                'depth' => $category->depth,
                'name' => $category->name,
                'slug' => $category->slug,
                'icon' => $category->icon,
                'description' => $category->description,
                'meta_title' => $category->meta_title,
                'meta_description' => $category->meta_description,
                'image_url' => $images[$category->id]['card'] ?? null,
                'image_wide_url' => $images[$category->id]['wide'] ?? null,
            ])
            ->all());
    }

    /**
     * Etapas 10: kategorijų nuotraukų URL viena užklausa (category id => [card, wide]).
     * Miniatiūros dar nėra (pvz. ką tik įkelta) – rodomas originalas.
     *
     * @return array<int, array{card: string, wide: string}>
     */
    private static function loadImageUrls(): array
    {
        $urls = [];

        $media = Media::query()
            ->where('model_type', (new Category)->getMorphClass())
            ->where('collection_name', 'image')
            ->get();

        foreach ($media as $item) {
            $urls[(int) $item->model_id] = [
                'card' => $item->hasGeneratedConversion('card') ? $item->getUrl('card') : $item->getUrl(),
                'wide' => $item->hasGeneratedConversion('wide') ? $item->getUrl('wide') : $item->getUrl(),
            ];
        }

        return $urls;
    }

    /**
     * @param  list<array{id: int, parent_id: int|null, depth: int, name: string, slug: string, icon: string|null, description: string|null, meta_title: string|null, meta_description: string|null, image_url: string|null, image_wide_url: string|null}>  $rows
     */
    public static function fromRows(array $rows): self
    {
        return new self(array_map(CachedCategory::fromArray(...), $rows));
    }

    public function find(int $id): ?CachedCategory
    {
        return $this->byId[$id] ?? null;
    }

    public function findBySlug(string $slug): ?CachedCategory
    {
        $id = $this->idBySlug[$slug] ?? null;

        return $id === null ? null : $this->byId[$id];
    }

    /**
     * @return list<CachedCategory>
     */
    public function all(): array
    {
        return array_values($this->byId);
    }

    /**
     * 1 lygio kategorijos.
     *
     * @return list<CachedCategory>
     */
    public function roots(): array
    {
        return $this->children(null);
    }

    /**
     * @return list<CachedCategory>
     */
    public function children(?int $parentId): array
    {
        return array_map(fn (int $id): CachedCategory => $this->byId[$id], $this->childIds[$parentId ?? 0] ?? []);
    }

    /**
     * Tėvai nuo šaknies iki tiesioginio tėvo („duonos trupiniams").
     *
     * @return list<CachedCategory>
     */
    public function ancestors(int $id): array
    {
        $ancestors = [];
        $parentId = $this->find($id)?->parentId;

        while ($parentId !== null && isset($this->byId[$parentId])) {
            array_unshift($ancestors, $this->byId[$parentId]);
            $parentId = $this->byId[$parentId]->parentId;
        }

        return $ancestors;
    }

    /**
     * Visi vaikai ir vaikaičiai.
     *
     * @return list<int>
     */
    public function descendantIds(int $id): array
    {
        $ids = [];

        foreach ($this->childIds[$id] ?? [] as $childId) {
            $ids[] = $childId;
            array_push($ids, ...$this->descendantIds($childId));
        }

        return $ids;
    }

    /**
     * Kategorijos, kurias pasirinkęs teikėjas rodomas šios kategorijos puslapyje:
     * ji pati, jos tėvai (pasirinktas 2 lygis reiškia „visi jo vaikai") ir jos vaikai
     * (1–2 lygio puslapyje rodom ir siauresnių paslaugų teikėjus).
     *
     * @return list<int>
     */
    public function relatedIds(int $id): array
    {
        return [
            ...array_map(fn (CachedCategory $category): int => $category->id, $this->ancestors($id)),
            $id,
            ...$this->descendantIds($id),
        ];
    }

    /**
     * Kategorijos, kurių pavadinime yra visi paieškos žodžiai (paieškos puslapio pasiūlymams).
     * Žodžiai jau sutrumpinti iki šaknies (SearchTerms), todėl „plytelių" randa ir „Plytelių klijavimas".
     *
     * @param  list<string>  $words
     * @return list<CachedCategory>
     */
    public function search(array $words, int $limit): array
    {
        $found = array_filter($this->byId, function (CachedCategory $category) use ($words): bool {
            $name = mb_strtolower($category->name);

            foreach ($words as $word) {
                if (! str_contains($name, $word)) {
                    return false;
                }
            }

            return true;
        });

        // Siauresnės (3 lygio) kategorijos tikslesnės, todėl rodomos pirmos
        usort($found, fn (CachedCategory $a, CachedCategory $b): int => $b->depth <=> $a->depth);

        return array_slice($found, 0, $limit);
    }
}
