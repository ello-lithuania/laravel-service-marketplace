<?php

namespace App\Filament\Resources\Payments\Actions;

use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * „Kreditinė sąskaita PDF" (Etapas 9) – ta pati nuoroda, kurią mato teikėjas (CreditNoteController +
 * PaymentPolicy::downloadCreditNote). Matoma tik grąžintam mokėjimui.
 */
final class CreditNoteAction
{
    public static function make(): Action
    {
        return Action::make('creditNote')
            ->label('Kreditinė sąskaita PDF')
            ->icon(Heroicon::OutlinedDocumentMinus)
            ->color('gray')
            ->visible(fn (Payment $record): bool => $record->isRefunded() && $record->loadMissing('refund')->refund !== null)
            ->url(fn (Payment $record): string => route('payments.credit-note', $record))
            ->openUrlInNewTab();
    }
}
