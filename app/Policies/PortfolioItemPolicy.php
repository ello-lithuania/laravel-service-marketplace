<?php

namespace App\Policies;

use App\Models\PortfolioItem;
use App\Models\User;

/**
 * Atliktų darbų (portfolio) teisės: tvarko tik teikėjas, ir tik savo darbus.
 * Naudojama maršrutuose: ->can('update', 'portfolioItem') (routes/account.php).
 */
class PortfolioItemPolicy
{
    /**
     * Darbų sąrašas ir naujo darbo kūrimas – tik teikėjui, kuris jau turi profilį
     * (darbas priklauso profiliui, ne vartotojui).
     */
    public function viewAny(User $user): bool
    {
        return $user->isProvider() && $user->providerProfile !== null;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, PortfolioItem $portfolioItem): bool
    {
        return $user->isProvider()
            && $user->providerProfile !== null
            && $portfolioItem->provider_profile_id === $user->providerProfile->id;
    }

    public function delete(User $user, PortfolioItem $portfolioItem): bool
    {
        return $this->update($user, $portfolioItem);
    }
}
