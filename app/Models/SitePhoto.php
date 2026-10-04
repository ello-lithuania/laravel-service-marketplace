<?php

namespace App\Models;

use App\Enums\SitePhotoKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Svetainės dizaino nuotrauka (docs/DB_SCHEMA.md → site_photos): viena eilutė vienai vietai (SitePhotoKey).
 *
 * @property int $id
 * @property SitePhotoKey $key
 * @property string|null $alt
 */
#[Fillable(['key', 'alt'])]
class SitePhoto extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected function casts(): array
    {
        return [
            'key' => SitePhotoKey::class,
        ];
    }

    /**
     * Viena nuotrauka vietai; miniatiūros daromos iškart – administratorius rezultatą nori matyti tuoj pat.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->registerMediaConversions(function (): void {
                $this->addMediaConversion('large')->nonQueued()->fit(Fit::Max, 1920, 1920);
                $this->addMediaConversion('card')->nonQueued()->fit(Fit::Crop, 800, 600);
            });
    }

    // --- Etapas 10a ---

    /**
     * Vietos eilutė (sukuriama, jei jos dar nėra): admin panelė ir photos:download rodo visas SitePhotoKey vietas.
     * Alternatyvusis tekstas įrašomas tik tuščias – administratoriaus pakeisto neperrašom.
     */
    public static function forKey(SitePhotoKey $key, ?string $alt = null): self
    {
        $photo = self::query()->firstOrCreate(['key' => $key->value], ['alt' => $alt]);

        if ($photo->alt === null && $alt !== null) {
            $photo->update(['alt' => $alt]);
        }

        return $photo;
    }
}
