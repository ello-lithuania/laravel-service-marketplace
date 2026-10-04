<?php

namespace App\Services\Site;

use App\Enums\SitePhotoKey;
use App\Models\SitePhoto;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Support\Facades\Cache;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Svetainės dizaino nuotraukos (docs/DB_SCHEMA.md → site_photos) – iš cache, kad puslapiui nereikėtų DB užklausų.
 *
 * Cache išvalo MediaCacheObserver (įkėlus ar ištrynus nuotrauką) ir SitePhoto modelio pakeitimai (alt tekstas).
 * Cache saugom paprastus masyvus, ne modelius (config/cache.php → serializable_classes = false).
 */
#[Scoped]
final class SitePhotos
{
    public const CACHE_KEY = 'site:photos:v1';

    /** @var array<string, array{large: string, card: string, alt: string|null}>|null */
    private ?array $photos = null;

    /**
     * @return array<string, array{large: string, card: string, alt: string|null}> raktas (SitePhotoKey) => URL
     */
    public function all(): array
    {
        return $this->photos ??= Cache::rememberForever(self::CACHE_KEY, self::load(...));
    }

    public function url(SitePhotoKey $key, string $conversion = 'large'): ?string
    {
        $photo = $this->all()[$key->value] ?? null;

        return $photo === null ? null : ($conversion === 'card' ? $photo['card'] : $photo['large']);
    }

    /**
     * Puslapio props: {"hero": {"url": …, "alt": …} | null, …}. null – nuotraukos nėra, Vue rodo atsarginį dizainą.
     *
     * @return array<string, array{url: string, alt: string|null}|null>
     */
    public function forPage(SitePhotoKey ...$keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            $photo = $this->all()[$key->value] ?? null;
            $result[$key->value] = $photo === null ? null : ['url' => $photo['large'], 'alt' => $photo['alt']];
        }

        return $result;
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->photos = null;
    }

    /**
     * @return array<string, array{large: string, card: string, alt: string|null}>
     */
    public static function load(): array
    {
        $photos = [];

        foreach (SitePhoto::query()->with('media')->get() as $sitePhoto) {
            $media = $sitePhoto->getFirstMedia('photo');

            if (! $media instanceof Media) {
                continue;
            }

            // Miniatiūros dar nėra (ką tik įkelta) – originalas
            $photos[$sitePhoto->key->value] = [
                'large' => $media->hasGeneratedConversion('large') ? $media->getUrl('large') : $media->getUrl(),
                'card' => $media->hasGeneratedConversion('card') ? $media->getUrl('card') : $media->getUrl(),
                'alt' => $sitePhoto->alt,
            ];
        }

        return $photos;
    }
}
