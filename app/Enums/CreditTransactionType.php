<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Kreditų operacijos tipas (ledger).
 */
enum CreditTransactionType: string implements HasColor, HasLabel
{
    use HasFilamentLabel;

    case Purchase = 'purchase';
    case Subscription = 'subscription';
    case Offer = 'offer';
    case Refund = 'refund';
    case Bonus = 'bonus';
    case AdminAdjustment = 'admin_adjustment';
    case Expiry = 'expiry';
    // --- Etapas 9b: kreditai atimti grąžinus mokėjimą (RefundPayment) ---
    case PaymentRefund = 'payment_refund';

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
            self::PaymentRefund => 'Mokėjimo grąžinimas',
        };
    }

    /**
     * Ženklelio spalva Filament panelėje: gauti kreditai – žalia ar mėlyna, išleisti – pilka.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::Purchase, self::Subscription => 'success',
            self::Bonus, self::Refund => 'info',
            self::AdminAdjustment => 'warning',
            self::Offer, self::Expiry => 'gray',
            self::PaymentRefund => 'danger',
        };
    }
}
