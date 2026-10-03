<?php

namespace App\Filament\Resources\Complaints\Tables;

use App\Enums\ComplaintReason;
use App\Enums\ComplaintStatus;
use App\Enums\ReportableType;
use App\Filament\Resources\Complaints\Actions\ComplaintActions;
use App\Filament\Resources\Complaints\ReportablePreview;
use App\Models\Complaint;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ComplaintsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Eilė: nauji, tada nagrinėjami, tada užbaigti; tame pačiame lygyje – seniausi viršuje (laukia ilgiausiai).
            // Indeksas complaints(status, created_at) – docs/DB_SCHEMA.md 8 sk. #13
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw('CASE status WHEN ? THEN 0 WHEN ? THEN 1 ELSE 2 END', [
                    ComplaintStatus::Open->value,
                    ComplaintStatus::InReview->value,
                ])
                ->orderBy('created_at'))
            ->columns([
                TextColumn::make('reportable_type')
                    ->label('Kas')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ReportableType::tryFrom($state)?->label() ?? $state),
                TextColumn::make('reason')
                    ->label('Priežastis')
                    ->badge()
                    ->color('danger'),
                TextColumn::make('preview')
                    ->label('Turinys')
                    ->state(fn (Complaint $record): string => ReportablePreview::for($record)['excerpt'] ?? ReportablePreview::for($record)['title'])
                    ->limit(70)
                    ->wrap()
                    ->description(fn (Complaint $record): ?string => $record->description),
                TextColumn::make('reporter.name')
                    ->label('Pranešė')
                    ->placeholder('–')
                    ->searchable(['first_name', 'last_name', 'email']),
                TextColumn::make('status')
                    ->label('Būsena')
                    ->badge()
                    ->sortable(),
                TextColumn::make('handledBy.name')
                    ->label('Nagrinėja')
                    ->placeholder('–')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Gautas')
                    ->dateTime('Y-m-d H:i', 'Europe/Vilnius')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Būsena')
                    ->options(ComplaintStatus::class),
                SelectFilter::make('reason')
                    ->label('Priežastis')
                    ->options(ComplaintReason::class),
                SelectFilter::make('reportable_type')
                    ->label('Kas skundžiama')
                    ->options(ReportableType::class),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    ComplaintActions::takeInReview(),
                    ComplaintActions::resolve(),
                    ComplaintActions::reject(),
                ]),
            ]);
    }
}
