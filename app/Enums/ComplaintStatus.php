<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Skundo būsena.
 */
enum ComplaintStatus: string implements HasLabel
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
}
