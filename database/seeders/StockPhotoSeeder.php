<?php

namespace Database\Seeders;

use App\Actions\Photos\AttachLibraryPhoto;
use App\Enums\SitePhotoKey;
use App\Models\Category;
use App\Models\SitePhoto;
use App\Services\Catalog\CatalogCache;
use App\Services\Photos\LibraryPhoto;
use App\Services\Photos\PhotoQueries;
use App\Services\Photos\StockPhotoLibrary;
use App\Services\Site\SitePhotos;
use Database\Seeders\Support\OrphanedMediaFiles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * Etapas 10: kategorijų ir svetainės nuotraukos iš vietinės bibliotekos (storage/app/stock-photos), kurią užpildo
 * php artisan photos:download. Po migrate:fresh --seed svetainė vėl su nuotraukomis – be interneto ir API limitų.
 * Bibliotekos nėra arba SEED_STOCK_PHOTOS=false – nieko nedaro.
 *
 * Greitis: kiekviena nuotrauka – dvi miniatiūros iškart (≈ 0,15–0,3 s). Todėl visada – 1 lygio sritys ir svetainės
 * vietos (≈ 3 s), o 49 antro lygio kategorijos (dar ≈ 8 s) – tik su SEED_MEDIA=true, kai seed'as ir taip ilgesnis.
 * Be SEED_MEDIA jas iš bibliotekos (be interneto) prisega php artisan photos:download.
 */
class StockPhotoSeeder extends Seeder
{
    public function run(StockPhotoLibrary $library, AttachLibraryPhoto $attach, PhotoQueries $queries): void
    {
        if (! config('seeding.stock_photos')) {
            return;
        }

        $categoryPhotos = $library->all(StockPhotoLibrary::CATEGORIES);
        $sitePhotos = $library->all(StockPhotoLibrary::SITE);

        if ($categoryPhotos === [] && $sitePhotos === []) {
            return;
        }

        $started = microtime(true);
        $categories = 0;

        // Švari DB (migrate:fresh) – pirma išvalom senų seed'ų failus, kad nauji įrašai nerašytų į katalogus su jais
        if (! DB::table('media')->exists()) {
            OrphanedMediaFiles::clear();
        }

        $places = 0;

        $query = Category::query()
            ->whereIn('slug', array_keys($categoryPhotos))
            ->when(! config('seeding.media'), fn ($query) => $query->where('depth', 1))
            ->with('media');

        foreach ($query->get() as $category) {
            if (! $category->hasMedia('image')) {
                $this->attach($attach, $category, 'image', $categoryPhotos[$category->slug], 'category:'.$category->slug);
                $categories++;
            }
        }

        // Enum tvarka (ne bibliotekos abėcėlės): taip kuriamos ir site_photos eilutės
        foreach (SitePhotoKey::cases() as $siteKey) {
            $photo = $sitePhotos[$siteKey->value] ?? null;

            if ($photo === null) {
                continue;
            }

            $place = SitePhoto::forKey($siteKey, $queries->siteAlt($siteKey));
            $place->load('media');

            if (! $place->hasMedia('photo')) {
                $this->attach($attach, $place, 'photo', $photo, 'site_photo:'.$siteKey->value);
                $places++;
            }
        }

        // Seed'o metu modelių įvykiai išjungti (WithoutModelEvents), todėl MediaCacheObserver cache neišvalė
        app(CatalogCache::class)->forgetCategories();
        app(SitePhotos::class)->forget();

        $this->command->line(sprintf(
            '  Nuotraukos iš bibliotekos: kategorijų %d, svetainės %d (%.1f s)',
            $categories,
            $places,
            microtime(true) - $started,
        ));
    }

    private function attach(AttachLibraryPhoto $attach, Category|SitePhoto $model, string $collection, LibraryPhoto $photo, string $name): void
    {
        // UUID medialibrary priskiria „creating" įvykyje, o seed'e įvykiai išjungti. UUID v5 – iš vardo, todėl
        // kiekvieną kartą tas pats (kaip ir MediaGenerator: ta pati DB – tie patys duomenys)
        $attach->handle($model, $collection, $photo, [
            'uuid' => Uuid::uuid5(Uuid::NAMESPACE_URL, 'stock-photo:'.$name)->toString(),
        ]);
    }
}
