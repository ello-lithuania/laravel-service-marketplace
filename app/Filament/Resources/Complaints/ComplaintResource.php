<?php

namespace App\Filament\Resources\Complaints;

use App\Enums\ComplaintStatus;
use App\Filament\Resources\Complaints\Pages\ListComplaints;
use App\Filament\Resources\Complaints\Pages\ViewComplaint;
use App\Filament\Resources\Complaints\Schemas\ComplaintInfolist;
use App\Filament\Resources\Complaints\Tables\ComplaintsTable;
use App\Models\Complaint;
use App\Models\Message;
use App\Models\ProviderProfile;
use App\Models\ServiceRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * Skundų nagrinėjimas (Etapas 6): eilė (nauji ir nagrinėjami – seniausi viršuje), peržiūra su skundžiamu
 * turiniu ir veiksmai „Imti nagrinėti", „Išspręsti" (galima paslėpti turinį), „Atmesti".
 * Teisės – ComplaintPolicy (viewAny, view, handle).
 */
class ComplaintResource extends Resource
{
    protected static ?string $model = Complaint::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = 'Moderavimas';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'skundas';

    protected static ?string $pluralModelLabel = 'skundai';

    // Lietuviškas URL: /admin/skundai
    protected static ?string $slug = 'skundai';

    public static function infolist(Schema $schema): Schema
    {
        return ComplaintInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ComplaintsTable::configure($table);
    }

    public static function getNavigationBadge(): ?string
    {
        $open = Complaint::query()->where('status', ComplaintStatus::Open)->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListComplaints::route('/'),
            'view' => ViewComplaint::route('/{record}'),
        ];
    }

    /**
     * Skundžiamas turinys užkraunamas iš karto (morphTo eager loading – po vieną užklausą kiekvienam tipui).
     * constrain(): paslėptos žinutės ir „ištrintos" užklausos ar profiliai irgi rodomi – administratorius
     * turi matyti, dėl ko skųstasi, net jei turinys jau pašalintas.
     * https://laravel.com/docs/13.x/eloquent-relationships#constraining-eager-loads-on-morph-to-relationships
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'reporter',
            'handledBy',
            'reportable' => function (Relation $relation): void {
                $withTrashed = fn (Builder $query) => $query->withoutGlobalScope(SoftDeletingScope::class);

                if ($relation instanceof MorphTo) {
                    $relation->constrain([
                        Message::class => $withTrashed,
                        ServiceRequest::class => $withTrashed,
                        ProviderProfile::class => $withTrashed,
                    ]);
                }
            },
        ]);
    }
}
