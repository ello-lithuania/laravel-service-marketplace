<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Skundo priežastis.
 */
enum ComplaintReason: string implements HasLabel
{
    use HasFilamentLabel;

    case Spam = 'spam';
    case Fraud = 'fraud';
    case Offensive = 'offensive';
    case FakeReview = 'fake_review';
    case WrongInfo = 'wrong_info';
    case Other = 'other';

    /**
     * Lietuviškas pavadinimas UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Spam => 'Šlamštas',
            self::Fraud => 'Sukčiavimas',
            self::Offensive => 'Įžeidžiantis turinys',
            self::FakeReview => 'Netikras atsiliepimas',
            self::WrongInfo => 'Klaidinga informacija',
            self::Other => 'Kita',
        };
    }
}
