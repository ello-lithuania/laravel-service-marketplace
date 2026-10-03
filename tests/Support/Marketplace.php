<?php

namespace Tests\Support;

use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\ServiceRequest;

/**
 * Etapo 5 testų pagalbininkai: tinkamas teikėjas užklausai ir pan.
 */
final class Marketplace
{
    /**
     * Teikėjas, tinkantis užklausai: pasirinkta užklausos kategorija (arba nurodyta – pvz. tėvas),
     * užklausos miestas – jo zonoje.
     */
    public static function eligibleProvider(ServiceRequest $request, int $credits = 10, ?Category $category = null): ProviderProfile
    {
        $provider = ProviderProfile::factory()->withCredits($credits)->create(['city_id' => $request->city_id]);
        $provider->categories()->attach($category->id ?? $request->category_id);
        $provider->serviceAreas()->attach($request->city_id);

        return $provider;
    }

    /**
     * Atvira užklausa su 3 lygio kategorija, kurios pasiūlymas kainuoja $cost kreditų.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function openRequest(int $cost = 1, array $attributes = []): ServiceRequest
    {
        $leaf = Category::factory()->leaf()->create(['offer_cost_credits' => $cost]);

        return ServiceRequest::factory()->create(['category_id' => $leaf->id, ...$attributes]);
    }
}
