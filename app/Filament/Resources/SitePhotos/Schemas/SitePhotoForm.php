<?php

namespace App\Filament\Resources\SitePhotos\Schemas;

use App\Enums\SitePhotoKey;
use App\Filament\Support\StockPhotoFields;
use App\Models\SitePhoto;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SitePhotoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextEntry::make('place')
                    ->label('Vieta')
                    ->state(fn (?SitePhoto $record): ?string => $record?->key->label())
                    ->helperText(fn (?SitePhoto $record): ?string => $record === null ? null : self::where($record->key)),
                StockPhotoFields::upload('photo'),
                TextInput::make('alt')
                    ->label('Alternatyvusis tekstas')
                    ->helperText('Trumpai, kas nuotraukoje – ekrano skaitytuvams ir paieškos sistemoms (pvz. „Meistras dažo sieną").')
                    ->maxLength(200),
                StockPhotoFields::credit('photo'),
            ]);
    }

    /**
     * Kur vieta matoma svetainėje ir kokia nuotrauka tinka.
     */
    private static function where(SitePhotoKey $key): string
    {
        return match ($key) {
            SitePhotoKey::Hero => 'Pradžios puslapio viršuje šalia paieškos. Tinka plati, šviesi nuotrauka su darbu ar meistru.',
            SitePhotoKey::Providers => 'Kvietimas „Tapkite teikėju" pradžios ir kainų puslapiuose. Tinka įrankiai, dirbtuvės.',
            SitePhotoKey::Request => 'Skyrius „Kaip tai veikia" – kvietimas sukurti užklausą. Tinka namų remonto planavimas.',
            SitePhotoKey::Auth => 'Prisijungimo ir registracijos puslapių šone. Tinka rami interjero nuotrauka.',
        };
    }
}
