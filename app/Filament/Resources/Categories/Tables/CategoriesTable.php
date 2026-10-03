<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Models\Category;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Tėvinė kategorija ir vaikų skaičius užkraunami iš karto (be N+1 užklausų)
            ->modifyQueryUsing(fn (Builder $query) => $query->with('parent')->withCount('children'))
            ->defaultSort('depth')
            ->columns([
                TextColumn::make('name')
                    ->label('Pavadinimas')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Category $record): ?string => $record->parent?->name),
                TextColumn::make('depth')
                    ->label('Lygis')
                    ->badge()
                    ->sortable(),
                TextColumn::make('children_count')
                    ->label('Subkategorijos')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('offer_cost_credits')
                    ->label('Kreditai')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label('Eilė')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->label('Aktyvi')
                    ->boolean(),
                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Atnaujinta')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('depth')
                    ->label('Lygis')
                    ->options([1 => '1 – sritis', 2 => '2 – kategorija', 3 => '3 – paslauga']),
                SelectFilter::make('parent_id')
                    ->label('Tėvinė kategorija')
                    ->relationship('parent', 'name', fn (Builder $query) => $query->where('depth', '<', Category::MAX_DEPTH))
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_active')
                    ->label('Aktyvi'),
            ])
            // Trynimo nėra: kategorijas naudoja užklausos ir teikėjai (FK restrict) – jas išjungiam
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
