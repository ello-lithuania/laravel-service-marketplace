<?php

namespace App\Http\Resources\Catalog;

use App\Models\PortfolioItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Atliktas darbas teikėjo profilyje.
 *
 * @mixin PortfolioItem
 *
 * @property PortfolioItem $resource
 */
class PortfolioItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $item = $this->resource;

        return [
            'id' => $item->id,
            'title' => $item->title,
            'description' => $item->description,
            'completed_date' => $item->completed_date?->toDateString(),
            'category' => $item->category?->name,
            'city' => $item->city?->name,
            // Etapas 3: nuotraukos iš medialibrary kolekcijos „images" ([{url, thumb_url}]); kol kas tuščia
            'images' => [],
        ];
    }
}
