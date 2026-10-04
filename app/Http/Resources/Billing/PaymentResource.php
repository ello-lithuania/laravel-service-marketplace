<?php

namespace App\Http\Resources\Billing;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mokėjimas teikėjui (sąrašas ir mokėjimo puslapis). Tiekėjo atsakymo (meta) ir rekvizitų nesiunčiam.
 * purchasable ryšys turi būti užkrautas (description()).
 *
 * @property Payment $resource
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payment = $this->resource;
        $user = $request->user();

        return [
            'uuid' => $payment->uuid,
            'description' => $payment->description(),
            'amount_cents' => $payment->amount_cents,
            'currency' => $payment->currency,
            'status' => ['value' => $payment->status->value, 'label' => $payment->status->label()],
            'gateway' => ['value' => $payment->gateway->value, 'label' => $payment->gateway->label()],
            'is_renewal' => $payment->subscription_id !== null && $payment->isPending(),
            'invoice_number' => $payment->invoice_number,
            'created_at' => $payment->created_at?->toIso8601String(),
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'can' => [
                'pay' => $user?->can('pay', $payment) ?? false,
                'download_invoice' => $user?->can('downloadInvoice', $payment) ?? false,
                // Etapas 9b: tik grąžintiems tikrinam Policy (kitiems – be papildomos užklausos)
                'download_credit_note' => $payment->isRefunded() && ($user?->can('downloadCreditNote', $payment) ?? false),
            ],
            // --- Etapas 9b --- tik kai controller'is užkrovė ryšį (preventLazyLoading)
            'refund' => $this->refundData($payment),
        ];
    }

    /**
     * Grąžinimo duomenys teikėjui. Kas grąžino (administratorius) – nerodom.
     *
     * @return array{credit_note_number: string, refunded_at: string|null, reason: string, credits_reversed: int, credits_shortfall: int}|null
     */
    private function refundData(Payment $payment): ?array
    {
        $refund = $payment->relationLoaded('refund') ? $payment->refund : null;

        if ($refund === null) {
            return null;
        }

        return [
            'credit_note_number' => $refund->credit_note_number,
            'refunded_at' => $refund->created_at?->toIso8601String(),
            'reason' => $refund->reason,
            'credits_reversed' => $refund->credits_reversed,
            'credits_shortfall' => $refund->credits_shortfall,
        ];
    }
}
