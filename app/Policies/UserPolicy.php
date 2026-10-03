<?php

namespace App\Policies;

use App\Models\User;

/**
 * Vartotojų administravimas Filament panelėje (UserResource, Etapas 8).
 * $user – kas veikia (administratorius), $model – su kuo veikiama.
 *
 * Kūrimo ir redagavimo teisių sąmoningai nėra: vartotojai registruojasi patys, o jų duomenis keičia tik jie
 * (BDAR – duomenų tikslumas). Administratorius blokuoja, atblokuoja ir vykdo BDAR ištrynimo prašymus (anonymize).
 * https://laravel.com/docs/13.x/authorization#creating-policies
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    /**
     * Administratorių blokuoti negalima: taip neliktų kam atblokuoti (ir apsauga nuo klaidos savo paskyrai).
     */
    public function ban(User $user, User $model): bool
    {
        return $user->isAdmin()
            && ! $model->isAdmin()
            && ! $model->isBanned()
            && ! $model->trashed();
    }

    public function unban(User $user, User $model): bool
    {
        return $user->isAdmin() && $model->isBanned() && ! $model->trashed();
    }

    /**
     * BDAR ištrynimo prašymas, gautas ne per svetainę (pvz. el. paštu): administratorius anonimizuoja paskyrą.
     */
    public function anonymize(User $user, User $model): bool
    {
        return $user->isAdmin() && ! $model->isAdmin() && ! $model->trashed();
    }
}
