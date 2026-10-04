<?php

namespace App\Models;

use App\Enums\BillingPeriod;
use App\Services\Subscriptions\PlanBenefits;
use App\Services\Subscriptions\PlanFeatures;
use Database\Factories\SubscriptionPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Prenumeratos planas.
 */
#[Fillable([
    'name', 'slug', 'description', 'price_cents', 'billing_period', 'credits_per_period',
    'features', 'is_active', 'sort_order',
])]
class SubscriptionPlan extends Model
{
    /** @use HasFactory<SubscriptionPlanFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'billing_period' => BillingPeriod::class,
            'credits_per_period' => 'integer',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** @return MorphMany<Payment, $this> */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'purchasable');
    }

    // --- Etapas 9c: prenumeratų privalumai --------------------------------------------

    /**
     * features JSON kaip tipizuotas objektas (max_categories, badge, priority_support).
     */
    public function planFeatures(): PlanFeatures
    {
        return PlanFeatures::fromArray($this->features);
    }

    protected static function booted(): void
    {
        // PlanBenefits planus laiko atmintyje visą užklausą – pakeitus planą, sąrašą perskaito iš naujo
        static::saved(fn () => app(PlanBenefits::class)->forget());
        static::deleted(fn () => app(PlanBenefits::class)->forget());
    }
}
