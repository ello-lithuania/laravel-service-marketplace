<?php

namespace App\Models;

use App\Enums\CreditTransactionType;
use Database\Factories\CreditTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Kreditų „didžiosios knygos" (ledger) įrašas (docs/DB_SCHEMA.md 2.8).
 * Įrašai nekeičiami: klaida taisoma nauja priešinga eilute, todėl updated_at nėra.
 */
#[Fillable(['amount', 'balance_after', 'type', 'description'])]
class CreditTransaction extends Model
{
    /** @use HasFactory<CreditTransactionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance_after' => 'integer',
            'type' => CreditTransactionType::class,
        ];
    }

    /** @return BelongsTo<ProviderProfile, $this> */
    public function providerProfile(): BelongsTo
    {
        return $this->belongsTo(ProviderProfile::class);
    }

    /**
     * Kas sukėlė operaciją: Payment, Offer arba Subscription (polimorfinis ryšys).
     *
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
