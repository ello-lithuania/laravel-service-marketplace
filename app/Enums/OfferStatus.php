<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Pasiūlymo būsena (perėjimai – docs/STATES.md).
 */
enum OfferStatus: string implements HasLabel
{
    use HasFilamentLabel;

    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';

    /**
     * Lietuviškas pavadinimas UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Laukia atsakymo',
            self::Accepted => 'Priimtas',
            self::Declined => 'Atmestas',
            self::Withdrawn => 'Atšauktas teikėjo',
        };
    }
}
