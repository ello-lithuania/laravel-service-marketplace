<?php

namespace App\Models;

use Database\Factories\RegionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Apskritis (docs/DB_SCHEMA.md → regions). Žinyninė lentelė – be timestamps.
 */
#[Fillable(['name', 'slug', 'sort_order'])]
#[WithoutTimestamps]
class Region extends Model
{
    /** @use HasFactory<RegionFactory> */
    use HasFactory;

    /** @return HasMany<City, $this> */
    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }
}
