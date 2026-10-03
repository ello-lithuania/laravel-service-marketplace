<?php

namespace App\Filament\Resources\ServiceRequests\Tables;

use App\Enums\ServiceRequestStatus;
use App\Filament\Resources\ServiceRequests\Actions\ModerationActions;
use App\Models\ServiceRequest;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ServiceRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Pavadinimas')
                    ->searchable()
                    ->limit(60)
                    ->description(fn (ServiceRequest $record): string => $record->client->name.' · '.$record->client->email),
                // Paieška ir pagal kliento el. paštą (ryšio stulpelis), nors stulpelis nerodomas
                TextColumn::make('client.email')
                    ->label('Kliento el. paštas')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('category.name')
                    ->label('Paslauga')
                    ->toggleable(),
                TextColumn::make('city.name')
                    ->label('Miestas')
                    ->toggleable(),
                // Enum'as su HasLabel + HasColor – Filament pats parodo lietuvišką pavadinimą ir spalvą
                TextColumn::make('status')
                    ->label('Būsena')
                    ->badge()
                    ->sortable(),
                TextColumn::make('offers_count')
                    ->label('Pasiūlymai')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Sukurta')
                    ->dateTime('Y-m-d H:i', 'Europe/Vilnius')
                    ->sortable(),
                TextColumn::make('published_at')
                    ->label('Paskelbta')
                    ->dateTime('Y-m-d H:i', 'Europe/Vilnius')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Būsena')
                    ->options(ServiceRequestStatus::class),
                SelectFilter::make('category')
                    ->label('Paslauga')
                    ->relationship('category', 'name', fn ($query) => $query->where('depth', 3))
                    ->searchable()
                    ->preload(),
                SelectFilter::make('city')
                    ->label('Miestas')
                    ->relationship('city', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    ModerationActions::approve(),
                    ModerationActions::reject(),
                    ModerationActions::cancel(),
                ]),
            ]);
    }
}
