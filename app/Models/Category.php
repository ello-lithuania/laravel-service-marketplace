<?php

namespace App\Models;

use App\Observers\CatalogCacheObserver;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 3 lygių paslaugų medis: parent_id + depth (docs/DB_SCHEMA.md 2.2).
 * Užklausos visada priskiriamos 3 lygiui; teikėjas gali pasirinkti bet kurį lygį.
 */
#[Fillable([
    'parent_id', 'depth', 'name', 'slug', 'description', 'icon', 'offer_cost_credits',
    'sort_order', 'is_active', 'meta_title', 'meta_description',
])]
// Pakeitus įrašą išvalomas katalogo cache (Etapas 4)
#[ObservedBy([CatalogCacheObserver::class])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    public const MAX_DEPTH = 3;

    /**
     * Lygis (depth) visada skaičiuojamas iš tėvo, kad nesiderintų su medžiu (pvz. kuriant per Filament).
     * Užklausa, o ne $category->parent, nes preventLazyLoading neleidžia tyliai užkrauti ryšio.
     */
    protected static function booted(): void
    {
        static::saving(function (Category $category): void {
            $parentDepth = $category->parent_id === null
                ? 0
                : max(0, (int) static::query()->whereKey($category->parent_id)->value('depth'));

            $category->depth = $parentDepth + 1;
        });
    }

    protected function casts(): array
    {
        return [
            'depth' => 'integer',
            'offer_cost_credits' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /** @return HasMany<Category, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Teikėjai, pasirinkę šią kategoriją, su kaina „nuo" (pivot category_provider_profile).
     *
     * @return BelongsToMany<ProviderProfile, $this>
     */
    public function providerProfiles(): BelongsToMany
    {
        return $this->belongsToMany(ProviderProfile::class)->withPivot('price_from_cents', 'price_unit');
    }

    /** @return HasMany<ServiceRequest, $this> */
    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    /** @return HasMany<PortfolioItem, $this> */
    public function portfolioItems(): HasMany
    {
        return $this->hasMany(PortfolioItem::class);
    }

    public function isLeaf(): bool
    {
        return $this->depth === self::MAX_DEPTH;
    }

    /**
     * Tik aktyvios kategorijos: Category::active()->get().
     *
     * @param  Builder<Category>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Pirmo lygio kategorijos: Category::roots()->get().
     *
     * @param  Builder<Category>  $query
     */
    #[Scope]
    protected function roots(Builder $query): void
    {
        $query->whereNull('parent_id');
    }
}
