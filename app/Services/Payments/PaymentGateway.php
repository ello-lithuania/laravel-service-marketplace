<?php

namespace App\Services\Payments;

use App\Enums\PaymentGateway as GatewayType;
use App\Exceptions\InvalidPaymentCallbackException;
use App\Models\Payment;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mokėjimų tiekėjo sąsaja (interface): ką turi mokėti kiekvienas tiekėjas – Paysera, netikras (fake), vėliau Stripe.
 *
 * Likęs kodas (controller'iai, Actions) žino tik šią sąsają, o ne konkretų tiekėją. Todėl pridėti Stripe = parašyti
 * naują klasę ir užregistruoti ją PaymentGatewayManager'yje; kreditų, prenumeratų ir sąskaitų kodas nesikeičia.
 * WordPress analogas – WooCommerce WC_Payment_Gateway klasė, kurią paveldi kiekvienas mokėjimo įskiepis.
 * https://laravel.com/docs/13.x/container#binding-interfaces-to-implementations
 */
interface PaymentGateway
{
    /**
     * Kuri reikšmė įrašoma į payments.gateway.
     */
    public function type(): GatewayType;

    /**
     * Adresas, į kurį nukreipiam pirkėją apmokėti (Paysera puslapis arba vietinis testinis puslapis).
     */
    public function startPayment(Payment $payment): string;

    /**
     * Patikrina tiekėjo callback'ą (parašą!) ir paverčia jį mūsų PaymentResult.
     *
     * @throws InvalidPaymentCallbackException kai parašas ar duomenys netinkami – tada niekas nekeičiama
     */
    public function handleCallback(Request $request): PaymentResult;

    /**
     * Atsakymas, kurio tiekėjas laukia (Paysera – tekstas „OK", kitaip callback'ą kartos).
     */
    public function acknowledge(PaymentResult $result): Response;
}
