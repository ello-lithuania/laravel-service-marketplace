<?php

namespace App\Services\Photos;

/**
 * Vienas paieškos rezultatas iš nuotraukų banko (dar neatsisiųstas).
 */
final readonly class StockPhoto
{
    /**
     * @param  string  $provider  trumpas šaltinio vardas: pexels, openverse
     * @param  int|null  $width  originalo matmenys, jei API juos nurodo (dar prieš atsisiunčiant)
     * @param  string  $description  aprašymas ir žymos – iš jų spėjama, ar nuotraukoje žmonės
     */
    public function __construct(
        public string $provider,
        public string $id,
        public string $downloadUrl,
        public ?int $width,
        public ?int $height,
        public string $description,
        public PhotoCredit $credit,
    ) {}

    /**
     * Unikalus ID tarp visų šaltinių („pexels:2014422") – kad ta pati nuotrauka nebūtų naudojama du kartus.
     */
    public function stockId(): string
    {
        return $this->provider.':'.$this->id;
    }
}
