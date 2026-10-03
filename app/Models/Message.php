<?php

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Pokalbio žinutė. sender_id = NULL – sisteminė žinutė.
 */
#[Fillable(['sender_id', 'body'])]
class Message extends Model implements HasMedia
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory, InteractsWithMedia, SoftDeletes;

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** @return MorphMany<Complaint, $this> */
    public function complaints(): MorphMany
    {
        return $this->morphMany(Complaint::class, 'reportable');
    }

    public function isSystem(): bool
    {
        return $this->sender_id === null;
    }

    // -------------------------------------------------------------------------
    // Priedai (Etapas 6, docs/DB_SCHEMA.md → media)
    // -------------------------------------------------------------------------

    /** Daugiausia priedų vienoje žinutėje. */
    public const MAX_ATTACHMENTS = 5;

    /** Leidžiami priedų tipai: nuotraukos ir PDF (tikrinamas turinys, ne plėtinys). */
    public const ATTACHMENT_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    /**
     * Priedai – privačiame diske (local = storage/app/private): pokalbis privatus, todėl failą atiduoda
     * PrivateMediaController, patikrinęs, ar žiūrintysis – pokalbio dalyvis (MessagePolicy::view).
     * Miniatiūra – tik nuotraukoms; PDF'ui jos nedarom (reikėtų Ghostscript/Imagick).
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')
            ->useDisk('local')
            ->acceptsMimeTypes(self::ATTACHMENT_MIME_TYPES)
            ->registerMediaConversions(function (?Media $media = null): void {
                if ($media !== null && ! str_starts_with($media->mime_type, 'image/')) {
                    return;
                }

                $this->addMediaConversion('thumb')->fit(Fit::Crop, 480, 360);
            });
    }
}
