<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Mokėjimų tiekėjas. Kodas, kuris su juo kalbasi, – app/Services/Payments (sąsaja PaymentGateway).
 */
enum PaymentGateway: string implements HasLabel
{
    use HasFilamentLabel;

    case Paysera = 'paysera';
    case Stripe = 'stripe';
    case Manual = 'manual';
    // Etapas 7: netikras tiekėjas dev'ui ir testams – „apmokama" vienu mygtuku, be tikrų pinigų
    case Fake = 'fake';

    /**
     * Lietuviškas pavadinimas UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Paysera => 'Paysera',
            self::Stripe => 'Stripe',
            self::Manual => 'Rankinis',
            self::Fake => 'Testinis',
        };
    }
}
