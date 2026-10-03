<?php

namespace App\Filament\Resources\ProviderProfiles\Schemas;

use App\Enums\ProviderStatus;
use App\Filament\Resources\ProviderProfiles\Tables\ProviderProfilesTable;
use App\Filament\Resources\Users\UserResource;
use App\Models\PortfolioItem;
use App\Models\ProviderProfile;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Teikėjo profilio peržiūra administratoriui: duomenys, paskyra, rodikliai, paslaugos, zonos ir nuotraukos.
 * Visi ryšiai užkraunami ProviderProfileResource::getRecordRouteBindingEloquentQuery().
 */
class ProviderProfileInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Profilis')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('display_name')->label('Pavadinimas')->weight('bold'),
                        TextEntry::make('status')
                            ->label('Būsena')
                            ->badge()
                            ->color(fn (ProviderStatus $state): string => ProviderProfilesTable::statusColor($state)),
                        TextEntry::make('type')->label('Tipas')->badge()->color('gray'),
                        TextEntry::make('verified_at')
                            ->label('Patikrintas')
                            ->dateTime('Y-m-d', 'Europe/Vilnius')
                            ->placeholder('Ne'),
                        TextEntry::make('headline')->label('Antraštė')->columnSpanFull()->placeholder('–'),
                        TextEntry::make('description')->label('Aprašymas')->columnSpanFull()->prose()->placeholder('–'),
                        TextEntry::make('city.name')->label('Bazinis miestas'),
                        TextEntry::make('years_experience')->label('Patirtis, m.')->placeholder('–'),
                        TextEntry::make('company_code')->label('Įmonės kodas')->placeholder('–'),
                        TextEntry::make('vat_code')->label('PVM kodas')->placeholder('–'),
                        TextEntry::make('website')->label('Svetainė')->placeholder('–'),
                        TextEntry::make('slug')->label('Adresas')->prefix('/meistrai/'),
                    ]),

                Section::make('Paskyra ir rodikliai')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('Savininkas')
                            ->url(fn (ProviderProfile $record): string => UserResource::getUrl('view', ['record' => $record->user])),
                        TextEntry::make('user.email')->label('El. paštas')->copyable(),
                        TextEntry::make('user.banned_at')
                            ->label('Paskyra')
                            ->state(fn (ProviderProfile $record): string => $record->user->banned_at !== null ? 'Užblokuota' : 'Aktyvi')
                            ->badge()
                            ->color(fn (string $state): string => $state === 'Užblokuota' ? 'danger' : 'success'),
                        TextEntry::make('rating_avg')->label('Reitingas')->numeric(decimalPlaces: 2),
                        TextEntry::make('reviews_count')->label('Atsiliepimai'),
                        TextEntry::make('completed_jobs_count')->label('Atlikti darbai'),
                        TextEntry::make('offers_count')->label('Išsiųsti pasiūlymai'),
                        TextEntry::make('credits_balance')->label('Kreditų likutis'),
                        TextEntry::make('created_at')->label('Sukurta')->dateTime('Y-m-d H:i', 'Europe/Vilnius'),
                    ]),

                Section::make('Paslaugos ir aptarnavimo zonos')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('categories.name')
                            ->label('Paslaugos')
                            ->badge()
                            ->placeholder('Nepasirinkta'),
                        TextEntry::make('serviceAreas.name')
                            ->label('Zonos')
                            ->badge()
                            ->color('gray')
                            ->state(fn (ProviderProfile $record): array => $record->serves_whole_country
                                ? ['Visa Lietuva']
                                : $record->serviceAreas->pluck('name')->all())
                            ->placeholder('Nepasirinkta'),
                    ]),

                Section::make('Logotipas ir viršelis')
                    ->columnSpanFull()
                    ->columns(2)
                    ->collapsible()
                    ->schema([
                        ImageEntry::make('logo')
                            ->label('Logotipas')
                            ->state(fn (ProviderProfile $record): ?string => $record->logoUrl())
                            ->imageHeight(96)
                            ->placeholder('Neįkeltas'),
                        ImageEntry::make('cover')
                            ->label('Viršelis')
                            ->state(fn (ProviderProfile $record): ?string => $record->coverUrl())
                            ->imageHeight(96)
                            ->placeholder('Neįkeltas'),
                    ]),

                Section::make('Atlikti darbai')
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        RepeatableEntry::make('portfolioItems')
                            ->hiddenLabel()
                            ->placeholder('Darbų nėra')
                            ->grid(2)
                            ->schema([
                                TextEntry::make('title')->hiddenLabel()->weight('bold'),
                                ImageEntry::make('images')
                                    ->hiddenLabel()
                                    ->state(fn (PortfolioItem $record): array => array_column($record->imageUrls(), 'thumb'))
                                    ->imageHeight(72)
                                    ->stacked(false)
                                    ->limit(6)
                                    ->placeholder('Be nuotraukų'),
                            ]),
                    ]),
            ]);
    }
}
