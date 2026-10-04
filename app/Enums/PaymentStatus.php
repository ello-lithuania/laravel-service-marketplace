<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Mokėjimo būsena.
 */
enum PaymentStatus: string implements HasColor, HasLabel
{
    use HasFilamentLabel;

    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    /**
     * Lietuviškas pavadinimas UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Laukiama',
            self::Paid => 'Apmokėta',
            self::Failed => 'Nepavyko',
            self::Cancelled => 'Atšaukta',
            self::Refunded => 'Grąžinta',
        };
    }

    /**
     * Ženklelio spalva Filament panelėje.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Paid => 'success',
            self::Failed => 'danger',
            self::Cancelled, self::Refunded => 'gray',
        };
    }

    // --- Etapas 9b ---

    /**
     * Leidžiami perėjimai (lentelė – docs/DB_SCHEMA.md → payments). Nepavykęs ar atšauktas mokėjimas dar gali
     * tapti apmokėtu (tiekėjas patvirtino vėliau), o grąžintas – galutinis.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Paid, self::Failed, self::Cancelled],
            self::Failed, self::Cancelled => [self::Paid],
            self::Paid => [self::Refunded],
            self::Refunded => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }
}
