<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\Actions\CreditNoteAction;
use App\Filament\Resources\Payments\Actions\InvoiceAction;
use App\Filament\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        // Etapas 9b: kreditinė sąskaita
        return [InvoiceAction::make(), CreditNoteAction::make()];
    }
}
