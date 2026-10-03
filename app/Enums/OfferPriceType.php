<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Kaip nurodyta pasiūlymo kaina.
 */
enum OfferPriceType: string implements HasLabel
{
    use HasFilamentLabel;

    case Fixed = 'fixed';
    case Hourly = 'hourly';
    case PerUnit = 'per_unit';
    case AfterInspection = 'after_inspection';

    /**
     * Lietuviškas pavadinimas UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fiksuota',
            self::Hourly => 'Už valandą',
            self::PerUnit => 'Už vienetą',
            self::AfterInspection => 'Po apžiūros',
        };
    }
}
