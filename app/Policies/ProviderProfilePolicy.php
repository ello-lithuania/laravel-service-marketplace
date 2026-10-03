<?php

namespace App\Policies;

use App\Enums\ProviderStatus;
use App\Models\ProviderProfile;
use App\Models\User;

/**
 * Kas ką gali daryti su teikėjo profiliu. Laravel šią klasę randa pats pagal pavadinimą
 * (ProviderProfile → ProviderProfilePolicy), registruoti nereikia.
 * https://laravel.com/docs/13.x/authorization#creating-policies
 *
 * Maršrutų middleware role:provider – grubus filtras („ar tu teikėjas?"),
 * o Policy atsako į tikslesnį klausimą „ar TAI tavo profilis?".
 */
class ProviderProfilePolicy
{
    /**
     * Aktyvų profilį mato visi, net neprisijungę (todėl ?User). Nebaigtą, paslėptą ar užblokuotą –
     * tik savininkas ir administratorius. Naudos viešas profilio puslapis (Etapas 4).
     */
    public function view(?User $user, ProviderProfile $profile): bool
    {
        if ($profile->status === ProviderStatus::Active) {
            return true;
        }

        return $user !== null && ($user->isAdmin() || $profile->user_id === $user->id);
    }

    /**
     * Profilį gali susikurti tik teikėjas ir tik vieną (ryšys 1:1).
     */
    public function create(User $user): bool
    {
        return $user->isProvider() && $user->providerProfile === null;
    }

    public function update(User $user, ProviderProfile $profile): bool
    {
        return $user->isProvider() && $profile->user_id === $user->id;
    }

    // --- Etapas 8: moderavimas Filament panelėje (ProviderProfileResource) --------

    /**
     * Teikėjų sąrašas admin panelėje.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Būsenos keitimas (paslėpti, užblokuoti, aktyvuoti) ir ženklelis „Patikrintas".
     */
    public function moderate(User $user, ProviderProfile $profile): bool
    {
        return $user->isAdmin();
    }
}
