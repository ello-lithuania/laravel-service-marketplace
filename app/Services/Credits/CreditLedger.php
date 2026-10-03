<?php

namespace App\Services\Credits;

use App\Enums\CreditTransactionType;
use App\Exceptions\InsufficientCreditsException;
use App\Models\CreditTransaction;
use App\Models\ProviderProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Kreditų „didžioji knyga" (ledger, docs/DB_SCHEMA.md 2.8) – VIENINTELĖ vieta, kuri keičia kreditų balansą.
 *
 * Taisyklės, kurias ši klasė užtikrina:
 * - kiekvienas pokytis = nauja nekeičiama credit_transactions eilutė su balance_after;
 * - provider_profiles.credits_balance (cache) keičiamas toje pačioje DB transakcijoje;
 * - teikėjo eilutė užrakinama (lockForUpdate), todėl lygiagrečios operacijos vyksta po vieną
 *   ir balansas niekada netampa neigiamas.
 *
 * Naudoja: pasiūlymai (Etapas 5), vėliau – pirkimai, prenumeratos, admin koregavimai (Etapas 7):
 *   $ledger->credit($provider, 30, CreditTransactionType::Purchase, $payment, 'Kreditų paketas „30"');
 *
 * Klasė be būsenos, todėl Laravel service container ją sukuria automatiškai (constructor injection).
 * https://laravel.com/docs/13.x/container#zero-configuration-resolution
 */
class CreditLedger
{
    /**
     * Nurašo kreditus (amount įrašomas su minusu).
     *
     * @throws InsufficientCreditsException kai balanso neužtenka – tada niekas neįrašoma
     */
    public function debit(
        ProviderProfile $provider,
        int $amount,
        CreditTransactionType $type,
        ?Model $source = null,
        ?string $description = null,
    ): CreditTransaction {
        $this->ensurePositive($amount);

        return DB::transaction(fn () => $this->write($provider, -$amount, $type, $source, $description));
    }

    /**
     * Prideda kreditų (pirkimas, prenumerata, dovana, grąžinimas).
     */
    public function credit(
        ProviderProfile $provider,
        int $amount,
        CreditTransactionType $type,
        ?Model $source = null,
        ?string $description = null,
    ): CreditTransaction {
        $this->ensurePositive($amount);

        return DB::transaction(fn () => $this->write($provider, $amount, $type, $source, $description));
    }

    /**
     * Grąžina tai, kas grynai nurašyta už šaltinį (pvz. pasiūlymą), įrašu type = refund.
     *
     * Idempotentiška: suma skaičiuojama iš ledger'io (nurašymas −2 + grąžinimas +2 = 0), todėl
     * antras kvietimas nieko nebedaro. Taip apsaugom nuo dvigubo grąžinimo, net jei kodas būtų iškviestas du kartus.
     *
     * @return CreditTransaction|null null – nėra ką grąžinti
     */
    public function refund(Model $source, ?string $description = null): ?CreditTransaction
    {
        return DB::transaction(function () use ($source, $description): ?CreditTransaction {
            $providerId = CreditTransaction::query()->whereMorphedTo('source', $source)->value('provider_profile_id');

            if ($providerId === null) {
                return null;
            }

            // Pirma užrakinam teikėją, tik tada skaičiuojam sumą – kitaip du lygiagretūs grąžinimai
            // abu pamatytų „dar negrąžinta" ir grąžintų dvigubai
            $provider = $this->lock((int) $providerId);

            $net = (int) CreditTransaction::query()
                ->whereMorphedTo('source', $source)
                ->where('provider_profile_id', $provider->id)
                ->sum('amount');

            if ($net >= 0) {
                return null;
            }

            return $this->write($provider, -$net, CreditTransactionType::Refund, $source, $description);
        });
    }

    /**
     * Įrašo pokytį. Kviečiama tik DB transakcijos viduje.
     */
    private function write(
        ProviderProfile $provider,
        int $amount,
        CreditTransactionType $type,
        ?Model $source,
        ?string $description,
    ): CreditTransaction {
        // Šviežias balansas iš užrakintos eilutės, o ne iš $provider (jis galėjo būti užkrautas seniai)
        $locked = $this->lock($provider->id);
        $balance = $locked->credits_balance + $amount;

        if ($balance < 0) {
            throw new InsufficientCreditsException($locked->credits_balance, -$amount);
        }

        $locked->forceFill(['credits_balance' => $balance])->save();

        $transaction = new CreditTransaction([
            'amount' => $amount,
            'balance_after' => $balance,
            'type' => $type,
            'description' => $description === null ? null : mb_substr($description, 0, 255),
        ]);
        $transaction->providerProfile()->associate($locked);

        if ($source !== null) {
            $transaction->source()->associate($source);
        }

        $transaction->save();

        // Kviečiančiojo modelis irgi mato naują balansą (be papildomos užklausos)
        $provider->forceFill(['credits_balance' => $balance])->syncOriginalAttribute('credits_balance');

        return $transaction;
    }

    /**
     * SELECT … FOR UPDATE: kitos transakcijos, norinčios keisti šio teikėjo kreditus, palauks, kol ši baigsis.
     * https://laravel.com/docs/13.x/queries#pessimistic-locking
     */
    private function lock(int $providerId): ProviderProfile
    {
        // withTrashed – grąžinimas turi pavykti ir „ištrintam" (soft delete) teikėjui
        return ProviderProfile::withTrashed()->whereKey($providerId)->lockForUpdate()->firstOrFail();
    }

    private function ensurePositive(int $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Kreditų kiekis turi būti teigiamas, kryptį nurodo metodas (debit/credit).');
        }
    }
}
