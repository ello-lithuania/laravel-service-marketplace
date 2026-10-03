<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;

/**
 * Patikrinto callback'o rezultatas tiekėjui nepriklausomu formatu (DTO – „duomenų dėžutė" be logikos).
 * Paysera atsiunčia „status=1", Stripe – „payment_intent.succeeded", o mūsų kodas mato tik PaymentStatus::Paid.
 */
final readonly class PaymentResult
{
    /**
     * @param  string  $paymentUuid  mūsų užsakymo numeris (payments.uuid)
     * @param  PaymentStatus  $status  Paid, Failed, Cancelled arba Pending (dar nebaigta – nieko nekeičiam)
     * @param  string|null  $gatewayReference  tiekėjo transakcijos ID (payments.gateway_reference)
     * @param  int|null  $amountCents  tiekėjo patvirtinta suma – turi sutapti su mūsų
     * @param  array<string, scalar|null>  $meta  saugoma payments.meta (be asmens duomenų)
     */
    public function __construct(
        public string $paymentUuid,
        public PaymentStatus $status,
        public ?string $gatewayReference = null,
        public ?int $amountCents = null,
        public ?string $currency = null,
        public array $meta = [],
    ) {}
}
