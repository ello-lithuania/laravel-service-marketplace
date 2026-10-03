<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Teikėjų sąrašo rikiavimas. Reikšmės lietuviškos, nes matomos URL: /meistrai?rikiuoti=atsiliepimai.
 */
enum ProviderSort: string implements HasLabel
{
    use HasFilamentLabel;

    /** Tik paieškoje: kuo geriau tekstas atitinka užklausą. */
    case Relevance = 'aktualumas';
    case Rating = 'reitingas';
    case Reviews = 'atsiliepimai';
    case CompletedJobs = 'darbai';
    case Newest = 'naujausi';

    /**
     * Lietuviškas pavadinimas UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Relevance => 'Tinkamiausi',
            self::Rating => 'Geriausiai įvertinti',
            self::Reviews => 'Daugiausia atsiliepimų',
            self::CompletedJobs => 'Daugiausia atliktų darbų',
            self::Newest => 'Naujausi',
        };
    }

    /**
     * Rikiavimo pasirinkimai sąrašui; „Tinkamiausi" prasmingas tik ieškant tekstu.
     *
     * @return list<self>
     */
    public static function options(bool $withRelevance = false): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $sort): bool => $withRelevance || $sort !== self::Relevance,
        ));
    }
}
