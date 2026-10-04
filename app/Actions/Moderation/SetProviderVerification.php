<?php

namespace App\Actions\Moderation;

use App\Models\ProviderProfile;
use App\Models\User;
use App\Notifications\ProviderVerified;

/**
 * Ženklelis „Patikrintas": administratorius patikrino dokumentus (provider_profiles.verified_at).
 * Laiko žyma, o ne true/false: „patikrintas" išvedamas iš verified_at IS NOT NULL, o kartu matosi, kada patikrinta.
 *
 * Etapas 9c: pažymėjus (anksčiau nepatikrintą) profilį teikėjas gauna ProviderVerified. Nuėmus ženklelį –
 * nepranešam: tai retas atvejis, kai administratorius paprastai susisiekia asmeniškai ir paaiškina priežastį.
 */
final class SetProviderVerification
{
    public function handle(ProviderProfile $profile, bool $verified): ProviderProfile
    {
        $newlyVerified = $verified && $profile->verified_at === null;

        // verified_at nėra Fillable – keičia tik administratorius
        $profile->forceFill(['verified_at' => $verified ? ($profile->verified_at ?? now()) : null])->save();

        if ($newlyVerified) {
            // Iš naujo, ne $profile->user: Filament sąraše ryšys užkrautas be email_verified_at (žr. ChangeProviderStatus)
            $user = User::query()->find($profile->user_id);

            if ($user !== null && $user->banned_at === null) {
                $user->notify(new ProviderVerified($profile));
            }
        }

        return $profile;
    }
}
