<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * Visi prisijungiantys žmonės: klientai, teikėjai, administratoriai (docs/DB_SCHEMA.md → users).
 *
 * @property int $id
 * @property UserRole $role
 * @property string $first_name
 * @property string $last_name
 * @property-read string $name
 * @property-read string $public_name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $phone
 * @property int|null $city_id
 * @property string $password
 * @property array<string, mixed>|null $notification_settings
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $banned_at
 * @property string|null $ban_reason
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * role sąmoningai nėra Fillable: rolę nustatom tik kode, kad jos nebūtų galima „atsiųsti" per formą.
 */
#[Fillable(['first_name', 'last_name', 'email', 'phone', 'city_id', 'password', 'notification_settings'])]
#[Hidden(['password', 'remember_token'])]
#[Appends(['name'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notification_settings' => 'array',
            'last_seen_at' => 'datetime',
            'banned_at' => 'datetime',
        ];
    }

    /**
     * Pilnas vardas „Jonas Petraitis". DB tokio stulpelio nėra – skaičiuojama (accessor).
     * Vadinasi „name", kad veiktų Filament ir starter kit komponentai.
     *
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->first_name.' '.$this->last_name));
    }

    /**
     * Viešai rodomas vardas „Jonas P." – pavardė neatskleidžiama.
     *
     * @return Attribute<string, never>
     */
    protected function publicName(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->first_name.' '.mb_substr($this->last_name, 0, 1).'.'));
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** @return HasOne<ProviderProfile, $this> */
    public function providerProfile(): HasOne
    {
        return $this->hasOne(ProviderProfile::class);
    }

    /**
     * Kliento sukurtos užklausos (FK client_id).
     *
     * @return HasMany<ServiceRequest, $this>
     */
    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'client_id');
    }

    /**
     * Teikėjo pasiūlymai „per" jo profilį: users → provider_profiles → offers.
     *
     * @return HasManyThrough<Offer, ProviderProfile, $this>
     */
    public function offers(): HasManyThrough
    {
        return $this->hasManyThrough(Offer::class, ProviderProfile::class);
    }

    /**
     * @return BelongsToMany<Conversation, $this>
     */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class)->withPivot('last_read_message_id');
    }

    /** @return HasMany<Message, $this> */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /**
     * Šio vartotojo parašyti atsiliepimai (FK author_id).
     *
     * @return HasMany<Review, $this>
     */
    public function writtenReviews(): HasMany
    {
        return $this->hasMany(Review::class, 'author_id');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Šio vartotojo pateikti skundai (FK reporter_id).
     *
     * @return HasMany<Complaint, $this>
     */
    public function filedComplaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'reporter_id');
    }

    /**
     * Skundai dėl šio vartotojo (jis – skundo objektas, polimorfinis ryšys).
     *
     * @return MorphMany<Complaint, $this>
     */
    public function complaints(): MorphMany
    {
        return $this->morphMany(Complaint::class, 'reportable');
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isProvider(): bool
    {
        return $this->role === UserRole::Provider;
    }

    public function isClient(): bool
    {
        return $this->role === UserRole::Client;
    }
}
