<?php

namespace App\Models;

use Database\Factories\PortfolioItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Teikėjo atliktas darbas (nuotraukos – medialibrary, Etapas 3).
 */
#[Fillable(['category_id', 'city_id', 'title', 'description', 'completed_date', 'sort_order'])]
class PortfolioItem extends Model
{
    /** @use HasFactory<PortfolioItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'completed_date' => 'date',
        ];
    }

    /** @return BelongsTo<ProviderProfile, $this> */
    public function providerProfile(): BelongsTo
    {
        return $this->belongsTo(ProviderProfile::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
