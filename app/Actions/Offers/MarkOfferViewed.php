<?php

namespace App\Actions\Offers;

use App\Models\Offer;

/**
 * Klientas pirmą kartą atidarė pasiūlymą → viewed_at. Nuo to priklauso kreditų grąžinimas pasibaigus užklausai
 * (docs/STATES.md 3 sk.): už atidarytą pasiūlymą kreditai nebegrąžinami.
 */
class MarkOfferViewed
{
    public function handle(Offer $offer): Offer
    {
        if ($offer->viewed_at !== null) {
            return $offer;
        }

        $now = now();

        // UPDATE … WHERE viewed_at IS NULL – atominis: du vienu metu atidaryti skirtukai neperrašys pirmo laiko
        Offer::query()->whereKey($offer->id)->whereNull('viewed_at')->update(['viewed_at' => $now]);

        $offer->forceFill(['viewed_at' => $now])->syncOriginalAttribute('viewed_at');

        return $offer;
    }
}
