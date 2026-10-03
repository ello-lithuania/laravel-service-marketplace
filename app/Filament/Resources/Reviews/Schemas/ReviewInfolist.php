<?php

namespace App\Filament\Resources\Reviews\Schemas;

use App\Models\Review;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Atsiliepimo peržiūra administratoriui: tekstas, autorius, teikėjas, užklausa (jei patvirtintas), skundai.
 */
class ReviewInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Atsiliepimas')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('rating')
                            ->label('Įvertinimas')
                            ->formatStateUsing(fn (int $state): string => str_repeat('★', $state).' ('.$state.' iš 5)')
                            ->color('warning'),
                        TextEntry::make('status')->label('Būsena')->badge(),
                        TextEntry::make('comment')->label('Tekstas')->columnSpanFull()->prose(),
                        TextEntry::make('provider_reply')
                            ->label('Teikėjo atsakymas')
                            ->columnSpanFull()
                            ->placeholder('Teikėjas neatsakė'),
                    ]),

                Section::make('Kas ir apie ką')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('author.name')->label('Autorius'),
                        TextEntry::make('author.email')->label('Autoriaus el. paštas')->copyable(),
                        TextEntry::make('providerProfile.display_name')
                            ->label('Teikėjas')
                            ->url(fn (Review $record): ?string => $record->providerProfile === null
                                ? null
                                : route('providers.show', $record->providerProfile), shouldOpenInNewTab: true),
                        IconEntry::make('verified')
                            ->label('Patvirtintas (darbas per platformą)')
                            ->state(fn (Review $record): bool => $record->isVerified())
                            ->boolean(),
                        TextEntry::make('serviceRequest.title')
                            ->label('Užklausa')
                            ->placeholder('Pagal teikėjo pakvietimą')
                            ->url(fn (Review $record): ?string => $record->serviceRequest === null
                                ? null
                                : route('filament.admin.resources.uzklausos.view', $record->serviceRequest->id)),
                        TextEntry::make('complaints_count')->label('Skundai'),
                        TextEntry::make('created_at')->label('Parašytas')->dateTime('Y-m-d H:i', 'Europe/Vilnius'),
                        TextEntry::make('published_at')->label('Paskelbtas')->dateTime('Y-m-d H:i', 'Europe/Vilnius')->placeholder('–'),
                    ]),
            ]);
    }
}
