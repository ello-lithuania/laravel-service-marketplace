<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabel;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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

    /**
     * Kainos prierašas: „19 € / mėn.", „190 € / metus".
     */
    public function perLabel(): string
    {
        return match ($this) {
            self::Month => 'mėn.',
            self::Year => 'metus',
        };
    }

    /**
     * Data po vieno laikotarpio. NoOverflow: sausio 31 + 1 mėn. = vasario 28, o ne kovo 3.
     */
    public function addTo(CarbonInterface $date): CarbonImmutable
    {
        $date = CarbonImmutable::instance($date);

        return match ($this) {
            self::Month => $date->addMonthNoOverflow(),
            self::Year => $date->addYearNoOverflow(),
        };
    }
}
