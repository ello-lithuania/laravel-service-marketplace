<?php

namespace App\Filament\Resources\Cities\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('region_id')
                    ->label('Apskritis')
                    ->relationship('region', 'name')
                    ->required()
                    ->preload(),
                TextInput::make('name')
                    ->label('Pavadinimas')
                    ->required()
                    ->maxLength(80)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, ?string $state, Set $set): void {
                        if ($operation === 'create') {
                            $set('slug', Str::slug((string) $state));
                        }
                    }),
                TextInput::make('name_locative')
                    ->label('Vietininkas')
                    ->helperText('Kur? – „Vilniuje", „Kauno rajone". Naudojamas tekstuose ir SEO.')
                    ->required()
                    ->maxLength(80),
                TextInput::make('slug')
                    ->label('URL dalis (slug)')
                    ->required()
                    ->maxLength(80)
                    ->alphaDash()
                    ->unique(ignoreRecord: true),
                TextInput::make('latitude')
                    ->label('Platuma')
                    ->numeric()
                    ->minValue(53.8)
                    ->maxValue(56.5),
                TextInput::make('longitude')
                    ->label('Ilguma')
                    ->numeric()
                    ->minValue(20.9)
                    ->maxValue(26.9),
                TextInput::make('sort_order')
                    ->label('Rikiavimas')
                    ->helperText('Mažesnis skaičius – aukščiau sąraše (didmiesčiai viršuje).')
                    ->required()
                    ->integer()
                    ->minValue(0)
                    ->default(0),
            ]);
    }
}
