<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\Categories\Schemas\CategoryForm;
use App\Filament\Resources\Categories\Tables\CategoriesTable;
use App\Models\Category;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Paslaugų kategorijų medis admin panelėje. Resource = modelio CRUD: sąrašas, kūrimas, redagavimas.
 * https://filamentphp.com/docs/5.x/resources/overview
 */
class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Žinynai';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'kategorija';

    protected static ?string $pluralModelLabel = 'kategorijos';

    protected static ?string $recordTitleAttribute = 'name';

    // Lietuviškas URL: /admin/kategorijos
    protected static ?string $slug = 'kategorijos';

    public static function form(Schema $schema): Schema
    {
        return CategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CategoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/kurti'),
            'edit' => EditCategory::route('/{record}/redaguoti'),
        ];
    }
}
