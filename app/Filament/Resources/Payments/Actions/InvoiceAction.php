<?php

namespace App\Filament\Resources\Payments\Actions;

use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * „Sąskaita PDF" – ta pati nuoroda, kurią mato teikėjas (InvoiceController + PaymentPolicy::downloadInvoice).
 */
final class InvoiceAction
{
    public static function make(): Action
    {
        return Action::make('invoice')
            ->label('Sąskaita PDF')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color('gray')
            ->visible(fn (Payment $record): bool => $record->hasInvoice())
            ->url(fn (Payment $record): string => route('payments.invoice', $record))
            ->openUrlInNewTab();
    }
}
