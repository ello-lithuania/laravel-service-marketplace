<?php

namespace App\Observers;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\ProviderProfile;
use App\Notifications\LowCredits;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Stebi naujas ledger eilutes ir praneša teikėjui, kai kreditų lieka mažai (Etapas 7).
 *
 * Observer – klasė, reaguojanti į modelio įvykius (created, updated…), kaip WordPress „save_post" hook'as.
 * Taip LowCredits atsiranda nekeičiant pasiūlymų kodo (SendOffer) ar ledger'io: bet koks nurašymas, kuris
 * nuleidžia balansą žemiau ribos, sukelia pranešimą.
 * ShouldHandleEventsAfterCommit – vykdoma tik transakcijai pavykus: jei pasiūlymo siuntimas būtų atšauktas,
 * pranešimo apie „nurašytus" kreditus nebūtų. https://laravel.com/docs/13.x/eloquent#observers-and-database-transactions
 */
class CreditTransactionObserver implements ShouldHandleEventsAfterCommit
{
    public function created(CreditTransaction $transaction): void
    {
        $threshold = (int) config('payments.low_credits_threshold');
        $balanceBefore = $transaction->balance_after - $transaction->amount;

        // Tik kai balansas PEREINA ribą (buvo ≥ ribos, tapo < ribos) – kitaip kiekvienas pasiūlymas siųstų laišką
        if ($transaction->amount >= 0 || $transaction->balance_after >= $threshold || $balanceBefore < $threshold) {
            return;
        }

        // --- Etapas 9b --- Grąžinus mokėjimą teikėjas jau gauna PaymentRefunded su atimtais kreditais –
        // antras laiškas „baigiasi kreditai" tą pačią minutę būtų triukšmas
        if ($transaction->type === CreditTransactionType::PaymentRefund) {
            return;
        }

        $provider = ProviderProfile::query()->with('user')->find($transaction->provider_profile_id);

        $provider?->user->notify(new LowCredits($transaction->balance_after));
    }
}
