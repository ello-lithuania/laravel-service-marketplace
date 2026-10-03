<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Kreditų operacijos tipas (ledger).
 */
enum CreditTransactionType: string implements HasLabel
{
    use HasFilamentLabel;

    case Purchase = 'purchase';
    case Subscription = 'subscription';
    case Offer = 'offer';
    case Refund = 'refund';
    case Bonus = 'bonus';
    case AdminAdjustment = 'admin_adjustment';
    case Expiry = 'expiry';

    /**
     * Lietuviškas pavadinimas UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Pirkimas',
            self::Subscription => 'Prenumerata',
            self::Offer => 'Pasiūlymas',
            self::Refund => 'Grąžinimas',
            self::Bonus => 'Dovana',
            self::AdminAdjustment => 'Koregavimas',
            self::Expiry => 'Pasibaigė',
        };
    }
}
