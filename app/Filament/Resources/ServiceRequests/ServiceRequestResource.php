<?php

namespace App\Filament\Resources\ServiceRequests;

use App\Enums\ServiceRequestStatus;
use App\Filament\Resources\ServiceRequests\Pages\ListServiceRequests;
use App\Filament\Resources\ServiceRequests\Pages\ViewServiceRequest;
use App\Filament\Resources\ServiceRequests\Schemas\ServiceRequestInfolist;
use App\Filament\Resources\ServiceRequests\Tables\ServiceRequestsTable;
use App\Models\ServiceRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * Užklausų moderavimas admin panelėje: sąrašas, peržiūra, patvirtinimas (pending → open) ir atmetimas.
 * Kūrimo ir redagavimo puslapių nėra – užklausas kuria klientai, o būseną keičia tik Actions (docs/STATES.md).
 * Teises tikrina ServiceRequestPolicy (viewAny, view, publish, cancel).
 * https://filamentphp.com/docs/5.x/resources/overview
 */
class ServiceRequestResource extends Resource
{
    protected static ?string $model = ServiceRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Užklausos';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'užklausa';

    protected static ?string $pluralModelLabel = 'užklausos';

    protected static ?string $recordTitleAttribute = 'title';

    // Lietuviškas URL: /admin/uzklausos
    protected static ?string $slug = 'uzklausos';

    public static function infolist(Schema $schema): Schema
    {
        return ServiceRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ServiceRequestsTable::configure($table);
    }

    /**
     * Ženklelis meniu: kiek užklausų laukia moderavimo.
     */
    public static function getNavigationBadge(): ?string
    {
        $pending = ServiceRequest::query()->where('status', ServiceRequestStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServiceRequests::route('/'),
            'view' => ViewServiceRequest::route('/{record}'),
        ];
    }

    /**
     * Visos užklausos su ryšiais iš karto (be N+1) – ir sąrašui, ir peržiūrai.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['client', 'category', 'city']);
    }

    /**
     * Administratorius mato ir „ištrintas" (soft delete) užklausas.
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
