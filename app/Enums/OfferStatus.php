<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Pasiūlymo būsena (perėjimai – docs/STATES.md 2 sk.).
 * „Peržiūrėtas" – ne būsena, o laiko žyma viewed_at (kodėl – STATES.md).
 */
enum OfferStatus: string implements HasColor, HasLabel
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

    /**
     * Į kurias būsenas galima pereiti iš šios (docs/STATES.md 2 sk. diagrama).
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Accepted, self::Declined, self::Withdrawn],
            self::Accepted, self::Declined, self::Withdrawn => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

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
            self::Accepted => 'success',
            self::Declined, self::Withdrawn => 'gray',
        };
    }
}
