<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Kainos vienetas („nuo 15 € / m²").
 */
enum PriceUnit: string implements HasLabel
{
    use HasFilamentLabel;

    case Hour = 'hour';
    case Job = 'job';
    case SquareMeter = 'm2';
    case Meter = 'm';
    case Unit = 'unit';

    /**
     * Lietuviškas pavadinimas UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Hour => 'val.',
            self::Job => 'darbas',
            self::SquareMeter => 'm²',
            self::Meter => 'm',
            self::Unit => 'vnt.',
        };
    }
}
