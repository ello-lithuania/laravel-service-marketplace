<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Vartotojo rolė.
 */
enum UserRole: string implements HasLabel
{
    use HasFilamentLabel;

    case Client = 'client';
    case Provider = 'provider';
    case Admin = 'admin';

    /**
     * Lietuviškas pavadinimas UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Client => 'Klientas',
            self::Provider => 'Paslaugų teikėjas',
            self::Admin => 'Administratorius',
        };
    }
}
