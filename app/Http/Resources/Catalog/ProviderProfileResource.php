<?php

namespace App\Http\Resources\Catalog;

use App\Http\Resources\Catalog\Concerns\ReadsServicePrice;
use App\Models\Category;
use App\Models\City;
use App\Models\ProviderProfile;
use App\Services\Catalog\CatalogCache;
use Collator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Viešas teikėjo profilis. Sąmoningai nesiunčiam: el. pašto, telefono, įmonės ir PVM kodų, kreditų balanso,
 * būsenos – tik tai, ką teikėjas skelbia viešai.
 *
 * Tikisi užkrautų ryšių city, serviceAreas, categories (ProviderController::show).
 *
 * @mixin ProviderProfile
 *
 * @property ProviderProfile $resource
 */
class ProviderProfileResource extends JsonResource
{
    use ReadsServicePrice;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $provider = $this->resource;

        return [
            'id' => $provider->id,
            'slug' => $provider->slug,
            'display_name' => $provider->display_name,
            'type' => $provider->type->label(),
            'headline' => $provider->headline,
            'description' => $provider->description,
            'website' => $this->safeWebsite($provider->website),
            // Logotipas iš medialibrary (media ryšys užkraunamas užklausoje); nėra – Vue rodo inicialus
            'logo_url' => $provider->relationLoaded('media') ? $provider->logoUrl() : null,
            'cover_url' => $provider->relationLoaded('media') ? $provider->coverUrl() : null,
            'city' => ['name' => $provider->city->name, 'slug' => $provider->city->slug],
            'serves_whole_country' => $provider->serves_whole_country,
            'service_areas' => $provider->serves_whole_country ? [] : $this->serviceAreas($provider),
            'is_verified' => $provider->isVerified(),
            'years_experience' => $provider->years_experience,
            'rating_avg' => (float) $provider->rating_avg,
            'reviews_count' => $provider->reviews_count,
            'completed_jobs_count' => $provider->completed_jobs_count,
            'member_since' => $provider->created_at?->year,
            'services' => $this->services($provider),
        ];
    }

    /**
     * Paslaugos su kaina „nuo". Nuoroda (slug) – tik į kategorijas, kurios kataloge matomos.
     *
     * @return list<array{name: string, slug: string|null, price_from_cents: int|null, price_unit: string|null}>
     */
    private function services(ProviderProfile $provider): array
    {
        $tree = app(CatalogCache::class)->categories();

        return array_values($provider->categories
            ->sortBy([['depth', 'asc'], ['name', 'asc']])
            ->map(function (Category $category) use ($tree): array {
                $price = $this->servicePrice($category);

                return [
                    'name' => $category->name,
                    'slug' => $tree->find($category->id)?->slug,
                    'price_from_cents' => $price['cents'] ?? null,
                    'price_unit' => $price['unit'] ?? null,
                ];
            })
            ->all());
    }

    /**
     * Zonos abėcėlės tvarka (lietuviška – Š po S).
     *
     * @return list<array{name: string, slug: string}>
     */
    private function serviceAreas(ProviderProfile $provider): array
    {
        $collator = new Collator('lt_LT');

        return array_values($provider->serviceAreas
            ->sort(fn (City $a, City $b): int => (int) $collator->compare($a->name, $b->name))
            ->map(fn (City $city): array => ['name' => $city->name, 'slug' => $city->slug])
            ->all());
    }

    /**
     * Tik http(s) adresai: „javascript:…" nuoroda Vue :href atribute būtų XSS spraga.
     */
    private function safeWebsite(?string $website): ?string
    {
        if ($website === null || filter_var($website, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return in_array(parse_url($website, PHP_URL_SCHEME), ['http', 'https'], true) ? $website : null;
    }
}
