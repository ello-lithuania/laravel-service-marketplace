<?php

namespace App\Models;

use App\Enums\OfferPriceType;
use App\Enums\OfferStatus;
use Database\Factories\OfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Teikėjo pasiūlymas užklausai (docs/DB_SCHEMA.md → offers).
 *
 * Ne Fillable: service_request_id, provider_profile_id, status, credits_spent, viewed_at, responded_at.
 */
#[Fillable(['message', 'price_cents', 'price_type', 'duration_text', 'start_date'])]
class Offer extends Model
{
    /** @use HasFactory<OfferFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price_type' => OfferPriceType::class,
            'status' => OfferStatus::class,
            'price_cents' => 'integer',
            'credits_spent' => 'integer',
            'start_date' => 'date',
            'viewed_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ServiceRequest, $this> */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /** @return BelongsTo<ProviderProfile, $this> */
    public function providerProfile(): BelongsTo
    {
        return $this->belongsTo(ProviderProfile::class);
    }

    /** @return HasOne<Conversation, $this> */
    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }

    /**
     * Kreditų operacijos, kurių šaltinis – šis pasiūlymas (nurašymas, grąžinimas).
     *
     * @return MorphMany<CreditTransaction, $this>
     */
    public function creditTransactions(): MorphMany
    {
        return $this->morphMany(CreditTransaction::class, 'source');
    }

    /** @return MorphMany<Complaint, $this> */
    public function complaints(): MorphMany
    {
        return $this->morphMany(Complaint::class, 'reportable');
    }
}
