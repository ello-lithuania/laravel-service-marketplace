<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Teikėjo profilio būsena.
 */
enum ProviderStatus: string implements HasLabel
{
    use HasFilamentLabel;

    case Pending = 'pending';
    case Active = 'active';
    case Hidden = 'hidden';
    case Suspended = 'suspended';

    /**
     * Lietuviškas pavadinimas UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Nebaigtas / laukia',
            self::Active => 'Aktyvus',
            self::Hidden => 'Paslėptas',
            self::Suspended => 'Užblokuotas',
        };
    }
}
