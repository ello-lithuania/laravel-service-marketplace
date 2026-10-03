<?php

namespace App\Filament\Widgets;

use App\Enums\ProviderStatus;
use App\Filament\Resources\ProviderProfiles\ProviderProfileResource;
use App\Models\ProviderProfile;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Nauji aktyvūs, bet dar nepatikrinti teikėjai – administratoriaus darbo eilė („Patikrintas" ženkleliui).
 */
class UnverifiedProvidersTable extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Nauji nepatikrinti teikėjai')
            ->query(fn () => ProviderProfile::query()
                ->where('status', ProviderStatus::Active)
                ->whereNull('verified_at')
                ->with('city:id,name')
                ->latest()
                ->limit(5))
            ->paginated(false)
            ->emptyStateHeading('Visi aktyvūs teikėjai patikrinti')
            ->recordUrl(fn (ProviderProfile $record): string => ProviderProfileResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('display_name')
                    ->label('Teikėjas')
                    ->limit(40)
                    ->description(fn (ProviderProfile $record): string => $record->city->name),
                TextColumn::make('reviews_count')->label('Atsiliepimai')->numeric(),
                TextColumn::make('created_at')->label('Sukurta')->since(),
            ]);
    }
}
