<?php

namespace App\Services\Catalog;

use Illuminate\Container\Attributes\Scoped;
use Illuminate\Support\Facades\Cache;

/**
 * Kategorijų medžio ir geografijos cache (docs/DB_SCHEMA.md 2.2–2.3).
 *
 * - Cache::rememberForever – duomenys keičiasi tik per Filament, todėl laiko limito nereikia:
 *   juos išvalo CatalogCacheObserver, kai kategorija, savivaldybė ar apskritis išsaugoma ar ištrinama.
 * - Raktai fiksuoti (be tag'ų): database ir file cache store'ai tag'ų nepalaiko.
 *   „v1" rakte – pakeitus saugomų duomenų formą, užtenka pakelti versiją, ir senas cache nebenaudojamas.
 * - #[Scoped] – vienas objektas per HTTP užklausą (ar eilės darbą), todėl per užklausą cache skaitomas
 *   ir medis sudedamas tik kartą, o kita užklausa gauna šviežią objektą.
 *
 * https://laravel.com/docs/13.x/cache#retrieve-store
 */
#[Scoped]
final class CatalogCache
{
    public const CATEGORIES_KEY = 'catalog:categories:v1';

    public const GEOGRAPHY_KEY = 'catalog:geography:v1';

    private ?CategoryTree $categories = null;

    private ?Geography $geography = null;

    public function categories(): CategoryTree
    {
        return $this->categories ??= CategoryTree::fromRows(
            Cache::rememberForever(self::CATEGORIES_KEY, CategoryTree::loadRows(...)),
        );
    }

    public function geography(): Geography
    {
        return $this->geography ??= Geography::fromRows(
            Cache::rememberForever(self::GEOGRAPHY_KEY, Geography::loadRows(...)),
        );
    }

    public function forgetCategories(): void
    {
        Cache::forget(self::CATEGORIES_KEY);
        $this->categories = null;
    }

    public function forgetGeography(): void
    {
        Cache::forget(self::GEOGRAPHY_KEY);
        $this->geography = null;
    }

    public function flush(): void
    {
        $this->forgetCategories();
        $this->forgetGeography();
    }
}
