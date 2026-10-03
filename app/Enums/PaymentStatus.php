<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Mokėjimo būsena.
 */
enum PaymentStatus: string implements HasColor, HasLabel
{
    use HasFilamentLabel;

    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    /**
     * Lietuviškas pavadinimas UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Laukiama',
            self::Paid => 'Apmokėta',
            self::Failed => 'Nepavyko',
            self::Cancelled => 'Atšaukta',
            self::Refunded => 'Grąžinta',
        };
    }

    /**
     * Ženklelio spalva Filament panelėje.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Paid => 'success',
            self::Failed => 'danger',
            self::Cancelled, self::Refunded => 'gray',
        };
    }
}
