<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Payments\Widgets\PaymentStats;
use Filament\Resources\Pages\ListRecords;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    /**
     * Pajamų suvestinė virš lentelės.
     */
    protected function getHeaderWidgets(): array
    {
        return [PaymentStats::class];
    }
}
