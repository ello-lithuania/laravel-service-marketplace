<?php

namespace App\Services\Photos\Providers;

use App\Services\Photos\Exceptions\PhotoSearchFailed;
use App\Services\Photos\Exceptions\ProviderUnavailable;
use App\Services\Photos\PhotoSpec;
use App\Services\Photos\StockPhoto;
use Illuminate\Http\Client\ConnectionException;

/**
 * Nuotraukų bankas (Pexels, Openverse). Bendra sąsaja leidžia komandai bandyti kelis šaltinius iš eilės,
 * o testams – pakeisti tikrą API netikru (Http::fake). Toks pat principas kaip PaymentGateway Etape 7.
 */
interface StockPhotoProvider
{
    /** Pavadinimas žmonėms: „Pexels". */
    public function label(): string;

    /**
     * Ar iš šio šaltinio visada vengti nuotraukų su žmonėmis. Openverse – taip: CC licencija leidžia naudoti
     * nuotrauką, bet neapima nuotraukoje esančio žmogaus sutikimo (asmens teisės į atvaizdą).
     */
    public function avoidsPeople(): bool;

    /**
     * @return list<StockPhoto> rezultatai pagal aktualumą (dar neatsisiųsti)
     *
     * @throws ProviderUnavailable neteisingas raktas ar išnaudotas limitas – šaltinio daugiau nebandyti
     * @throws PhotoSearchFailed kita API klaida – galima bandyti kitą frazę
     * @throws ConnectionException nėra ryšio, baigėsi laikas (timeout), SSL klaida
     */
    public function search(string $query, PhotoSpec $spec): array;
}
