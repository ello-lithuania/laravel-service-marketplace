<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\Schemas\UserInfolist;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * Vartotojai admin panelėje (Etapas 8): sąrašas su paieška ir filtrais, peržiūra, blokavimas ir atblokavimas.
 * Kūrimo ir redagavimo puslapių nėra (UserPolicy). Teises tikrina UserPolicy (viewAny, view, ban, unban).
 * https://filamentphp.com/docs/5.x/resources/overview
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Vartotojai';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'vartotojas';

    protected static ?string $pluralModelLabel = 'vartotojai';

    protected static ?string $recordTitleAttribute = 'email';

    // Lietuviškas URL: /admin/vartotojai
    protected static ?string $slug = 'vartotojai';

    public static function infolist(Schema $schema): Schema
    {
        return UserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'view' => ViewUser::route('/{record}'),
        ];
    }

    /**
     * Peržiūrai – ryšiai ir skaitliukai iš karto (preventLazyLoading neleistų jų krauti po vieną).
     * Administratorius mato ir ištrintus (anonimizuotus) vartotojus.
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->with([
                'city:id,name',
                'providerProfile' => fn (Relation $query) => $query->withoutGlobalScope(SoftDeletingScope::class),
            ])
            ->withCount(['serviceRequests', 'writtenReviews', 'filedComplaints', 'complaints', 'payments']);
    }
}
