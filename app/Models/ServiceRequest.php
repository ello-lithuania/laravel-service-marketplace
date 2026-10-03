<?php

namespace App\Models;

use App\Enums\ServiceRequestStatus;
use App\Enums\StartPreference;
use App\Support\PrivateMedia;
use Carbon\CarbonInterface;
use Database\Factories\ServiceRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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
class ServiceRequest extends Model implements HasMedia
{
    /** @use HasFactory<ServiceRequestFactory> */
    use HasFactory, InteractsWithMedia, SoftDeletes;

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

    // -------------------------------------------------------------------------
    // Nuotraukos (Etapas 6, docs/DB_SCHEMA.md → media)
    // -------------------------------------------------------------------------

    /** Daugiausia nuotraukų vienoje užklausoje. */
    public const MAX_PHOTOS = 8;

    /**
     * Nuotraukos – privačiame diske: jose gali matytis kliento namai, todėl jas atiduoda PrivateMediaController
     * tik tiems, kas mato užklausą (ServiceRequestPolicy::view – klientas ir tinkami teikėjai).
     * Miniatiūros – eilėje (kaip portfolio): kol jų nėra, rodomas originalas.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')
            ->useDisk('local')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->registerMediaConversions(function (): void {
                $this->addMediaConversion('thumb')->fit(Fit::Crop, 480, 360);
                $this->addMediaConversion('large')->fit(Fit::Max, 1600, 1600);
            });
    }

    /**
     * Nuotraukos Inertia puslapiui (media ryšys turi būti užkrautas – be N+1).
     *
     * @return list<array{id: int, name: string, mime: string, size: int, is_image: bool, thumb_url: string|null, url: string}>
     */
    public function photosForInertia(): array
    {
        return array_values($this->getMedia('photos')
            ->map(fn (Media $media): array => PrivateMedia::toArray($media, 'thumb', 'large'))
            ->all());
    }

    /**
     * Ar dar galima keisti nuotraukas: kol užklausa laukia patvirtinimo arba pasiūlymų.
     */
    public function acceptsPhotoChanges(): bool
    {
        return in_array($this->status, [ServiceRequestStatus::Pending, ServiceRequestStatus::Open], true);
    }

    // -------------------------------------------------------------------------
    // Darbo užbaigimo priminimai (Etapas 6, docs/STATES.md 1 sk. „Papildomos taisyklės")
    // -------------------------------------------------------------------------

    /** Teikėjas gali pakartotinai paprašyti pažymėti darbą atliktu ne dažniau kaip kas tiek dienų. */
    public const COMPLETION_REQUEST_COOLDOWN_DAYS = 3;

    /** Po tiek dienų vykdymo klientui vieną kartą primenama pažymėti darbą atliktu. */
    public const COMPLETION_REMINDER_AFTER_DAYS = 60;

    /**
     * Kada teikėjas vėl galės paprašyti (null – jau dabar).
     */
    public function nextCompletionRequestAt(): ?CarbonInterface
    {
        $next = $this->completion_requested_at?->addDays(self::COMPLETION_REQUEST_COOLDOWN_DAYS);

        return $next !== null && $next->isFuture() ? $next : null;
    }
}
