<?php

namespace App\Filament\Resources\Subscriptions\Schemas;

use App\Models\Subscription;
use App\Support\Money;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SubscriptionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Prenumerata')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('providerProfile.display_name')->label('Teikėjas')->columnSpanFull()->weight('bold'),
                        TextEntry::make('plan.name')->label('Planas'),
                        TextEntry::make('status')->label('Būsena')->badge(),
                        TextEntry::make('plan.price_cents')
                            ->label('Kaina už laikotarpį')
                            ->formatStateUsing(fn (int $state): string => Money::format($state)),
                        TextEntry::make('plan.credits_per_period')->label('Kreditai už laikotarpį'),
                        IconEntry::make('auto_renew')->label('Pratęsiama automatiškai')->boolean(),
                    ]),
                Section::make('Datos')
                    ->schema([
                        TextEntry::make('starts_at')->label('Pradžia')->dateTime('Y-m-d H:i', 'Europe/Vilnius'),
                        TextEntry::make('ends_at')->label('Apmokėta iki')->dateTime('Y-m-d H:i', 'Europe/Vilnius'),
                        TextEntry::make('credits_granted_until')
                            ->label('Kreditai suteikti iki')
                            ->dateTime('Y-m-d H:i', 'Europe/Vilnius')
                            ->placeholder('Dar nesuteikti'),
                        TextEntry::make('cancelled_at')->label('Atšaukta')->dateTime('Y-m-d H:i', 'Europe/Vilnius')->placeholder('–'),
                        TextEntry::make('payments_count')
                            ->label('Mokėjimų')
                            ->state(fn (Subscription $record): int => $record->payments()->count()),
                    ]),
            ]);
    }
}
