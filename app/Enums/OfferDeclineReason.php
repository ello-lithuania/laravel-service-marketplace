<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Kodėl pasiūlymas tapo „declined" (docs/STATES.md 2 sk.). DB nesaugoma – naudojama pranešime teikėjui.
 */
enum OfferDeclineReason: string implements HasLabel
{
    use HasFilamentLabel;

    case ClientDeclined = 'client_declined';
    case OtherAccepted = 'other_accepted';
    case RequestCancelled = 'request_cancelled';
    case RequestExpired = 'request_expired';

    /**
     * Lietuviškas pavadinimas UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::ClientDeclined => 'Klientas atmetė',
            self::OtherAccepted => 'Pasirinktas kitas pasiūlymas',
            self::RequestCancelled => 'Užklausa atšaukta',
            self::RequestExpired => 'Užklausa pasibaigė',
        };
    }
}
