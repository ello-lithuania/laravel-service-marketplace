<?php

namespace App\Policies;

use App\Enums\PaymentGateway;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PaymentGatewayManager;

/**
 * Kas gali matyti mokėjimus, juos apmokėti ir atsisiųsti sąskaitas (Etapas 7).
 * Teikėjas – tik savo mokėjimus; administratorius – visus (Filament). Kurti ir redaguoti mokėjimų rankomis
 * negalima niekam – juos kuria tik Actions (create/update/delete metodų nėra, todėl Filament tų mygtukų nerodo).
 */
class PaymentPolicy
{
    public function __construct(private readonly PaymentGatewayManager $gateways) {}

    /**
     * Mokėjimų sąrašas Filament panelėje.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->isAdmin() || $this->owns($user, $payment);
    }

    /**
     * „Apmokėti" – savo laukiantį mokėjimą, jei jo tiekėjas dar palaikomas.
     */
    public function pay(User $user, Payment $payment): bool
    {
        return $this->owns($user, $payment) && $payment->isPending() && $this->gateways->supports($payment->gateway);
    }

    /**
     * Pirkėjas atšaukė mokėjimą tiekėjo puslapyje (cancelurl).
     */
    public function cancel(User $user, Payment $payment): bool
    {
        return $this->owns($user, $payment);
    }

    /**
     * Testinio tiekėjo puslapis („Apmokėti" be pinigų) – tik savo testiniam mokėjimui.
     */
    public function simulate(User $user, Payment $payment): bool
    {
        return $this->owns($user, $payment) && $payment->gateway === PaymentGateway::Fake;
    }

    public function downloadInvoice(User $user, Payment $payment): bool
    {
        return ($user->isAdmin() || $this->owns($user, $payment)) && $payment->hasInvoice();
    }

    // --- Etapas 9b: grąžinimai ir kreditinės sąskaitos ---

    /**
     * „Grąžinti pinigus" (Filament) – tik administratorius ir tik apmokėtą mokėjimą. Būseną dar kartą,
     * užrakinusi eilutę, patikrina RefundPayment (lygiagretus antras paspaudimas negrąžins dukart).
     */
    public function refund(User $user, Payment $payment): bool
    {
        return $user->isAdmin() && $payment->isPaid();
    }

    /**
     * Kreditinė sąskaita PDF: savininkui ir administratoriui, jei grąžinimas yra. loadMissing – sąrašuose
     * ryšys užkraunamas iš anksto (be N+1), o vienam mokėjimui – viena užklausa.
     */
    public function downloadCreditNote(User $user, Payment $payment): bool
    {
        return ($user->isAdmin() || $this->owns($user, $payment))
            && $payment->loadMissing('refund')->refund !== null;
    }

    private function owns(User $user, Payment $payment): bool
    {
        return $payment->user_id === $user->id;
    }
}
