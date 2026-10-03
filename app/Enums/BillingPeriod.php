<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Prenumeratos mokėjimo laikotarpis.
 */
enum BillingPeriod: string implements HasLabel
{
    use HasFilamentLabel;

    case Month = 'month';
    case Year = 'year';

    /**
     * Lietuviškas pavadinimas UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Month => 'Mėnuo',
            self::Year => 'Metai',
        };
    }
}
