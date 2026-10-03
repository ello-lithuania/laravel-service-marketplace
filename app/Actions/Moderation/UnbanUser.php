<?php

namespace App\Actions\Moderation;

use App\Enums\ProviderStatus;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Atblokavimas. Teikėjo profilis atkuriamas tik jei administratorius to nori ($restoreProfile):
 * profilis galėjo būti užblokuotas ir dėl kitos priežasties dar prieš paskyros blokavimą.
 * Atkuriamas profilis tampa active tik jei užpildyti privalomi vedlio žingsniai – kitaip pending
 * (ta pati taisyklė kaip ActivateCompletedProfile).
 */
final class UnbanUser
{
    public function handle(User $user, bool $restoreProfile = true): void
    {
        DB::transaction(function () use ($user, $restoreProfile): void {
            $user->forceFill(['banned_at' => null, 'ban_reason' => null])->save();

            if (! $restoreProfile) {
                return;
            }

            $profile = ProviderProfile::query()->where('user_id', $user->id)->first();

            if ($profile !== null && $profile->status === ProviderStatus::Suspended) {
                $profile->forceFill([
                    'status' => $profile->hasRequiredSteps() ? ProviderStatus::Active : ProviderStatus::Pending,
                ])->save();
            }
        });
    }
}
