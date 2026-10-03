<?php

namespace App\Filament\Resources\CreditTransactions\Tables;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CreditTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Naujausi viršuje: PK didėja kartu su laiku, o indeksas (provider_profile_id + PK) tinka ir filtrui
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('Y-m-d H:i', 'Europe/Vilnius'),
                TextColumn::make('providerProfile.display_name')
                    ->label('Teikėjas')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Tipas')
                    ->badge(),
                TextColumn::make('amount')
                    ->label('Kiekis')
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? '+'.$state : (string) $state)
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'danger')
                    ->weight('bold'),
                TextColumn::make('balance_after')
                    ->label('Likutis po')
                    ->numeric(),
                TextColumn::make('description')
                    ->label('Aprašymas')
                    ->limit(60)
                    ->tooltip(fn (CreditTransaction $record): ?string => $record->description)
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipas')
                    ->options(CreditTransactionType::class),
                SelectFilter::make('providerProfile')
                    ->label('Teikėjas')
                    ->relationship('providerProfile', 'display_name')
                    ->searchable(),
                Filter::make('created_at')
                    ->label('Data')
                    ->schema([
                        DatePicker::make('from')->label('Nuo'),
                        DatePicker::make('until')->label('Iki'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))),
            ]);
    }
}
