<?php

namespace App\Filament\Resources\Reviews\Tables;

use App\Enums\ReviewStatus;
use App\Filament\Resources\Reviews\Actions\ReviewModerationActions;
use App\Models\Review;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('rating')
                    ->label('★')
                    ->formatStateUsing(fn (int $state): string => str_repeat('★', $state))
                    ->color('warning')
                    ->sortable(),
                TextColumn::make('comment')
                    ->label('Atsiliepimas')
                    ->limit(80)
                    ->wrap()
                    ->searchable()
                    ->description(fn (Review $record): string => ($record->author->public_name ?? '–').' → '.($record->providerProfile->display_name ?? '–')),
                // Patvirtintas (yra užklausa) ar pagal pakvietimą
                IconColumn::make('service_request_id')
                    ->label('Tipas')
                    ->state(fn (Review $record): bool => $record->isVerified())
                    ->boolean()
                    // Pakvietimo atsiliepimas – ne klaida, todėl ne raudonas „X", o pilka „pakviesto" ikona
                    ->trueIcon(Heroicon::OutlinedShieldCheck)
                    ->falseIcon(Heroicon::OutlinedUserPlus)
                    ->falseColor('gray')
                    ->tooltip(fn (Review $record): string => $record->isVerified() ? 'Užsakyta per platformą' : 'Pagal teikėjo pakvietimą'),
                TextColumn::make('status')
                    ->label('Būsena')
                    ->badge()
                    ->sortable(),
                TextColumn::make('complaints_count')
                    ->label('Skundai')
                    ->numeric()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Parašytas')
                    ->dateTime('Y-m-d H:i', 'Europe/Vilnius')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Būsena')
                    ->options(ReviewStatus::class),
                TernaryFilter::make('verified')
                    ->label('Tipas')
                    ->placeholder('Visi')
                    ->trueLabel('Patvirtinti (per platformą)')
                    ->falseLabel('Pagal pakvietimą')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('service_request_id'),
                        false: fn (Builder $query) => $query->whereNull('service_request_id'),
                    ),
                SelectFilter::make('rating')
                    ->label('Įvertinimas')
                    ->options([5 => '★★★★★', 4 => '★★★★', 3 => '★★★', 2 => '★★', 1 => '★']),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    ReviewModerationActions::publish(),
                    ReviewModerationActions::hide(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ReviewModerationActions::publishBulk(),
                    ReviewModerationActions::hideBulk(),
                ]),
            ]);
    }
}
