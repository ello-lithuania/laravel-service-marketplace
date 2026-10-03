<?php

namespace App\Models;

use App\Enums\ProviderStatus;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
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
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

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
class User extends Authenticatable implements FilamentUser, HasMedia, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, InteractsWithMedia, Notifiable, SoftDeletes;

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

    /**
     * Į Filament admin panelę – tik administratoriai su patvirtintu el. paštu ir neužblokuoti.
     * Be šio metodo Filament įleistų bet kurį vartotoją, bet tik lokalioje aplinkoje.
     * https://filamentphp.com/docs/5.x/users/overview#authorizing-access-to-the-panel
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin() && $this->hasVerifiedEmail() && $this->banned_at === null;
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

    /**
     * Teikėjas dar neužbaigė profilio vedlio (profilio nėra arba jis „pending").
     * Tokį teikėją po registracijos ir el. pašto patvirtinimo nukreipiam į vedlį.
     */
    public function needsProviderOnboarding(): bool
    {
        if (! $this->isProvider()) {
            return false;
        }

        $profile = $this->providerProfile;

        return $profile === null || $profile->status === ProviderStatus::Pending;
    }

    // --- Failai (spatie/laravel-medialibrary, docs/DB_SCHEMA.md → media) -------------

    /**
     * Avataras – viena nuotrauka: singleFile() įkėlus naują senąją ištrina pats.
     * Miniatiūra daroma iškart (nonQueued), nes ją rodom tame pačiame puslapyje po įkėlimo.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->registerMediaConversions(function (): void {
                $this->addMediaConversion('thumb')
                    ->nonQueued()
                    ->fit(Fit::Crop, 128, 128);
            });
    }

    /**
     * Avataro miniatiūros URL arba null, jei avataro nėra.
     */
    public function avatarUrl(): ?string
    {
        $url = $this->getFirstMediaUrl('avatar', 'thumb');

        return $url !== '' ? $url : null;
    }

    // --- Etapas 8: moderavimas ---------------------------------------------------

    /**
     * Užblokuotas administratoriaus (BanUser): negali prisijungti, teikėjo profilis – suspended.
     */
    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }
}
