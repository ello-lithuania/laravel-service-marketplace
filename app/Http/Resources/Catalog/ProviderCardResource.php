<?php

namespace App\Http\Resources\Catalog;

use App\Models\Category;
use App\Models\ProviderProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Teikėjo kortelė sąraše. API Resource nusako, kuriuos laukus siunčiam į Vue: tik viešus ir tik reikalingus
 * (jokio el. pašto, telefono, įmonės kodo ar kreditų balanso).
 *
 * Tikisi užkrautų ryšių city ir categories (ProviderListQuery) – kitaip preventLazyLoading išmes klaidą.
 * Kaina „nuo" čia nerodoma: be konkrečios paslaugos skirtingų kategorijų kainos (už val., už m²) nepalyginamos.
 * Kategorijos puslapyje naudojamas CategoryProviderCardResource – su kaina.
 *
 * https://laravel.com/docs/13.x/eloquent-resources
 *
 * @mixin ProviderProfile
 *
 * @property ProviderProfile $resource
 */
class ProviderCardResource extends JsonResource
{
    private const MAX_CATEGORIES = 3;

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
            'headline' => $provider->headline,
            // Etapas 3: logotipas iš medialibrary; kol kas Vue rodo inicialus
            'logo_url' => null,
            'city' => $provider->city->name,
            'serves_whole_country' => $provider->serves_whole_country,
            'is_verified' => $provider->isVerified(),
            'rating_avg' => (float) $provider->rating_avg,
            'reviews_count' => $provider->reviews_count,
            'completed_jobs_count' => $provider->completed_jobs_count,
            'years_experience' => $provider->years_experience,
            'categories' => $provider->categories
                ->take(self::MAX_CATEGORIES)
                ->map(fn (Category $category): array => ['name' => $category->name, 'slug' => $category->slug])
                ->values()
                ->all(),
            'price_from' => $this->priceFrom($provider),
        ];
    }

    /**
     * @return array{cents: int, unit: string|null}|null
     */
    protected function priceFrom(ProviderProfile $provider): ?array
    {
        return null;
    }
}
