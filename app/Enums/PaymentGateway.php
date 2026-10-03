<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Mokėjimų tiekėjas.
 */
enum PaymentGateway: string implements HasLabel
{
    use HasFilamentLabel;

    case Paysera = 'paysera';
    case Stripe = 'stripe';
    case Manual = 'manual';

    /**
     * Lietuviškas pavadinimas UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Paysera => 'Paysera',
            self::Stripe => 'Stripe',
            self::Manual => 'Rankinis',
        };
    }
}
