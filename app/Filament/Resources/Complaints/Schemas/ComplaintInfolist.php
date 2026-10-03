<?php

namespace App\Filament\Resources\Complaints\Schemas;

use App\Enums\ReportableType;
use App\Filament\Resources\Complaints\ReportablePreview;
use App\Models\Complaint;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Skundo peržiūra: skundas, skundžiamas turinys (su nuoroda) ir nagrinėjimo eiga.
 */
class ComplaintInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Skundžiamas turinys')
                    ->columnSpan(2)
                    ->schema([
                        TextEntry::make('reportable_type')
                            ->label('Tipas')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => ReportableType::tryFrom($state)?->label() ?? $state),
                        TextEntry::make('reportable_title')
                            ->label('Kas')
                            ->state(fn (Complaint $record): string => ReportablePreview::for($record)['title'])
                            ->weight('bold')
                            ->url(fn (Complaint $record): ?string => ReportablePreview::for($record)['url'], shouldOpenInNewTab: true),
                        TextEntry::make('reportable_excerpt')
                            ->label('Tekstas')
                            ->state(fn (Complaint $record): ?string => ReportablePreview::for($record)['excerpt'])
                            ->placeholder('–')
                            ->prose(),
                    ]),

                Section::make('Skundas')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('reason')->label('Priežastis')->badge()->color('danger'),
                        TextEntry::make('description')->label('Aprašymas')->placeholder('–'),
                        TextEntry::make('reporter.name')->label('Pranešė')->placeholder('Ištrinta paskyra'),
                        TextEntry::make('reporter.email')->label('El. paštas')->copyable()->placeholder('–'),
                        TextEntry::make('created_at')->label('Gautas')->dateTime('Y-m-d H:i', 'Europe/Vilnius'),
                    ]),

                Section::make('Nagrinėjimas')
                    ->columnSpanFull()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('status')->label('Būsena')->badge(),
                        TextEntry::make('handledBy.name')->label('Nagrinėja / išnagrinėjo')->placeholder('–'),
                        TextEntry::make('resolved_at')->label('Užbaigtas')->dateTime('Y-m-d H:i', 'Europe/Vilnius')->placeholder('–'),
                        TextEntry::make('resolution_note')->label('Sprendimas')->placeholder('–'),
                    ]),
            ]);
    }
}
