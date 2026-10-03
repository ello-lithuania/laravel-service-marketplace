<?php

namespace App\Observers;

use App\Models\Category;
use App\Services\Catalog\CatalogCache;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Database\Eloquent\Model;

/**
 * Išvalo katalogo cache, kai keičiasi kategorija, savivaldybė ar apskritis (pvz. per Filament),
 * kad pakeitimas svetainėje matytųsi iš karto.
 *
 * ShouldHandleEventsAfterCommit – valom tik DB transakcijai pasibaigus sėkmingai. Kitaip lygiagreti
 * užklausa galėtų spėti į cache įrašyti dar senus (nepatvirtintus) duomenis.
 *
 * https://laravel.com/docs/13.x/eloquent#observers
 */
class CatalogCacheObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly CatalogCache $catalog) {}

    public function saved(Model $model): void
    {
        $this->forget($model);
    }

    public function deleted(Model $model): void
    {
        $this->forget($model);
    }

    private function forget(Model $model): void
    {
        if ($model instanceof Category) {
            $this->catalog->forgetCategories();
        } else {
            // City ir Region – geografija
            $this->catalog->forgetGeography();
        }
    }
}
