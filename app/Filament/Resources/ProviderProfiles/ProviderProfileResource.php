<?php

namespace App\Filament\Resources\ProviderProfiles;

use App\Enums\ProviderStatus;
use App\Filament\Resources\ProviderProfiles\Pages\ListProviderProfiles;
use App\Filament\Resources\ProviderProfiles\Pages\ViewProviderProfile;
use App\Filament\Resources\ProviderProfiles\Schemas\ProviderProfileInfolist;
use App\Filament\Resources\ProviderProfiles\Tables\ProviderProfilesTable;
use App\Models\ProviderProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

/**
 * Teikėjų profilių moderavimas (Etapas 8): sąrašas, peržiūra su paslaugomis, zonomis ir nuotraukomis,
 * veiksmai „Patikrintas", „Paslėpti", „Užblokuoti", „Aktyvuoti".
 * Profilius kuria ir redaguoja patys teikėjai (vedlys), todėl kūrimo ir redagavimo puslapių nėra.
 * Teisės – ProviderProfilePolicy (viewAny, view, moderate).
 */
class ProviderProfileResource extends Resource
{
    /** Meniu ženklelio cache raktas (išvalomas pakeitus „Patikrintas"). */
    public const UNVERIFIED_BADGE_CACHE_KEY = 'admin:nav:unverified-providers';

    protected static ?string $model = ProviderProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|UnitEnum|null $navigationGroup = 'Vartotojai';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'teikėjas';

    protected static ?string $pluralModelLabel = 'teikėjai';

    protected static ?string $recordTitleAttribute = 'display_name';

    // Lietuviškas URL: /admin/teikejai
    protected static ?string $slug = 'teikejai';

    public static function infolist(Schema $schema): Schema
    {
        return ProviderProfileInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProviderProfilesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProviderProfiles::route('/'),
            'view' => ViewProviderProfile::route('/{record}'),
        ];
    }

    /**
     * Sąrašui – savininkas ir bazinis miestas iš karto (be N+1).
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'user:id,first_name,last_name,email,banned_at',
            'city:id,name',
        ]);
    }

    /**
     * Peržiūrai – viskas, ką rodo infolist: paslaugos, zonos, darbai su nuotraukomis, logotipas ir viršelis.
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->with([
                // Ištrinto (anonimizuoto) savininko profilis irgi turi atsidaryti
                'user' => fn (Relation $query) => $query->withoutGlobalScope(SoftDeletingScope::class),
                'city:id,name',
                'categories' => fn (Relation $query) => $query->select(['categories.id', 'categories.name', 'categories.depth']),
                'serviceAreas' => fn (Relation $query) => $query->select(['cities.id', 'cities.name']),
                'portfolioItems.media',
                'media' => fn (Relation $query) => $query->whereIn('collection_name', ['logo', 'cover']),
            ])
            ->withCount('offers');
    }

    /**
     * Ženklelis meniu: aktyvūs, bet dar nepatikrinti teikėjai (darbo eilė administratoriui).
     * Meniu piešiamas kiekviename admin puslapyje, o verified_at indekse nėra (pilname seed'e ~35 ms),
     * todėl skaičius laikomas cache 5 min. – tikslumo iki sekundės čia nereikia.
     */
    public static function getNavigationBadge(): ?string
    {
        $unverified = (int) Cache::remember(self::UNVERIFIED_BADGE_CACHE_KEY, now()->addMinutes(5), fn (): int => ProviderProfile::query()
            ->where('status', ProviderStatus::Active)
            ->whereNull('verified_at')
            ->count());

        return $unverified > 0 ? (string) $unverified : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Aktyvūs, bet nepatikrinti teikėjai';
    }
}
