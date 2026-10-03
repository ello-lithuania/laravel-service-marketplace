<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Kategorija')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('parent_id')
                            ->label('Tėvinė kategorija')
                            ->helperText('Tuščia – 1 lygio sritis. Lygis (1–3) nustatomas automatiškai.')
                            // Tėvu gali būti tik 1 ir 2 lygis – taip medis niekada neturės daugiau nei 3 lygių
                            ->relationship('parent', 'name', fn (Builder $query) => $query->where('depth', '<', Category::MAX_DEPTH)->orderBy('depth')->orderBy('name'))
                            ->searchable()
                            ->preload()
                            ->live()
                            // Kategorijos su vaikais perkelti negalima – pasikeistų visų vaikų lygiai
                            ->disabled(fn (?Category $record): bool => $record !== null && $record->children()->exists()),
                        TextInput::make('name')
                            ->label('Pavadinimas')
                            ->required()
                            ->maxLength(120)
                            ->live(onBlur: true)
                            // Kuriant slug užpildomas iš pavadinimo; redaguojant nekeičiam, kad nesulūžtų URL
                            ->afterStateUpdated(function (string $operation, ?string $state, Set $set): void {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('URL dalis (slug)')
                            ->required()
                            ->maxLength(140)
                            ->alphaDash()
                            ->unique(ignoreRecord: true),
                        TextInput::make('icon')
                            ->label('Ikona')
                            ->helperText('Lucide ikonos pavadinimas, pvz. „hammer". Rodoma tik 1 lygio sritims.')
                            ->maxLength(60)
                            ->visible(fn (Get $get): bool => blank($get('parent_id'))),
                        TextInput::make('offer_cost_credits')
                            ->label('Pasiūlymo kaina (kreditais)')
                            ->required()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(20)
                            ->default(1),
                        TextInput::make('sort_order')
                            ->label('Rikiavimas')
                            ->required()
                            ->integer()
                            ->minValue(0)
                            ->default(0),
                        Textarea::make('description')
                            ->label('Aprašymas')
                            ->rows(3)
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Aktyvi')
                            ->helperText('Neaktyvi kategorija nerodoma svetainėje. Kategorijų netrinam – jas išjungiam.')
                            ->default(true),
                    ]),
                Section::make('SEO')
                    ->columnSpanFull()
                    ->description('Jei tušti – sugeneruojami automatiškai iš pavadinimo.')
                    ->collapsed()
                    ->schema([
                        TextInput::make('meta_title')
                            ->label('Meta pavadinimas')
                            ->maxLength(160),
                        Textarea::make('meta_description')
                            ->label('Meta aprašymas')
                            ->maxLength(300)
                            ->rows(2),
                    ]),
            ]);
    }
}
