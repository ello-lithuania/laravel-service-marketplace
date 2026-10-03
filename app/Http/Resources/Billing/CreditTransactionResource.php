<?php

namespace App\Http\Resources\Billing;

use App\Models\CreditTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Kreditų istorijos eilutė teikėjui. Šaltinio (source) nesiunčiam – užtenka aprašymo ir tipo.
 *
 * @property CreditTransaction $resource
 */
class CreditTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $transaction = $this->resource;

        return [
            'id' => $transaction->id,
            'amount' => $transaction->amount,
            'balance_after' => $transaction->balance_after,
            'type' => ['value' => $transaction->type->value, 'label' => $transaction->type->label()],
            'description' => $transaction->description,
            'created_at' => $transaction->created_at->toIso8601String(),
        ];
    }
}
