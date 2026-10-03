<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Payments\ProcessPaymentResult;
use App\Enums\PaymentGateway as GatewayType;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\FakeCheckoutRequest;
use App\Http\Resources\Billing\PaymentResource;
use App\Models\Payment;
use App\Services\Payments\FakeGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Netikras mokėjimų tiekėjas: „Paysera" puslapio imitacija dev'ui. Paspaudus mygtuką sukuriamas toks pat
 * pasirašytas callback'as, kokį atsiųstų tiekėjas, ir apdorojamas tuo pačiu ProcessPaymentResult kodu.
 * Produkcijoje – 404 (ir PaymentGatewayManager „fake" tiekėjo ten nesukurs).
 */
class FakeCheckoutController extends Controller
{
    public function show(Payment $payment): Response|RedirectResponse
    {
        $this->ensureFake($payment);

        if (! $payment->isPending()) {
            return to_route('payments.show', $payment);
        }

        $payment->load('purchasable');

        return Inertia::render('billing/FakeCheckout', [
            'payment' => PaymentResource::make($payment)->resolve(),
        ]);
    }

    public function complete(FakeCheckoutRequest $request, Payment $payment, FakeGateway $gateway, ProcessPaymentResult $process): RedirectResponse
    {
        $this->ensureFake($payment);

        // Tas pats kelias kaip tikro callback'o: pasirašyti duomenys → handleCallback (parašo patikra) → apdorojimas
        $callback = Request::create(route('payments.callback', ['gateway' => 'fake']), 'POST', $gateway->callbackPayload($payment, $request->result()));
        $process->handle(GatewayType::Fake, $gateway->handleCallback($callback));

        $payment->refresh();

        Inertia::flash('toast', match ($payment->status) {
            PaymentStatus::Paid => ['type' => 'success', 'message' => __('billing.flash.payment_paid')],
            PaymentStatus::Cancelled => ['type' => 'info', 'message' => __('billing.flash.payment_cancelled')],
            default => ['type' => 'error', 'message' => __('billing.flash.payment_failed')],
        });

        return to_route('payments.show', $payment);
    }

    private function ensureFake(Payment $payment): void
    {
        abort_if(app()->isProduction() || $payment->gateway !== GatewayType::Fake, 404);

        Gate::authorize('simulate', $payment);
    }
}
