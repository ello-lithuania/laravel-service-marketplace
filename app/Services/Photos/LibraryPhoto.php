<?php

namespace App\Services\Photos;

/**
 * Nuotrauka vietinėje bibliotekoje (storage/app/stock-photos) su autoriaus duomenimis iš credits.json.
 */
final readonly class LibraryPhoto
{
    /**
     * @param  string  $name  vardas kataloge: kategorijos slug, SitePhotoKey reikšmė arba portfolio „{sritis}-01"
     * @param  string  $path  pilnas kelias diske – jį gauna medialibrary addMedia()
     */
    public function __construct(
        public string $name,
        public string $path,
        public string $fileName,
        public string $stockId,
        public PhotoCredit $credit,
    ) {}

    /**
     * Media custom_properties: credit – autorių puslapiui, stock_id – kad komanda atpažintų savo nuotraukas.
     *
     * @return array{credit: array<string, string|null>, stock_id: string}
     */
    public function customProperties(): array
    {
        return ['credit' => $this->credit->toArray(), 'stock_id' => $this->stockId];
    }
}
