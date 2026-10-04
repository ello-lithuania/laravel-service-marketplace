<?php

namespace App\Actions\Moderation;

use App\Enums\ProviderStatus;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Notifications\ProviderStatusChanged;
use Illuminate\Support\Facades\DB;

/**
 * Atblokavimas. Teikėjo profilis atkuriamas tik jei administratorius to nori ($restoreProfile):
 * profilis galėjo būti užblokuotas ir dėl kitos priežasties dar prieš paskyros blokavimą.
 * Atkuriamas profilis tampa active tik jei užpildyti privalomi vedlio žingsniai – kitaip pending
 * (ta pati taisyklė kaip ActivateCompletedProfile).
 *
 * Etapas 9c: atkūrus profilį teikėjas gauna ProviderStatusChanged (vėl aktyvus arba „užbaikite pildymą").
 * Pats blokavimas (BanUser) pranešimo nesiunčia: priežastis vidinė, o užblokuotas žmogus apie tai sužino
 * prisijungimo puslapyje.
 */
final class UnbanUser
{
    public function handle(User $user, bool $restoreProfile = true): void
    {
        $restored = DB::transaction(function () use ($user, $restoreProfile): ?ProviderProfile {
            $user->forceFill(['banned_at' => null, 'ban_reason' => null])->save();

            if (! $restoreProfile) {
                return null;
            }

            $profile = ProviderProfile::query()->where('user_id', $user->id)->first();

            if ($profile === null || $profile->status !== ProviderStatus::Suspended) {
                return null;
            }

            $profile->forceFill([
                'status' => $profile->hasRequiredSteps() ? ProviderStatus::Active : ProviderStatus::Pending,
            ])->save();

            return $profile;
        });

        // Anonimizuotam (soft deleted) vartotojui pranešti nėra kam – jo el. paštas jau pakeistas
        if ($restored !== null && ! $user->trashed()) {
            $user->notify(new ProviderStatusChanged($restored, $restored->status));
        }
    }
}
