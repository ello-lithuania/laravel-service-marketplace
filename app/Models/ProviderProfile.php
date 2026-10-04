<?php

namespace App\Models;

use App\Enums\ProviderSort;
use App\Enums\ProviderStatus;
use App\Enums\ProviderType;
use App\Services\Catalog\SearchTerms;
use Database\Factories\ProviderProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Teikėjo vieši ir verslo duomenys, 1:1 su User (docs/DB_SCHEMA.md → provider_profiles).
 *
 * Ne Fillable: user_id (nustatomas per $user->providerProfile()->create()), status, verified_at
 * ir denormalizuoti laukai (credits_balance, rating_avg…) – juos keičia tik sistema.
 *
 * Enum tipai PHPStan'ui (jis neskaito casts() metodo, todėl be šių eilučių laikytų juos string):
 *
 * @property ProviderType $type
 * @property ProviderStatus $status
 */
#[Fillable([
    'type', 'display_name', 'slug', 'headline', 'description', 'city_id', 'company_code',
    'vat_code', 'website', 'years_experience', 'serves_whole_country',
])]
class ProviderProfile extends Model implements HasMedia
{
    /** @use HasFactory<ProviderProfileFactory> */
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => ProviderType::class,
            'status' => ProviderStatus::class,
            'serves_whole_country' => 'boolean',
            'verified_at' => 'datetime',
            'credits_balance' => 'integer',
            'rating_avg' => 'decimal:2',
            'reviews_count' => 'integer',
            'completed_jobs_count' => 'integer',
            'last_active_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Bazinė vieta.
     *
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Teikiamos paslaugos su kaina „nuo" (pivot category_provider_profile).
     *
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withPivot('price_from_cents', 'price_unit');
    }

    /**
     * Aptarnavimo zonos (pivot city_provider_profile).
     *
     * @return BelongsToMany<City, $this>
     */
    public function serviceAreas(): BelongsToMany
    {
        return $this->belongsToMany(City::class);
    }

    /** @return HasMany<PortfolioItem, $this> */
    public function portfolioItems(): HasMany
    {
        return $this->hasMany(PortfolioItem::class)->orderBy('sort_order');
    }

    /** @return HasMany<Offer, $this> */
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    /** @return HasMany<Review, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** @return HasMany<CreditTransaction, $this> */
    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    /** @return MorphMany<Complaint, $this> */
    public function complaints(): MorphMany
    {
        return $this->morphMany(Complaint::class, 'reportable');
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * Ar užpildyti privalomi vedlio žingsniai: duomenys (profilis jau yra), bent viena kategorija
     * ir aptarnavimo zona („visa Lietuva" arba bent viena savivaldybė). Kainos – neprivalomos.
     */
    public function hasRequiredSteps(): bool
    {
        return $this->categories()->exists()
            && ($this->serves_whole_country || $this->serviceAreas()->exists());
    }

    // --- Failai (spatie/laravel-medialibrary, docs/DB_SCHEMA.md → media) -------------

    /**
     * logo ir cover – po vieną failą. Miniatiūros daromos iškart (nonQueued):
     * failas vienas, o teikėjas rezultatą nori matyti tuoj pat po įkėlimo.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->registerMediaConversions(function (): void {
                // Fit::Max – sumažina iki 256×256 neiškirpdamas (logotipo kraštų nukirpti negalima)
                $this->addMediaConversion('thumb')
                    ->nonQueued()
                    ->fit(Fit::Max, 256, 256);
            });

        $this->addMediaCollection('cover')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->registerMediaConversions(function (): void {
                // Fit::Crop – užpildo visą 1200×400 plotą, perteklių iškerpa per vidurį
                $this->addMediaConversion('wide')
                    ->nonQueued()
                    ->fit(Fit::Crop, 1200, 400);
            });
    }

    public function logoUrl(): ?string
    {
        $url = $this->getFirstMediaUrl('logo', 'thumb');

        return $url !== '' ? $url : null;
    }

    public function coverUrl(): ?string
    {
        $url = $this->getFirstMediaUrl('cover', 'wide');

        return $url !== '' ? $url : null;
    }

    /**
     * @param  Builder<ProviderProfile>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', ProviderStatus::Active);
    }

    // -------------------------------------------------------------------------
    // Katalogas ir paieška (Etapas 4): teikėjų atrankos ir rikiavimo scope'ai
    // -------------------------------------------------------------------------

    /** Stulpeliai, kuriems MySQL turi FULLTEXT indeksą (migracija create_provider_profiles_table). */
    public const FULLTEXT_COLUMNS = ['display_name', 'headline', 'description'];

    /**
     * Teikėjai, pasirinkę bent vieną iš nurodytų kategorijų (sąrašą paruošia CategoryTree::relatedIds()).
     *
     * IN su subužklausa per pivot lentelę, be JOIN: teikėjas su keliomis tinkamomis kategorijomis
     * nepasikartoja, o MySQL naudoja indeksą (category_id, provider_profile_id) – docs/DB_SCHEMA.md 8 sk. #2.
     *
     * @param  Builder<ProviderProfile>  $query
     * @param  list<int>  $categoryIds
     */
    #[Scope]
    protected function inCategories(Builder $query, array $categoryIds): void
    {
        $query->whereIn('provider_profiles.id', fn (QueryBuilder $pivot) => $pivot
            ->select('provider_profile_id')
            ->from('category_provider_profile')
            ->whereIn('category_id', $categoryIds));
    }

    /**
     * Teikėjai, aptarnaujantys savivaldybę: ji yra teikėjo zonose arba teikėjas dirba visoje Lietuvoje.
     * Ta pati taisyklė kaip naujų užklausų atitikime (Etapas 5).
     *
     * @param  Builder<ProviderProfile>  $query
     */
    #[Scope]
    protected function servingCity(Builder $query, int $cityId): void
    {
        // Skliaustai (where su closure) būtini: be jų OR „pabėgtų" iš kitų sąlygų (status = active…)
        $query->where(fn (Builder $inner) => $inner
            ->where('provider_profiles.serves_whole_country', true)
            ->orWhereIn('provider_profiles.id', fn (QueryBuilder $pivot) => $pivot
                ->select('provider_profile_id')
                ->from('city_provider_profile')
                ->where('city_id', $cityId)));
    }

    /**
     * @param  Builder<ProviderProfile>  $query
     */
    #[Scope]
    protected function verified(Builder $query): void
    {
        $query->whereNotNull('provider_profiles.verified_at');
    }

    /**
     * @param  Builder<ProviderProfile>  $query
     */
    #[Scope]
    protected function withMinRating(Builder $query, float $rating): void
    {
        $query->where('provider_profiles.rating_avg', '>=', $rating);
    }

    /**
     * Paieška tekstu pavadinime, antraštėje ir aprašyme.
     *
     * MySQL – FULLTEXT indeksas (boolean režimas, žodžių pradžios), SQLite – LIKE (FULLTEXT nepalaiko).
     * whereFullText stulpeliai turi sutapti su indekso stulpeliais (migracija create_provider_profiles_table).
     *
     * @param  Builder<ProviderProfile>  $query
     */
    #[Scope]
    protected function matchingText(Builder $query, SearchTerms $terms): void
    {
        $booleanQuery = $terms->booleanQuery();

        if ($booleanQuery !== null && $query->getModel()->getConnection()->getDriverName() === 'mysql') {
            $query->whereFullText(self::FULLTEXT_COLUMNS, $booleanQuery, ['mode' => 'boolean']);

            return;
        }

        // Kiekvienas žodis turi būti bent viename stulpelyje: (pavadinimas LIKE … OR antraštė LIKE … OR …) AND …
        foreach ($terms->words as $word) {
            $query->where(function (Builder $inner) use ($word): void {
                foreach (self::FULLTEXT_COLUMNS as $column) {
                    $inner->orWhereLike('provider_profiles.'.$column, '%'.$word.'%');
                }
            });
        }
    }

    /**
     * Rikiavimas pagal pasirinktą variantą. Paskutinis rikiavimas visada pagal id: kai reitingai vienodi,
     * DB be jo gali grąžinti eilutes skirtinga tvarka, ir tas pats teikėjas atsidurtų dviejuose puslapiuose.
     *
     * @param  Builder<ProviderProfile>  $query
     */
    #[Scope]
    protected function sortedBy(Builder $query, ProviderSort $sort, ?SearchTerms $terms = null): void
    {
        match ($sort) {
            ProviderSort::Relevance => $this->orderByRelevance($query, $terms),
            ProviderSort::Rating => $query->orderByDesc('provider_profiles.rating_avg')
                ->orderByDesc('provider_profiles.reviews_count'),
            ProviderSort::Reviews => $query->orderByDesc('provider_profiles.reviews_count')
                ->orderByDesc('provider_profiles.rating_avg'),
            ProviderSort::CompletedJobs => $query->orderByDesc('provider_profiles.completed_jobs_count')
                ->orderByDesc('provider_profiles.rating_avg'),
            ProviderSort::Newest => $query->orderByDesc('provider_profiles.created_at'),
        };

        $query->orderByDesc('provider_profiles.id');
    }

    /**
     * MySQL – FULLTEXT atitikimo balas (MATCH … AGAINST), SQLite – paprasta taisyklė:
     * pirmas žodis pavadinime svarbiau nei antraštėje, o antraštėje – nei aprašyme.
     *
     * @param  Builder<ProviderProfile>  $query
     */
    private function orderByRelevance(Builder $query, ?SearchTerms $terms): void
    {
        $booleanQuery = $terms?->booleanQuery();

        if ($booleanQuery !== null && $query->getModel()->getConnection()->getDriverName() === 'mysql') {
            $query->orderByRaw(
                'MATCH ('.implode(', ', self::FULLTEXT_COLUMNS).') AGAINST (? IN BOOLEAN MODE) DESC',
                [$booleanQuery],
            );
        } elseif ($terms !== null) {
            $like = '%'.$terms->words[0].'%';
            $query->orderByRaw(
                'CASE WHEN provider_profiles.display_name LIKE ? THEN 0 WHEN provider_profiles.headline LIKE ? THEN 1 ELSE 2 END',
                [$like, $like],
            );
        }

        $query->orderByDesc('provider_profiles.rating_avg');
    }

    // -------------------------------------------------------------------------
    // Etapas 9c: prenumeratų privalumai (logika – App\Services\Subscriptions\PlanBenefits)
    // -------------------------------------------------------------------------

    /**
     * Dabar galiojančios prenumeratos (paprastai 0 arba 1). Atskiras ryšys, kad katalogo sąraše jas būtų galima
     * užkrauti viena užklausa visiems puslapio teikėjams: ->with('currentSubscriptions').
     *
     * @return HasMany<Subscription, $this>
     */
    public function currentSubscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class)->current();
    }
}
