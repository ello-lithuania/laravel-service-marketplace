<?php

namespace App\Services\Payments;

use App\Enums\SubscriptionStatus;
use Carbon\CarbonImmutable;

/**
 * Ką padarys mokėjimo grąžinimas (RefundCalculator rezultatas): kiek kreditų atimti ir kas nutiks prenumeratai.
 *
 * DTO (data transfer object) – nekeičiamas (readonly) duomenų „paketas" be logikos. Tą patį rezultatą naudoja ir
 * Filament patvirtinimo langas (peržiūra „bus atimta X kreditų"), ir RefundPayment (tikras vykdymas).
 */
final readonly class RefundCalculation
{
    public function __construct(
        /** Kiek kreditų šis mokėjimas suteikė ir reikėtų atimti. */
        public int $creditsToReverse,
        /** Kiek atimsim: ne daugiau nei dabartinis balansas (balansas niekada < 0). */
        public int $creditsReversed,
        /** Kiek atimti nepavyks – teikėjas juos jau išleido. */
        public int $creditsShortfall,
        /** Dabartinis teikėjo balansas (peržiūrai). */
        public int $balance,
        /** Nauja prenumeratos būsena; null – mokėjimas ne už prenumeratą. */
        public ?SubscriptionStatus $subscriptionStatus = null,
        public ?CarbonImmutable $subscriptionEndsAt = null,
        public ?CarbonImmutable $subscriptionCreditsGrantedUntil = null,
    ) {}

    public function affectsSubscription(): bool
    {
        return $this->subscriptionStatus !== null;
    }

    /**
     * Prenumerata baigiasi iškart (o ne sutrumpinama iki būsimos datos).
     */
    public function endsSubscription(): bool
    {
        return $this->subscriptionStatus === SubscriptionStatus::Expired;
    }
}
