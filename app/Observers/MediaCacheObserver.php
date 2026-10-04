<?php

namespace App\Observers;

use App\Services\Catalog\CatalogCache;
use App\Services\Site\SitePhotos;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Įkėlus, pakeitus ar ištrynus kategorijos ar svetainės nuotrauką, išvalomas atitinkamas cache (Etapas 10).
 * Media modelis priklauso paketui, todėl observer'is registruojamas AppServiceProvider'yje, ne atributu.
 */
class MediaCacheObserver
{
    public function __construct(
        private readonly CatalogCache $catalog,
        private readonly SitePhotos $sitePhotos,
    ) {}

    public function saved(Media $media): void
    {
        $this->flush($media);
    }

    public function deleted(Media $media): void
    {
        $this->flush($media);
    }

    private function flush(Media $media): void
    {
        match ($media->model_type) {
            'category' => $this->catalog->forgetCategories(),
            'site_photo' => $this->sitePhotos->forget(),
            default => null,
        };
    }
}
