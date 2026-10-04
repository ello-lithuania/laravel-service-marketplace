<?php

namespace App\Filament\Support;

use App\Models\Category;
use App\Models\SitePhoto;
use App\Services\Photos\PhotoCredit;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Infolists\Components\TextEntry;

/**
 * Bendri nuotraukos laukai admin formoms (kategorija, svetainės nuotrauka – Etapas 10).
 *
 * SpatieMediaLibraryFileUpload – oficialus Filament įskiepis medialibrary: failą išsaugo kaip media įrašą (su
 * miniatiūromis), o ne kaip kelią modelio stulpelyje, kaip paprastas FileUpload.
 * → https://filamentphp.com/docs/5.x/forms/file-upload · https://filamentphp.com/plugins/filament-spatie-media-library
 */
final class StockPhotoFields
{
    public static function upload(string $collection): SpatieMediaLibraryFileUpload
    {
        return SpatieMediaLibraryFileUpload::make($collection)
            ->label('Nuotrauka')
            ->collection($collection)
            // Peržiūrai – maža miniatiūra, ne originalas
            ->conversion('card')
            ->image()
            // Tik nuotraukų formatai: SVG gali turėti JavaScript (docs/DB_SCHEMA.md → media, validacija)
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->maxSize(10 * 1024)
            ->helperText('JPG, PNG arba WEBP iki 10 MB; geriausia gulsčia, bent 1600 px pločio. Nauja nuotrauka pakeičia ankstesnę.');
    }

    /**
     * Atsisiųstos nuotraukos autorius (custom_properties.credit). Savai nuotraukai laukas nerodomas.
     */
    public static function credit(string $collection): TextEntry
    {
        $credit = fn (Category|SitePhoto|null $record): ?PhotoCredit => PhotoCredit::fromMedia($record?->getFirstMedia($collection));

        return TextEntry::make($collection.'_credit')
            ->label('Autorius ir licencija')
            ->state(fn (Category|SitePhoto|null $record): ?string => $credit($record)?->summary())
            ->url(fn (Category|SitePhoto|null $record): ?string => $credit($record)?->sourceUrl, shouldOpenInNewTab: true)
            ->helperText('Nuotrauka iš nemokamų nuotraukų banko (php artisan photos:download), autorius rodomas puslapyje „Nuotraukų autoriai". Įkėlus savo nuotrauką, šis įrašas dingsta.')
            ->visible(fn (Category|SitePhoto|null $record): bool => $credit($record) !== null);
    }
}
