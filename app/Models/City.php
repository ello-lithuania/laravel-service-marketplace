<?php

namespace App\Models;

use Database\Factories\CityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Savivaldybė, UI – „Miestas / rajonas" (docs/DB_SCHEMA.md → cities).
 */
#[Fillable(['region_id', 'name', 'name_locative', 'slug', 'latitude', 'longitude', 'sort_order'])]
#[WithoutTimestamps]
class City extends Model
{
    /** @use HasFactory<CityFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /** @return BelongsTo<Region, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Teikėjai, kurių bazinė vieta – ši savivaldybė.
     *
     * @return HasMany<ProviderProfile, $this>
     */
    public function providerProfiles(): HasMany
    {
        return $this->hasMany(ProviderProfile::class);
    }

    /**
     * Teikėjai, kurie aptarnauja šią savivaldybę (aptarnavimo zonos, pivot city_provider_profile).
     *
     * @return BelongsToMany<ProviderProfile, $this>
     */
    public function servingProviders(): BelongsToMany
    {
        return $this->belongsToMany(ProviderProfile::class);
    }

    /** @return HasMany<ServiceRequest, $this> */
    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }
}
