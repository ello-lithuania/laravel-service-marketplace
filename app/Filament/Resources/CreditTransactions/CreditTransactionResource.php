<?php

namespace App\Filament\Resources\CreditTransactions;

use App\Filament\Resources\CreditTransactions\Pages\ListCreditTransactions;
use App\Filament\Resources\CreditTransactions\Tables\CreditTransactionsTable;
use App\Models\CreditTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Kreditų ledger'is administratoriui – tik skaityti (įrašai nekeičiami, docs/DB_SCHEMA.md 2.8).
 * Klaidos taisomos veiksmu „Koreguoti kreditus" – jis įrašo naują admin_adjustment eilutę.
 */
class CreditTransactionResource extends Resource
{
    protected static ?string $model = CreditTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Finansai';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'kreditų operacija';

    protected static ?string $pluralModelLabel = 'kreditų operacijos';

    // Lietuviškas URL: /admin/kreditu-operacijos
    protected static ?string $slug = 'kreditu-operacijos';

    /**
     * Filament antraštėms ir meniu daro „Title Case" („Kreditų Operacijos"); lietuviškai didžioji – tik pirma raidė.
     */
    public static function getTitleCasePluralModelLabel(): string
    {
        return Str::ucfirst(static::getPluralModelLabel());
    }

    public static function getTitleCaseModelLabel(): string
    {
        return Str::ucfirst(static::getModelLabel());
    }

    public static function table(Table $table): Table
    {
        return CreditTransactionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCreditTransactions::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'providerProfile' => fn ($query) => $query->withTrashed(),
        ]);
    }
}
