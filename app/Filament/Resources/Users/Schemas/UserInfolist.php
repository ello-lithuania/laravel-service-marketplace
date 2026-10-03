<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Resources\ProviderProfiles\ProviderProfileResource;
use App\Models\User;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Vartotojo peržiūra administratoriui. Ryšiai ir skaitliukai užkraunami UserResource::getRecordRouteBindingEloquentQuery().
 */
class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Paskyra užblokuota')
                    ->visible(fn (User $record): bool => $record->isBanned())
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('banned_at')->label('Užblokuota')->dateTime('Y-m-d H:i', 'Europe/Vilnius'),
                        TextEntry::make('ban_reason')->label('Priežastis')->color('danger'),
                    ]),

                Section::make('Paskyra')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->label('Vardas, pavardė')->weight('bold'),
                        TextEntry::make('role')->label('Rolė')->badge(),
                        TextEntry::make('email')->label('El. paštas')->copyable(),
                        TextEntry::make('phone')->label('Telefonas')->placeholder('–'),
                        TextEntry::make('city.name')->label('Miestas / rajonas')->placeholder('–'),
                        IconEntry::make('email_verified_at')
                            ->label('El. paštas patvirtintas')
                            ->state(fn (User $record): bool => $record->email_verified_at !== null)
                            ->boolean(),
                        TextEntry::make('created_at')->label('Registravosi')->dateTime('Y-m-d H:i', 'Europe/Vilnius'),
                        TextEntry::make('last_seen_at')->label('Paskutinį kartą matytas')->since()->placeholder('–'),
                        TextEntry::make('deleted_at')
                            ->label('Ištrintas (anonimizuotas)')
                            ->dateTime('Y-m-d H:i', 'Europe/Vilnius')
                            ->visible(fn (User $record): bool => $record->trashed()),
                    ]),

                Section::make('Veikla')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('service_requests_count')->label('Užklausos (kaip klientas)'),
                        TextEntry::make('written_reviews_count')->label('Parašyti atsiliepimai'),
                        TextEntry::make('payments_count')->label('Mokėjimai'),
                        TextEntry::make('filed_complaints_count')->label('Pateikti skundai'),
                        TextEntry::make('complaints_count')
                            ->label('Skundai dėl šio vartotojo')
                            ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                    ]),

                Section::make('Teikėjo profilis')
                    ->visible(fn (User $record): bool => $record->providerProfile !== null)
                    ->columnSpanFull()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('providerProfile.display_name')
                            ->label('Pavadinimas')
                            ->url(fn (User $record): ?string => $record->providerProfile === null
                                ? null
                                : ProviderProfileResource::getUrl('view', ['record' => $record->providerProfile])),
                        TextEntry::make('providerProfile.status')->label('Būsena')->badge(),
                        IconEntry::make('providerProfile.verified_at')
                            ->label('Patikrintas')
                            ->state(fn (User $record): bool => $record->providerProfile?->verified_at !== null)
                            ->boolean(),
                        TextEntry::make('providerProfile.credits_balance')->label('Kreditai'),
                    ]),
            ]);
    }
}
