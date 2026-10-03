<?php

namespace App\Actions\Portfolio;

use App\Models\PortfolioItem;
use App\Models\ProviderProfile;
use Illuminate\Http\UploadedFile;

/**
 * Sukuria naują atliktą darbą arba atnaujina esamą ir prideda naujas nuotraukas.
 *
 * Failų įrašymas į diską nėra DB transakcijos dalis (diskas „neatšaukiamas"), todėl pirma išsaugom
 * įrašą, o tada pridedam nuotraukas. Nesėkmės atveju darbas lieka be naujų nuotraukų, bet ne atvirkščiai.
 */
class SavePortfolioItem
{
    /**
     * @param  array<string, mixed>  $data  PortfolioItemRequest::validated() be „images"
     * @param  list<UploadedFile>  $images
     */
    public function handle(ProviderProfile $profile, array $data, array $images, ?PortfolioItem $item = null): PortfolioItem
    {
        if ($item === null) {
            $item = $profile->portfolioItems()->make($data);
            // Naujas darbas – sąrašo gale. reorder() nuima ryšio orderBy: MAX() su ORDER BY
            // MySQL griežtame (ONLY_FULL_GROUP_BY) režime būtų klaida
            $last = $profile->portfolioItems()->reorder()->max('sort_order');
            $item->sort_order = max(0, is_numeric($last) ? (int) $last : 0) + 1;
            $item->save();
        } else {
            $item->update($data);
        }

        foreach ($images as $image) {
            $item->addMedia($image)->toMediaCollection('images');
        }

        return $item;
    }
}
