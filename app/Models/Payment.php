<?php

namespace App\Models;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Mokėjimas už kreditų paketą arba prenumeratą (docs/DB_SCHEMA.md → payments).
 */
#[Fillable([
    'gateway', 'gateway_reference', 'amount_cents', 'currency', 'status', 'paid_at',
    'invoice_number', 'meta',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, HasUuids;

    /**
     * HasUuids generuoja tik viešą užsakymo numerį (uuid); pirminis raktas lieka skaitinis id.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return [
            'gateway' => PaymentGateway::class,
            'status' => PaymentStatus::class,
            'amount_cents' => 'integer',
            'paid_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Kas apmokėta: CreditPackage arba SubscriptionPlan.
     *
     * @return MorphTo<Model, $this>
     */
    public function purchasable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphMany<CreditTransaction, $this> */
    public function creditTransactions(): MorphMany
    {
        return $this->morphMany(CreditTransaction::class, 'source');
    }
}
