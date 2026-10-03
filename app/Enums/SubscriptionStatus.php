<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Prenumeratos būsena.
 */
enum SubscriptionStatus: string implements HasLabel
{
    use HasFilamentLabel;

    case Active = 'active';
    case Cancelled = 'cancelled';
    case PastDue = 'past_due';
    case Expired = 'expired';

    /**
     * Lietuviškas pavadinimas UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktyvi',
            self::Cancelled => 'Atšaukta (galioja iki pabaigos)',
            self::PastDue => 'Nesumokėta',
            self::Expired => 'Pasibaigusi',
        };
    }
}
