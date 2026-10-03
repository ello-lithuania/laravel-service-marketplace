<?php

namespace App\Models;

use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Pokalbis tarp kliento ir teikėjo dėl pasiūlymo (docs/DB_SCHEMA.md → conversations).
 */
#[Fillable(['service_request_id', 'offer_id', 'last_message_at'])]
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ServiceRequest, $this> */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /** @return BelongsTo<Offer, $this> */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    /**
     * Dalyviai su paskutine perskaityta žinute (pivot conversation_user).
     *
     * @return BelongsToMany<User, $this>
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('last_read_message_id');
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    // -------------------------------------------------------------------------
    // Žinutės (Etapas 6)
    // -------------------------------------------------------------------------

    /**
     * Paskutinė žinutė pokalbių sąrašui. latestOfMany() – „vienas iš daugelio" ryšys: eager loading'as visam
     * puslapiui paima po vieną (didžiausio id) žinutę kiekvienam pokalbiui viena užklausa.
     * https://laravel.com/docs/13.x/eloquent-relationships#has-one-of-many
     *
     * @return HasOne<Message, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * Ar vartotojas – pokalbio dalyvis. Jei dalyviai jau užkrauti, DB nebeklausiam.
     */
    public function hasParticipant(User $user): bool
    {
        if ($this->relationLoaded('participants')) {
            return $this->participants->contains('id', $user->id);
        }

        return $this->participants()->whereKey($user->id)->exists();
    }
}
