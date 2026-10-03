<?php

namespace App\Models;

use App\Enums\ProviderStatus;
use App\Enums\ProviderType;
use Database\Factories\ProviderProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Teikėjo vieši ir verslo duomenys, 1:1 su User (docs/DB_SCHEMA.md → provider_profiles).
 *
 * Ne Fillable: user_id (nustatomas per $user->providerProfile()->create()), status, verified_at
 * ir denormalizuoti laukai (credits_balance, rating_avg…) – juos keičia tik sistema.
 */
#[Fillable([
    'type', 'display_name', 'slug', 'headline', 'description', 'city_id', 'company_code',
    'vat_code', 'website', 'years_experience', 'serves_whole_country',
])]
class ProviderProfile extends Model
{
    /** @use HasFactory<ProviderProfileFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => ProviderType::class,
            'status' => ProviderStatus::class,
            'serves_whole_country' => 'boolean',
            'verified_at' => 'datetime',
            'credits_balance' => 'integer',
            'rating_avg' => 'decimal:2',
            'reviews_count' => 'integer',
            'completed_jobs_count' => 'integer',
            'last_active_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Bazinė vieta.
     *
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Teikiamos paslaugos su kaina „nuo" (pivot category_provider_profile).
     *
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withPivot('price_from_cents', 'price_unit');
    }

    /**
     * Aptarnavimo zonos (pivot city_provider_profile).
     *
     * @return BelongsToMany<City, $this>
     */
    public function serviceAreas(): BelongsToMany
    {
        return $this->belongsToMany(City::class);
    }

    /** @return HasMany<PortfolioItem, $this> */
    public function portfolioItems(): HasMany
    {
        return $this->hasMany(PortfolioItem::class)->orderBy('sort_order');
    }

    /** @return HasMany<Offer, $this> */
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    /** @return HasMany<Review, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** @return HasMany<CreditTransaction, $this> */
    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    /** @return MorphMany<Complaint, $this> */
    public function complaints(): MorphMany
    {
        return $this->morphMany(Complaint::class, 'reportable');
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * @param  Builder<ProviderProfile>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', ProviderStatus::Active);
    }
}
