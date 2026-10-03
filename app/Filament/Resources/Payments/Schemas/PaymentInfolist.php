<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Models\Payment;
use App\Support\Money;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Mokėjimo peržiūra administratoriui: duomenys, mokėtojas ir tiekėjo atsakymas (meta).
 */
class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Mokėjimas')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('description')
                            ->label('Pirkinys')
                            ->state(fn (Payment $record): string => $record->description())
                            ->columnSpanFull()
                            ->weight('bold'),
                        TextEntry::make('amount_cents')
                            ->label('Suma')
                            ->formatStateUsing(fn (int $state): string => Money::format($state)),
                        TextEntry::make('status')->label('Būsena')->badge(),
                        TextEntry::make('gateway')->label('Tiekėjas')->badge(),
                        TextEntry::make('gateway_reference')->label('Tiekėjo transakcijos ID')->placeholder('–')->copyable(),
                        TextEntry::make('uuid')->label('Užsakymo Nr.')->copyable(),
                        TextEntry::make('invoice_number')->label('Sąskaita')->placeholder('–'),
                        TextEntry::make('subscription_id')->label('Prenumeratos ID')->placeholder('–'),
                        TextEntry::make('created_at')->label('Sukurta')->dateTime('Y-m-d H:i', 'Europe/Vilnius'),
                        TextEntry::make('paid_at')->label('Apmokėta')->dateTime('Y-m-d H:i', 'Europe/Vilnius')->placeholder('–'),
                    ]),

                Section::make('Mokėtojas')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('user.name')->label('Vardas, pavardė'),
                        TextEntry::make('user.email')->label('El. paštas')->copyable(),
                    ]),

                Section::make('Tiekėjo atsakymas')
                    ->description('payments.meta – be asmens duomenų')
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        KeyValueEntry::make('meta')->hiddenLabel()->placeholder('–'),
                    ]),
            ]);
    }
}
