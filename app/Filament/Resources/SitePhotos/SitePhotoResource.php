<?php

namespace App\Filament\Resources\SitePhotos;

use App\Filament\Resources\SitePhotos\Pages\ManageSitePhotos;
use App\Filament\Resources\SitePhotos\Schemas\SitePhotoForm;
use App\Filament\Resources\SitePhotos\Tables\SitePhotosTable;
use App\Models\SitePhoto;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * „Svetainės nuotraukos" (Etapas 10): po vieną eilutę kiekvienai dizaino vietai (SitePhotoKey) – pradžios puslapio
 * viršus, kvietimas teikėjams ir t. t. Administratorius įkelia ar pakeičia nuotrauką ir alternatyvųjį tekstą.
 *
 * „Simple" resource: vienas puslapis, redagavimas modaliniame lange. Kurti ir trinti negalima – vietas apibrėžia
 * kodas (enum), o ManageSitePhotos trūkstamas eilutes sukuria pats.
 * https://filamentphp.com/docs/5.x/resources/overview#simple-modal-resources
 */
class SitePhotoResource extends Resource
{
    protected static ?string $model = SitePhoto::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Žinynai';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'svetainės nuotrauka';

    protected static ?string $pluralModelLabel = 'svetainės nuotraukos';

    // Lietuviškas URL: /admin/svetaines-nuotraukos
    protected static ?string $slug = 'svetaines-nuotraukos';

    /**
     * Filament antraštėms ir meniu daro „Title Case" („Svetainės Nuotraukos"); lietuviškai didžioji – tik pirma raidė.
     */
    public static function getTitleCasePluralModelLabel(): string
    {
        return Str::ucfirst(static::getPluralModelLabel());
    }

    public static function getTitleCaseModelLabel(): string
    {
        return Str::ucfirst(static::getModelLabel());
    }

    public static function form(Schema $schema): Schema
    {
        return SitePhotoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SitePhotosTable::configure($table);
    }

    /**
     * Pavadinimas – vietos aprašymas („Pradžios puslapio viršus"), o ne id.
     */
    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record instanceof SitePhoto ? $record->key->label() : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSitePhotos::route('/'),
        ];
    }
}
