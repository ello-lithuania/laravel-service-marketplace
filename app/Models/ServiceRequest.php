<?php

namespace App\Models;

use App\Enums\ServiceRequestStatus;
use App\Enums\StartPreference;
use Database\Factories\ServiceRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Kliento užklausa (darbas). Vadinasi ne Job/Request – tie vardai užimti Laravel (docs/DB_SCHEMA.md 2.4).
 *
 * Ne Fillable: client_id (per $user->serviceRequests()->create()), status, accepted_offer_id,
 * skaitliukai ir datos – juos keičia tik būsenų perėjimų Actions (docs/STATES.md).
 */
#[Fillable([
    'category_id', 'city_id', 'slug', 'title', 'description', 'address',
    'budget_min_cents', 'budget_max_cents', 'start_preference', 'start_date',
])]
class ServiceRequest extends Model
{
    /** @use HasFactory<ServiceRequestFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => ServiceRequestStatus::class,
            'start_preference' => StartPreference::class,
            'start_date' => 'date',
            'budget_min_cents' => 'integer',
            'budget_max_cents' => 'integer',
            'offers_count' => 'integer',
            'views_count' => 'integer',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            // Etapas 6: teikėjo prašymas užbaigti ir 60 d. priminimas
            'completion_requested_at' => 'datetime',
            'completion_reminded_at' => 'datetime',
        ];
    }

    /**
     * Užklausą sukūręs klientas (FK client_id, todėl nurodom jį aiškiai).
     *
     * @return BelongsTo<User, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
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

    /**
     * Laimėjęs pasiūlymas (žiedinė nuoroda į offers).
     *
     * @return BelongsTo<Offer, $this>
     */
    public function acceptedOffer(): BelongsTo
    {
        return $this->belongsTo(Offer::class, 'accepted_offer_id');
    }

    /** @return HasMany<Offer, $this> */
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    /** @return HasMany<Conversation, $this> */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /** @return HasOne<Review, $this> */
    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    /** @return MorphMany<Complaint, $this> */
    public function complaints(): MorphMany
    {
        return $this->morphMany(Complaint::class, 'reportable');
    }
}
