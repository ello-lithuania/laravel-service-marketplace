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
            'billing_details' => 'array',
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

    // --- Etapas 7: mokėjimai ir sąskaitos ------------------------------------------

    /**
     * Kurios prenumeratos laikotarpis apmokamas (pratęsimas). Pirmo plano pirkimo metu – null,
     * prenumerata priskiriama apmokėjus (docs/DB_SCHEMA.md → payments).
     *
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::Paid;
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::Pending;
    }

    public function hasInvoice(): bool
    {
        return $this->isPaid() && $this->invoice_number !== null;
    }

    /**
     * Ką žmogus perka – sąrašams, sąskaitai ir Paysera mokėjimo paskirčiai.
     * purchasable ryšys turi būti užkrautas iš anksto (preventLazyLoading).
     */
    public function description(): string
    {
        $purchasable = $this->purchasable;

        return match (true) {
            $purchasable instanceof CreditPackage => __('billing.purchasable.credit_package', ['name' => $purchasable->name]),
            $purchasable instanceof SubscriptionPlan => __('billing.purchasable.subscription_plan', [
                'name' => $purchasable->name,
                'period' => $purchasable->billing_period->label(),
            ]),
            default => __('billing.purchasable.unknown'),
        };
    }

    /**
     * Viešas URL parametras – uuid, o ne id: kitų mokėjimų numerių negalima atspėti.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
