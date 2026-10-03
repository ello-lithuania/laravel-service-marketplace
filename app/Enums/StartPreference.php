<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Kada klientas nori pradėti darbus.
 */
enum StartPreference: string implements HasLabel
{
    use HasFilamentLabel;

    case Asap = 'asap';
    case ThisWeek = 'this_week';
    case ThisMonth = 'this_month';
    case Flexible = 'flexible';
    case Date = 'date';

    /**
     * Lietuviškas pavadinimas UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Asap => 'Kuo skubiau',
            self::ThisWeek => 'Šią savaitę',
            self::ThisMonth => 'Šį mėnesį',
            self::Flexible => 'Lanksčiai',
            self::Date => 'Konkrečią dieną',
        };
    }
}
