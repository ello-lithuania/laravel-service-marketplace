<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Payments\PurchaseCreditPackage;
use App\Actions\Payments\PurchaseSubscriptionPlan;
use App\Http\Controllers\Controller;
use App\Models\CreditPackage;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * „Pirkti": sukuriamas laukiantis mokėjimas ir pirkėjas nukreipiamas į mokėjimų tiekėją.
 *
 * Inertia::location – „išorinis" nukreipimas: Inertia užklausai grąžinamas 409 su X-Inertia-Location antrašte,
 * ir naršyklė atidaro Paysera puslapį visu langu (paprastas redirect'as būtų bandomas atidaryti kaip Inertia puslapis).
 * https://inertiajs.com/redirects#external-redirects
 */
class PurchaseController extends Controller
{
    public function __construct(private readonly PaymentGatewayManager $gateways) {}

    public function package(Request $request, CreditPackage $creditPackage, PurchaseCreditPackage $purchase): Response
    {
        /** @var User $user */
        $user = $request->user();

        return $this->redirectToGateway($purchase->handle($user, $creditPackage));
    }

    public function plan(Request $request, SubscriptionPlan $subscriptionPlan, PurchaseSubscriptionPlan $purchase): Response
    {
        /** @var User $user */
        $user = $request->user();

        return $this->redirectToGateway($purchase->handle($user, $subscriptionPlan));
    }

    private function redirectToGateway(Payment $payment): Response
    {
        return Inertia::location($this->gateways->for($payment->gateway)->startPayment($payment));
    }
}
