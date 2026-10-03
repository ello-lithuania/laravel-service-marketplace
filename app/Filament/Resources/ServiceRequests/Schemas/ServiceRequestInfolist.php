<?php

namespace App\Filament\Resources\ServiceRequests\Schemas;

use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Services\Moderation\AutoModerator;
use App\Support\Money;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Užklausos peržiūra administratoriui. Laukiančiai užklausai rodoma, kodėl ji nebuvo paskelbta automatiškai.
 */
class ServiceRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Automatinis moderavimas')
                    ->description('Kodėl užklausa nebuvo paskelbta automatiškai')
                    ->visible(fn (ServiceRequest $record): bool => $record->status === ServiceRequestStatus::Pending)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('moderation_issues')
                            ->hiddenLabel()
                            ->state(fn (ServiceRequest $record): array => app(AutoModerator::class)->issues($record, $record->client))
                            ->placeholder('Pažeidimų nerasta – galima tvirtinti')
                            ->bulleted()
                            ->color('warning'),
                    ]),

                Section::make('Užklausa')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('title')->label('Pavadinimas')->columnSpanFull()->weight('bold'),
                        TextEntry::make('status')->label('Būsena')->badge(),
                        TextEntry::make('category.name')->label('Paslauga'),
                        TextEntry::make('city.name')->label('Miestas / rajonas'),
                        TextEntry::make('address')->label('Adresas')->placeholder('Nenurodytas'),
                        TextEntry::make('budget')
                            ->label('Biudžetas')
                            ->state(fn (ServiceRequest $record): string => Money::range($record->budget_min_cents, $record->budget_max_cents)),
                        TextEntry::make('start_preference')
                            ->label('Pradžia')
                            ->formatStateUsing(fn (ServiceRequest $record): string => $record->start_date?->format('Y-m-d') ?? $record->start_preference->label()),
                        TextEntry::make('description')->label('Aprašymas')->columnSpanFull()->prose(),
                        TextEntry::make('cancellation_reason')
                            ->label('Atšaukimo priežastis')
                            ->columnSpanFull()
                            ->visible(fn (ServiceRequest $record): bool => $record->cancellation_reason !== null),
                    ]),

                Section::make('Klientas ir datos')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('client.name')->label('Vardas, pavardė'),
                        TextEntry::make('client.email')->label('El. paštas')->copyable(),
                        TextEntry::make('client.phone')->label('Telefonas')->placeholder('–'),
                        IconEntry::make('client.email_verified_at')
                            ->label('El. paštas patvirtintas')
                            ->state(fn (ServiceRequest $record): bool => $record->client->hasVerifiedEmail())
                            ->boolean(),
                        TextEntry::make('offers_count')->label('Pasiūlymai'),
                        TextEntry::make('created_at')->label('Sukurta')->dateTime('Y-m-d H:i', 'Europe/Vilnius'),
                        TextEntry::make('published_at')->label('Paskelbta')->dateTime('Y-m-d H:i', 'Europe/Vilnius')->placeholder('–'),
                        TextEntry::make('expires_at')->label('Galioja iki')->dateTime('Y-m-d H:i', 'Europe/Vilnius')->placeholder('–'),
                        TextEntry::make('cancelled_at')->label('Atšaukta')->dateTime('Y-m-d H:i', 'Europe/Vilnius')->placeholder('–'),
                    ]),
            ]);
    }
}
