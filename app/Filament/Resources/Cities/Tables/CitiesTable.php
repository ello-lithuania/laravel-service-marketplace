<?php

namespace App\Filament\Resources\Cities\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('region')->withCount('providerProfiles'))
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label('Pavadinimas')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name_locative')
                    ->label('Vietininkas')
                    ->searchable(),
                TextColumn::make('region.name')
                    ->label('Apskritis')
                    ->sortable(),
                TextColumn::make('provider_profiles_count')
                    ->label('Teikėjai (bazinis miestas)')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label('Eilė')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('slug')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('region_id')
                    ->label('Apskritis')
                    ->relationship('region', 'name')
                    ->preload(),
            ])
            // Savivaldybių netrinam: jas naudoja teikėjai ir užklausos (FK restrict)
            ->recordActions([
                EditAction::make()->modalHeading('Redaguoti savivaldybę'),
            ]);
    }
}
