<?php

namespace App\Http\Resources\Catalog\Concerns;

use App\Enums\PriceUnit;
use App\Models\Category;

/**
 * Kaina „nuo" iš pivot lentelės category_provider_profile (price_from_cents, price_unit).
 */
trait ReadsServicePrice
{
    /**
     * @return array{cents: int, unit: string|null}|null
     */
    protected function servicePrice(Category $category): ?array
    {
        // Pivot eilutė pridedama kaip ryšys „pivot", kai kategorija užkrauta per belongsToMany
        $pivot = $category->getRelation('pivot');
        $cents = $pivot?->getAttribute('price_from_cents');
        $unit = $pivot?->getAttribute('price_unit');

        if (! is_numeric($cents)) {
            return null;
        }

        return [
            'cents' => (int) $cents,
            'unit' => is_string($unit) ? PriceUnit::tryFrom($unit)?->label() : null,
        ];
    }
}
