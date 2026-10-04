<?php

namespace App\Services\Photos;

use App\Enums\SitePhotoKey;

/**
 * Paieškos frazės iš database/data/photo_queries.php. Atskira klasė – kad testai galėtų paduoti savo frazes
 * (app()->instance(PhotoQueries::class, new PhotoQueries([...]))) ir PHPStan žinotų struktūrą.
 */
final class PhotoQueries
{
    /** @var array{categories: array<string, string|list<string>>, site: array<string, array{queries: string|list<string>, alt?: string}>, portfolio: array<string, string|list<string>>} */
    private array $data;

    /**
     * @param  array<string, mixed>|null  $data  null – iš database/data/photo_queries.php
     */
    public function __construct(?array $data = null)
    {
        $data ??= require database_path('data/photo_queries.php');

        /** @var array{categories?: array<string, string|list<string>>, site?: array<string, array{queries: string|list<string>, alt?: string}>, portfolio?: array<string, string|list<string>>} $data */
        $this->data = [
            'categories' => $data['categories'] ?? [],
            'site' => $data['site'] ?? [],
            'portfolio' => $data['portfolio'] ?? [],
        ];
    }

    /**
     * @return list<string>
     */
    public function category(string $slug): array
    {
        return self::list($this->data['categories'][$slug] ?? []);
    }

    /**
     * @return list<string>
     */
    public function site(SitePhotoKey $key): array
    {
        return self::list($this->data['site'][$key->value]['queries'] ?? []);
    }

    public function siteAlt(SitePhotoKey $key): ?string
    {
        return $this->data['site'][$key->value]['alt'] ?? null;
    }

    /**
     * @return list<string>
     */
    public function portfolio(string $rootSlug): array
    {
        return self::list($this->data['portfolio'][$rootSlug] ?? []);
    }

    /**
     * @param  string|list<string>  $value
     * @return list<string>
     */
    private static function list(string|array $value): array
    {
        return array_values(array_filter((array) $value, fn (string $query): bool => trim($query) !== ''));
    }
}
