<?php

namespace App\Actions\ProviderProfile;

use App\Models\ProviderProfile;
use Illuminate\Support\Str;

/**
 * Unikalus viešo profilio adresas /meistrai/{slug}: „Jonas Šimkus" → „jonas-simkus", „jonas-simkus-2"…
 *
 * Slug'as sukuriamas vieną kartą (kai sukuriamas profilis) ir vėliau nekeičiamas, net jei pasikeičia
 * pavadinimas: pasikeitęs URL sugadintų nuorodas Google'e ir klientų išsaugotas nuorodas.
 */
class GenerateProviderSlug
{
    /** Stulpelio ilgis 170; paliekam vietos priesagai „-123". */
    private const MAX_BASE_LENGTH = 160;

    public function handle(string $displayName): string
    {
        // Str::slug transliteruoja lietuviškas raides: „š" → „s", „ų" → „u"
        $base = rtrim(Str::limit(Str::slug($displayName), self::MAX_BASE_LENGTH, ''), '-');

        if ($base === '') {
            $base = 'teikejas';
        }

        $slug = $base;
        $suffix = 2;

        // withTrashed(): ištrinto (soft delete) profilio adresas lieka užimtas – jį galima atkurti
        while (ProviderProfile::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
