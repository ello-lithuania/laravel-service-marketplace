<?php

namespace App\Actions\ProviderProfile;

use App\Models\ProviderProfile;
use Illuminate\Support\Facades\DB;

/**
 * Vedlio 4 žingsnis: kaina „nuo" kiekvienai pasirinktai kategorijai (pivot price_from_cents + price_unit).
 */
class UpdateProviderPrices
{
    /**
     * @param  list<array{category_id: int, price_from: string|null, price_unit: string|null}>  $prices
     */
    public function handle(ProviderProfile $profile, array $prices): void
    {
        DB::transaction(function () use ($profile, $prices): void {
            foreach ($prices as $price) {
                $cents = $price['price_from'] === null ? null : $this->toCents($price['price_from']);

                $profile->categories()->updateExistingPivot($price['category_id'], [
                    'price_from_cents' => $cents,
                    // Be kainos vienetas neturi prasmės
                    'price_unit' => $cents === null ? null : $price['price_unit'],
                ]);
            }
        });
    }

    /**
     * „15", „15.5", „15,50" → 1550. Skaičiuojam su eilutėmis, ne su float:
     * float negali tiksliai saugoti 0,1, todėl 0.29 * 100 gali virsti 28.999… (docs/DB_SCHEMA.md 2.7).
     */
    private function toCents(string $euros): int
    {
        [$whole, $fraction] = array_pad(explode('.', str_replace(',', '.', $euros), 2), 2, '');

        return (int) $whole * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }
}
