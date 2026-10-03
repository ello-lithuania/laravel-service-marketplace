<?php

namespace App\Filament\Resources\Reviews;

use App\Enums\ReviewStatus;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Filament\Resources\Reviews\Pages\ViewReview;
use App\Filament\Resources\Reviews\Schemas\ReviewInfolist;
use App\Filament\Resources\Reviews\Tables\ReviewsTable;
use App\Models\Review;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * Atsiliepimų moderavimas (Etapas 6): sąrašas su filtrais, peržiūra, paskelbimas ir paslėpimas.
 * Būseną keičia PublishReview / HideReview, o reitingą po to perskaičiuoja ReviewObserver → job.
 * Kūrimo ir redagavimo nėra: atsiliepimus rašo klientai, administratorius jų turinio nekeičia.
 * Teisės – ReviewPolicy (viewAny, view, moderate).
 */
class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|UnitEnum|null $navigationGroup = 'Moderavimas';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'atsiliepimas';

    protected static ?string $pluralModelLabel = 'atsiliepimai';

    // Lietuviškas URL: /admin/atsiliepimai
    protected static ?string $slug = 'atsiliepimai';

    public static function infolist(Schema $schema): Schema
    {
        return ReviewInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReviewsTable::configure($table);
    }

    /**
     * Meniu ženklelis: kiek atsiliepimų (pagal pakvietimą) laukia moderavimo.
     */
    public static function getNavigationBadge(): ?string
    {
        $pending = Review::query()->where('status', ReviewStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReviews::route('/'),
            'view' => ViewReview::route('/{record}'),
        ];
    }

    /**
     * Ryšiai iš karto (be N+1). Ištrintos paskyros autorius ir teikėjas irgi rodomi.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'author' => fn ($query) => $query->withoutGlobalScope(SoftDeletingScope::class),
                'providerProfile' => fn ($query) => $query->withoutGlobalScope(SoftDeletingScope::class),
                'serviceRequest:id,slug,title',
            ])
            ->withCount('complaints');
    }
}
