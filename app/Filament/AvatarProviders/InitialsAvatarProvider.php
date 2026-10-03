<?php

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Administratoriaus avataras – inicialai SVG paveikslėlyje (data: URI), sugeneruoti serveryje.
 *
 * Kodėl ne Filament numatytasis UiAvatarsProvider: jis kiekvieną kartą siunčia administratoriaus vardą
 * išoriniam servisui ui-avatars.com. Tai asmens duomenų perdavimas trečiajai šaliai (BDAR) ir papildomas
 * domenas, kurį tektų leisti saugos antraštėje (CSP img-src). Inicialus nupiešti patiems paprasčiau.
 * https://filamentphp.com/docs/5.x/users/overview#setting-up-user-avatars
 */
final class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $initials = collect(preg_split('/\s+/u', trim(Filament::getNameForDefaultAvatar($record))) ?: [])
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->filter()
            ->take(2)
            ->join('');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="#3f3f46"/>'
            .'<text x="50%" y="50%" dy=".35em" text-anchor="middle" fill="#ffffff" '
            .'font-family="sans-serif" font-size="26" font-weight="600">'
            .htmlspecialchars($initials, ENT_XML1)
            .'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
