<?php

namespace App\Enums;

use App\Enums\Concerns\HasFilamentLabel;
use Filament\Support\Contracts\HasLabel;

/**
 * Vietos svetainės dizaine, kuriose rodoma nuotrauka (docs/DB_SCHEMA.md → site_photos).
 * Nauja vieta = naujas case; nuotraukos nėra – puslapis rodo atsarginį dizainą (spalvas, iliustraciją).
 */
enum SitePhotoKey: string implements HasLabel
{
    use HasFilamentLabel;

    /** Pradžios puslapio viršus (didelė nuotrauka šalia paieškos) */
    case Hero = 'hero';

    /** Kvietimas teikėjams („Tapkite teikėju") pradžios ir kainų puslapiuose */
    case Providers = 'providers';

    /** Kvietimas klientams sukurti užklausą („Kaip tai veikia" skyrius) */
    case Request = 'request';

    /** Prisijungimo ir registracijos puslapių šoninė nuotrauka */
    case Auth = 'auth';

    public function label(): string
    {
        return match ($this) {
            self::Hero => 'Pradžios puslapio viršus',
            self::Providers => 'Kvietimas teikėjams',
            self::Request => 'Kvietimas sukurti užklausą',
            self::Auth => 'Prisijungimo ir registracijos puslapiai',
        };
    }
}
