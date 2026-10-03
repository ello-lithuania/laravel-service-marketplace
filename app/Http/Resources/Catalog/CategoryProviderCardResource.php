<?php

namespace App\Http\Resources\Catalog;

use App\Http\Resources\Catalog\Concerns\ReadsServicePrice;
use App\Models\ProviderProfile;

/**
 * Teikėjo kortelė kategorijos puslapyje – papildomai su kaina „nuo".
 * ProviderListQuery čia užkrauna tik su kategorija susijusias teikėjo paslaugas, todėl kaina atitinka
 * būtent šią paslaugą (paveldėjimas: visi kiti laukai – iš ProviderCardResource).
 */
class CategoryProviderCardResource extends ProviderCardResource
{
    use ReadsServicePrice;

    /**
     * Mažiausia kaina tarp užkrautų (susijusių) kategorijų.
     *
     * @return array{cents: int, unit: string|null}|null
     */
    protected function priceFrom(ProviderProfile $provider): ?array
    {
        $cheapest = null;

        foreach ($provider->categories as $category) {
            $price = $this->servicePrice($category);

            if ($price !== null && ($cheapest === null || $price['cents'] < $cheapest['cents'])) {
                $cheapest = $price;
            }
        }

        return $cheapest;
    }
}
