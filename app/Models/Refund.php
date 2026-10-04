<?php

namespace App\Models;

use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mokėjimo grąžinimas ir jo kreditinė sąskaita faktūra (Etapas 9, docs/DB_SCHEMA.md → refunds).
 *
 * Kaip ir sąskaita faktūra, kreditinė sąskaita po išrašymo nekeičiama: rekvizitai (billing_details) – kopija,
 * padaryta grąžinimo metu. Kuria tik RefundPayment.
 */
#[Fillable(['amount_cents', 'reason', 'credits_reversed', 'credits_shortfall', 'credit_note_number', 'billing_details'])]
class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'credits_reversed' => 'integer',
            'credits_shortfall' => 'integer',
            'billing_details' => 'array',
        ];
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Administratorius, kuris grąžino. withTrashed – istorijoje matyti ir vėliau „ištrintas" administratorius.
     *
     * @return BelongsTo<User, $this>
     */
    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by_id')->withTrashed();
    }
}
