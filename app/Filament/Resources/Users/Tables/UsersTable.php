<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Actions\UserModerationActions;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Teikėjo profilio būsena reikalinga „Atblokuoti" formai – užkraunam iš karto (be N+1)
            ->modifyQueryUsing(fn (Builder $query) => $query->with('providerProfile:id,user_id,status'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Vardas, pavardė')
                    // name – accessor, todėl ieškom ir rikiuojam pagal tikrus stulpelius
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['last_name', 'first_name'])
                    ->description(fn (User $record): string => $record->email),
                TextColumn::make('email')
                    ->label('El. paštas')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('role')
                    ->label('Rolė')
                    ->badge()
                    ->color(fn (UserRole $state): string => match ($state) {
                        UserRole::Admin => 'danger',
                        UserRole::Provider => 'info',
                        UserRole::Client => 'gray',
                    }),
                IconColumn::make('email_verified_at')
                    ->label('Patvirtintas')
                    ->state(fn (User $record): bool => $record->email_verified_at !== null)
                    ->boolean(),
                TextColumn::make('banned_at')
                    ->label('Užblokuotas')
                    ->dateTime('Y-m-d', 'Europe/Vilnius')
                    ->badge()
                    ->color('danger')
                    ->placeholder('–')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Registravosi')
                    ->dateTime('Y-m-d H:i', 'Europe/Vilnius')
                    ->sortable(),
                TextColumn::make('last_seen_at')
                    ->label('Paskutinį kartą matytas')
                    ->since()
                    ->placeholder('–')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label('Ištrintas')
                    ->dateTime('Y-m-d', 'Europe/Vilnius')
                    ->placeholder('–')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Rolė')
                    ->options(UserRole::class),
                TernaryFilter::make('banned_at')
                    ->label('Užblokuotas')
                    ->nullable()
                    ->trueLabel('Tik užblokuoti')
                    ->falseLabel('Tik aktyvūs'),
                TernaryFilter::make('email_verified_at')
                    ->label('El. paštas patvirtintas')
                    ->nullable()
                    ->trueLabel('Patvirtintas')
                    ->falseLabel('Nepatvirtintas'),
                // Ištrinti = anonimizuoti pagal BDAR (soft delete)
                TrashedFilter::make()->label('Ištrinti vartotojai'),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    UserModerationActions::ban(),
                    UserModerationActions::unban(),
                ]),
            ]);
    }
}
