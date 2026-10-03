<?php

namespace App\Actions\Moderation;

use App\Models\ProviderProfile;

/**
 * Ženklelis „Patikrintas": administratorius patikrino dokumentus (provider_profiles.verified_at).
 * Laiko žyma, o ne true/false: „patikrintas" išvedamas iš verified_at IS NOT NULL, o kartu matosi, kada patikrinta.
 */
final class SetProviderVerification
{
    public function handle(ProviderProfile $profile, bool $verified): ProviderProfile
    {
        // verified_at nėra Fillable – keičia tik administratorius
        $profile->forceFill(['verified_at' => $verified ? ($profile->verified_at ?? now()) : null])->save();

        return $profile;
    }
}
