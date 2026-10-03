<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use App\Observers\ReviewObserver;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Kliento atsiliepimas apie teikėją (docs/DB_SCHEMA.md → reviews).
 * Su service_request_id – patvirtintas; be jo (NULL) – pagal teikėjo pakvietimą.
 *
 * Etapas 6: ReviewObserver po kiekvieno pokyčio perskaičiuoja teikėjo reitingą (eilėje).
 */
#[Fillable(['rating', 'comment'])]
#[ObservedBy(ReviewObserver::class)]
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'status' => ReviewStatus::class,
            'provider_replied_at' => 'datetime',
            'published_at' => 'datetime',
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

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return MorphMany<Complaint, $this> */
    public function complaints(): MorphMany
    {
        return $this->morphMany(Complaint::class, 'reportable');
    }

    /**
     * Ar darbas atliktas per platformą. Stulpelio is_verified nededam – tai pigiai apskaičiuojama.
     */
    public function isVerified(): bool
    {
        return $this->service_request_id !== null;
    }

    // -------------------------------------------------------------------------
    // Katalogas (Etapas 4)
    // -------------------------------------------------------------------------

    /**
     * Viešai rodomi atsiliepimai (ne paslėpti ir ne laukiantys moderavimo).
     *
     * @param  Builder<Review>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('reviews.status', ReviewStatus::Published);
    }

    // -------------------------------------------------------------------------
    // Atsiliepimų rašymas ir moderavimas (Etapas 6)
    // -------------------------------------------------------------------------

    /** Per kiek dienų po darbo užbaigimo klientas gali palikti patvirtintą atsiliepimą. */
    public const VERIFIED_WINDOW_DAYS = 60;

    /** Kiek dienų galioja teikėjo pakvietimo nuoroda. */
    public const INVITATION_LINK_DAYS = 30;

    /** Tas pats klientas tam pačiam teikėjui pagal pakvietimą gali rašyti ne dažniau kaip kartą per tiek dienų. */
    public const INVITATION_COOLDOWN_DAYS = 365;

    public function isPublished(): bool
    {
        return $this->status === ReviewStatus::Published;
    }
}
