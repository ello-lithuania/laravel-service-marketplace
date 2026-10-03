<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Prenumeratos būsena (perėjimų lentelė – docs/DB_SCHEMA.md → subscriptions).
 */
enum SubscriptionStatus: string implements HasColor, HasLabel
{
    use HasFilamentLabel;

    case Active = 'active';
    case Cancelled = 'cancelled';
    case PastDue = 'past_due';
    case Expired = 'expired';

    /**
     * Lietuviškas pavadinimas UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktyvi',
            self::Cancelled => 'Atšaukta (galioja iki pabaigos)',
            self::PastDue => 'Nesumokėta',
            self::Expired => 'Pasibaigusi',
        };
    }

    /**
     * Į kurias būsenas galima pereiti iš šios. Kaip ServiceRequestStatus ir OfferStatus (Etapas 5):
     * Action prieš keisdama būseną paklausia canTransitionTo().
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Active => [self::Cancelled, self::PastDue, self::Expired],
            // Atšauktai, bet dar galiojančiai prenumeratai apmokėjus pratęsimą – vėl aktyvi
            self::Cancelled => [self::Active, self::Expired],
            self::PastDue => [self::Active, self::Expired],
            self::Expired => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /**
     * Būsenos, kuriose prenumerata dar „gyva": galioja arba laukia apmokėjimo (malonės laikotarpis).
     *
     * @return list<self>
     */
    public static function live(): array
    {
        return [self::Active, self::Cancelled, self::PastDue];
    }

    /**
     * Ženklelio spalva Filament panelėje.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Cancelled => 'warning',
            self::PastDue => 'danger',
            self::Expired => 'gray',
        };
    }
}
