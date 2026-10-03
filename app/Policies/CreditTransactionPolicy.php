<?php

namespace App\Policies;

use App\Models\CreditTransaction;
use App\Models\User;

/**
 * Kreditų ledger'is administratoriui – tik skaityti. Įrašai nekeičiami ir netrinami (docs/DB_SCHEMA.md 2.8):
 * klaida taisoma nauju koregavimo įrašu (AdjustCredits), todėl update/delete metodų nėra.
 */
class CreditTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, CreditTransaction $creditTransaction): bool
    {
        return $user->isAdmin();
    }

    /**
     * Rankinis koregavimas („Koreguoti kreditus" Filament'e).
     */
    public function adjust(User $user): bool
    {
        return $user->isAdmin();
    }
}
