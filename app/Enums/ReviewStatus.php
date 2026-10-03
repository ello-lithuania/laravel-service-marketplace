<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Atsiliepimo būsena.
 */
enum ReviewStatus: string implements HasColor, HasLabel
{
    use HasFilamentLabel;

    case Pending = 'pending';
    case Published = 'published';
    case Hidden = 'hidden';

    /**
     * Lietuviškas pavadinimas UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Laukia moderavimo',
            self::Published => 'Paskelbtas',
            self::Hidden => 'Paslėptas',
        };
    }

    /**
     * Ženklelio spalva Filament panelėje (Etapas 6, ReviewResource).
     */
    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Published => 'success',
            self::Hidden => 'gray',
        };
    }
}
