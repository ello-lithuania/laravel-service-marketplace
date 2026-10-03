<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Užklausos būsena (perėjimai – docs/STATES.md).
 */
enum ServiceRequestStatus: string implements HasLabel
{
    use HasFilamentLabel;

    case Pending = 'pending';
    case Open = 'open';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    /**
     * Lietuviškas pavadinimas UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Laukia patvirtinimo',
            self::Open => 'Laukia pasiūlymų',
            self::InProgress => 'Vykdoma',
            self::Completed => 'Atlikta',
            self::Cancelled => 'Atšaukta',
            self::Expired => 'Pasibaigė',
        };
    }
}
