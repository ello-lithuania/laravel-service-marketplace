<?php

namespace App\Actions\ProviderProfile;

use App\Enums\ProviderStatus;
use App\Models\ProviderProfile;

/**
 * Taisyklė pending → active (docs/DB_SCHEMA.md → provider_profiles, „Būsenos"):
 * kai užpildyti visi privalomi vedlio žingsniai, profilis tampa aktyvus automatiškai,
 * be administratoriaus patvirtinimo. Netinkamus profilius administratorius vėliau gali
 * paslėpti (hidden) arba užblokuoti (suspended) – tokių būsenų vedlys nekeičia.
 */
class ActivateCompletedProfile
{
    public function handle(ProviderProfile $profile): void
    {
        if ($profile->status !== ProviderStatus::Pending || ! $profile->hasRequiredSteps()) {
            return;
        }

        // status nėra Fillable (jį keičia tik sistema), todėl forceFill
        $profile->forceFill(['status' => ProviderStatus::Active])->save();
    }
}
