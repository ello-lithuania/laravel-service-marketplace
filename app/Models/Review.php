<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Kliento atsiliepimas apie teikėją (docs/DB_SCHEMA.md → reviews).
 * Su service_request_id – patvirtintas; be jo (NULL) – pagal teikėjo pakvietimą.
 */
#[Fillable(['rating', 'comment'])]
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
}
