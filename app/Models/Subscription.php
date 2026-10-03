<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
            'credits_granted_until' => 'datetime',
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

    // --- Etapas 7: prenumeratų gyvavimo ciklas ----------------------------------------

    /**
     * Šios prenumeratos laikotarpių mokėjimai (pirmas pirkimas ir pratęsimai).
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * „Gyvos" prenumeratos: galioja arba laukia apmokėjimo (active, cancelled, past_due).
     *
     * @param  Builder<Subscription>  $query
     */
    #[Scope]
    protected function live(Builder $query): void
    {
        $query->whereIn('status', SubscriptionStatus::live());
    }

    /**
     * Ar prenumerata šiuo metu suteikia plano naudą: jau prasidėjo, apmokėta iki ends_at ir neatšaukta galutinai.
     */
    public function isCurrent(): bool
    {
        return in_array($this->status, [SubscriptionStatus::Active, SubscriptionStatus::Cancelled], true)
            && $this->starts_at->isPast()
            && $this->ends_at->isFuture();
    }

    /**
     * Suplanuota: nupirkta, bet prasidės tik pasibaigus dabartinei (plano keitimas).
     */
    public function isScheduled(): bool
    {
        return $this->status === SubscriptionStatus::Active && $this->starts_at->isFuture();
    }
}
