<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Užklausos būsena (perėjimai – docs/STATES.md 1 sk.).
 *
 * Būsenų mašina: allowedTransitions() – vienintelė vieta, kur aprašyta, iš kur į kur galima pereiti.
 * Perėjimus vykdo Action klasės (app/Actions/ServiceRequests), kurios prieš keisdamos statusą klausia canTransitionTo().
 */
enum ServiceRequestStatus: string implements HasColor, HasLabel
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

    /**
     * Į kurias būsenas galima pereiti iš šios (docs/STATES.md 1 sk. diagrama).
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Open, self::Cancelled],
            self::Open => [self::InProgress, self::Cancelled, self::Expired],
            self::InProgress => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled, self::Expired => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /**
     * Galutinė būsena – iš jos niekur nepereinama.
     */
    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * Ženklelio spalva Filament panelėje.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Open => 'info',
            self::InProgress => 'primary',
            self::Completed => 'success',
            self::Cancelled, self::Expired => 'gray',
        };
    }
}
