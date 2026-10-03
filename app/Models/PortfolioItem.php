<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\PortfolioItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Teikėjo atliktas darbas su nuotraukomis (medialibrary kolekcija „images").
 *
 * @property CarbonInterface|null $completed_date
 */
#[Fillable(['category_id', 'city_id', 'title', 'description', 'completed_date', 'sort_order'])]
class PortfolioItem extends Model implements HasMedia
{
    /** @use HasFactory<PortfolioItemFactory> */
    use HasFactory, InteractsWithMedia;

    /** Daugiausia nuotraukų viename darbe. */
    public const MAX_IMAGES = 10;

    protected function casts(): array
    {
        return [
            'completed_date' => 'date',
        ];
    }

    /** @return BelongsTo<ProviderProfile, $this> */
    public function providerProfile(): BelongsTo
    {
        return $this->belongsTo(ProviderProfile::class);
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

    // --- Failai (spatie/laravel-medialibrary, docs/DB_SCHEMA.md → media) -------------

    /**
     * Nuotraukų gali būti kelios, todėl miniatiūros daromos eilėje (queued – numatytasis režimas):
     * įkeliant 10 didelių nuotraukų puslapis neturi laukti, kol visos bus sumažintos.
     * Kol miniatiūra nepadaryta, rodom originalą (imageUrls()).
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->registerMediaConversions(function (): void {
                $this->addMediaConversion('thumb')->fit(Fit::Crop, 480, 360);
                $this->addMediaConversion('large')->fit(Fit::Max, 1600, 1600);
            });
    }

    /**
     * Nuotraukos Inertia puslapiams: id ir URL (miniatiūra, jei jau sugeneruota).
     *
     * @return list<array{id: int, thumb: string, url: string}>
     */
    public function imageUrls(): array
    {
        return array_values($this->getMedia('images')
            ->map(fn (Media $media): array => [
                'id' => $media->id,
                'thumb' => $media->hasGeneratedConversion('thumb') ? $media->getUrl('thumb') : $media->getUrl(),
                'url' => $media->hasGeneratedConversion('large') ? $media->getUrl('large') : $media->getUrl(),
            ])
            ->all());
    }
}
