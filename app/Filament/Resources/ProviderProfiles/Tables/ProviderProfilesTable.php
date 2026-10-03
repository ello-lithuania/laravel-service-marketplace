<?php

namespace App\Filament\Resources\ProviderProfiles\Tables;

use App\Enums\ProviderStatus;
use App\Enums\ProviderType;
use App\Filament\Resources\ProviderProfiles\Actions\ProviderModerationActions;
use App\Models\ProviderProfile;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProviderProfilesTable
{
    /**
     * Būsenos spalva – vienoje vietoje sąrašui ir peržiūrai.
     */
    public static function statusColor(ProviderStatus $status): string
    {
        return match ($status) {
            ProviderStatus::Active => 'success',
            ProviderStatus::Pending => 'gray',
            ProviderStatus::Hidden => 'warning',
            ProviderStatus::Suspended => 'danger',
        };
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('display_name')
                    ->label('Pavadinimas')
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->description(fn (ProviderProfile $record): string => $record->user->email),
                // Paieška ir pagal savininko el. paštą (ryšio stulpelis), nors stulpelis paslėptas
                TextColumn::make('user.email')
                    ->label('El. paštas')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('type')
                    ->label('Tipas')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('city.name')
                    ->label('Miestas')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Būsena')
                    ->badge()
                    ->color(fn (ProviderStatus $state): string => self::statusColor($state)),
                IconColumn::make('verified_at')
                    ->label('Patikrintas')
                    ->state(fn (ProviderProfile $record): bool => $record->verified_at !== null)
                    ->boolean(),
                IconColumn::make('user.banned_at')
                    ->label('Paskyra užblokuota')
                    ->state(fn (ProviderProfile $record): bool => $record->user->banned_at !== null)
                    ->boolean()
                    ->trueIcon('heroicon-o-no-symbol')
                    ->trueColor('danger')
                    ->falseIcon('heroicon-o-minus')
                    ->falseColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('rating_avg')
                    ->label('Reitingas')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('reviews_count')
                    ->label('Atsiliepimai')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('completed_jobs_count')
                    ->label('Atlikti darbai')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('credits_balance')
                    ->label('Kreditai')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Sukurta')
                    ->dateTime('Y-m-d', 'Europe/Vilnius')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Būsena')
                    ->options(ProviderStatus::class),
                TernaryFilter::make('verified_at')
                    ->label('Patikrintas')
                    ->nullable()
                    ->trueLabel('Patikrinti')
                    ->falseLabel('Nepatikrinti'),
                SelectFilter::make('type')
                    ->label('Tipas')
                    ->options(ProviderType::class),
                TernaryFilter::make('serves_whole_country')
                    ->label('Visa Lietuva'),
                SelectFilter::make('city')
                    ->label('Bazinis miestas')
                    ->relationship('city', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    ProviderModerationActions::verify(),
                    ProviderModerationActions::unverify(),
                    ProviderModerationActions::activate(),
                    ProviderModerationActions::hide(),
                    ProviderModerationActions::suspend(),
                ]),
            ]);
    }
}
