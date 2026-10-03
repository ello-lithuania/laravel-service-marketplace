<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Skundo būsena.
 */
enum ComplaintStatus: string implements HasColor, HasLabel
{
    use HasFilamentLabel;

    case Open = 'open';
    case InReview = 'in_review';
    case Resolved = 'resolved';
    case Rejected = 'rejected';

    /**
     * Lietuviškas pavadinimas UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Naujas',
            self::InReview => 'Nagrinėjamas',
            self::Resolved => 'Išspręstas',
            self::Rejected => 'Atmestas',
        };
    }

    // --- Etapas 6: skundų nagrinėjimas ---

    /**
     * Neužbaigtas skundas (laukia arba nagrinėjamas) – jį dar galima išspręsti ar atmesti.
     */
    public function isOpen(): bool
    {
        return $this === self::Open || $this === self::InReview;
    }

    /**
     * Ženklelio spalva Filament panelėje.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'danger',
            self::InReview => 'warning',
            self::Resolved => 'success',
            self::Rejected => 'gray',
        };
    }
}
