<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Teikėjo tipas.
 */
enum ProviderType: string implements HasLabel
{
    use HasFilamentLabel;

    case Individual = 'individual';
    case Company = 'company';

    /**
     * Lietuviškas pavadinimas UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Fizinis asmuo',
            self::Company => 'Įmonė',
        };
    }
}
