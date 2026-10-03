<?php

namespace App\Filament\Resources\Cities;

use App\Filament\Resources\Cities\Pages\ManageCities;
use App\Filament\Resources\Cities\Schemas\CityForm;
use App\Filament\Resources\Cities\Tables\CitiesTable;
use App\Models\City;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Savivaldybės. „Simple" resource: vienas puslapis, kūrimas ir redagavimas – modaliniuose languose.
 * https://filamentphp.com/docs/5.x/resources/overview#simple-modal-resources
 */
class CityResource extends Resource
{
    protected static ?string $model = City::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'Žinynai';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'savivaldybė';

    protected static ?string $pluralModelLabel = 'savivaldybės';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'savivaldybes';

    public static function form(Schema $schema): Schema
    {
        return CityForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CitiesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCities::route('/'),
        ];
    }
}
