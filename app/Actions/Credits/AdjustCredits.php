<?php

namespace App\Actions\Credits;

use App\Enums\CreditTransactionType;
use App\Exceptions\InsufficientCreditsException;
use App\Models\CreditTransaction;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Credits\CreditLedger;
use InvalidArgumentException;

/**
 * Administratoriaus kreditų koregavimas (Filament → „Koreguoti kreditus"): + kompensacija, − klaidos taisymas.
 *
 * Kaip ir visi kreditų pokyčiai – per CreditLedger: nauja nekeičiama ledger eilutė (type admin_adjustment),
 * balansas po jos niekada nebūna neigiamas. Šaltinis (source) – administratorius, todėl istorijoje matyti, kas koregavo.
 */
class AdjustCredits
{
    public function __construct(private readonly CreditLedger $ledger) {}

    /**
     * @throws InsufficientCreditsException kai atėmus balansas taptų neigiamas
     */
    public function handle(ProviderProfile $provider, int $amount, string $reason, User $admin): CreditTransaction
    {
        if ($amount === 0) {
            throw new InvalidArgumentException('Koregavimo kiekis negali būti 0.');
        }

        $description = __('billing.ledger.admin_adjustment', ['reason' => trim($reason), 'admin' => $admin->name]);

        return $amount > 0
            ? $this->ledger->credit($provider, $amount, CreditTransactionType::AdminAdjustment, $admin, $description)
            : $this->ledger->debit($provider, -$amount, CreditTransactionType::AdminAdjustment, $admin, $description);
    }
}
