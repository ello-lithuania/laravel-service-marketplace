<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Filament\Resources\Payments\Schemas\PaymentInfolist;
use App\Filament\Resources\Payments\Tables\PaymentsTable;
use App\Filament\Resources\Payments\Widgets\PaymentStats;
use App\Models\Payment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Mokėjimai admin panelėje: sąrašas su filtrais, peržiūra, sąskaitos PDF, pajamų suvestinė (Etapas 7).
 * Kūrimo ir redagavimo nėra – mokėjimus kuria ir keičia tik Actions (PaymentPolicy neturi create/update).
 */
class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Finansai';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'mokėjimas';

    protected static ?string $pluralModelLabel = 'mokėjimai';

    protected static ?string $recordTitleAttribute = 'uuid';

    // Lietuviškas URL: /admin/mokejimai
    protected static ?string $slug = 'mokejimai';

    public static function infolist(Schema $schema): Schema
    {
        return PaymentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }

    public static function getWidgets(): array
    {
        return [PaymentStats::class];
    }

    /**
     * Mokėtojas (Payment::user apima ir „ištrintus") bei pirkinys užkraunami iš karto – be N+1.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'purchasable']);
    }
}
