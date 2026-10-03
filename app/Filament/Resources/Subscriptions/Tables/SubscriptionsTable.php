<?php

namespace App\Filament\Resources\Subscriptions\Tables;

use App\Enums\SubscriptionStatus;
use App\Filament\Resources\Subscriptions\Actions\CancelSubscriptionAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('ends_at', 'desc')
            ->columns([
                TextColumn::make('providerProfile.display_name')
                    ->label('Teikėjas')
                    ->searchable(),
                TextColumn::make('plan.name')
                    ->label('Planas'),
                TextColumn::make('status')
                    ->label('Būsena')
                    ->badge()
                    ->sortable(),
                TextColumn::make('starts_at')
                    ->label('Pradžia')
                    ->date('Y-m-d', 'Europe/Vilnius')
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label('Apmokėta iki')
                    ->date('Y-m-d', 'Europe/Vilnius')
                    ->sortable(),
                IconColumn::make('auto_renew')
                    ->label('Pratęsiama')
                    ->boolean(),
                TextColumn::make('credits_granted_until')
                    ->label('Kreditai suteikti iki')
                    ->dateTime('Y-m-d H:i', 'Europe/Vilnius')
                    ->placeholder('–')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Būsena')
                    ->options(SubscriptionStatus::class),
                SelectFilter::make('plan')
                    ->label('Planas')
                    ->relationship('plan', 'name')
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                CancelSubscriptionAction::make(),
            ]);
    }
}
