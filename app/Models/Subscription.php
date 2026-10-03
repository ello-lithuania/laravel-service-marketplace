<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Teikėjo prenumerata. Pratęsiant ends_at pastumiama (docs/DB_SCHEMA.md → subscriptions).
 */
#[Fillable(['subscription_plan_id', 'status', 'starts_at', 'ends_at', 'cancelled_at', 'auto_renew'])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'auto_renew' => 'boolean',
        ];
    }

    /** @return BelongsTo<ProviderProfile, $this> */
    public function providerProfile(): BelongsTo
    {
        return $this->belongsTo(ProviderProfile::class);
    }

    /** @return BelongsTo<SubscriptionPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /** @return MorphMany<CreditTransaction, $this> */
    public function creditTransactions(): MorphMany
    {
        return $this->morphMany(CreditTransaction::class, 'source');
    }
}
