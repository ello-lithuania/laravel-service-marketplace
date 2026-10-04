<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Payments\CancelPayment;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Billing\PaymentResource;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Teikėjo mokėjimai: istorija, vieno mokėjimo puslapis (ir Paysera accepturl), „Apmokėti" ir atšaukimas.
 */
class PaymentController extends Controller
{
    /**
     * „Mokėjimai" – indeksas payments(user_id, created_at). Morph ryšys purchasable užkraunamas iš anksto (be N+1).
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        // Etapas 9b: refund – kreditinės sąskaitos nuoroda be N+1
        $payments = $user->payments()->with(['purchasable', 'refund'])->latest('id')->paginate(20);

        return Inertia::render('billing/Payments', [
            'payments' => PaymentResource::collection($payments),
        ]);
    }

    /**
     * Mokėjimo būsena. Čia grįžta pirkėjas iš Paysera (accepturl) – bet tai dar NE apmokėjimo įrodymas:
     * kol nepatvirtino tiekėjo serveris (callback'as), rodom „Laukiame patvirtinimo" ir puslapis pats atsinaujina.
     */
    public function show(Payment $payment): Response
    {
        Gate::authorize('view', $payment);

        $payment->load(['purchasable', 'refund']);

        return Inertia::render('billing/PaymentShow', [
            'payment' => PaymentResource::make($payment)->resolve(),
        ]);
    }

    /**
     * Laukiantį mokėjimą (pvz. pratęsimą iš priminimo laiško) apmokėti per jo tiekėją.
     */
    public function pay(Payment $payment, PaymentGatewayManager $gateways): HttpResponse
    {
        Gate::authorize('pay', $payment);

        return Inertia::location($gateways->for($payment->gateway)->startPayment($payment));
    }

    /**
     * Paysera cancelurl: pirkėjas paspaudė „Atšaukti". Pinigai nenuskaičiuoti, todėl tik pažymim mokėjimą.
     */
    public function cancel(Payment $payment, CancelPayment $cancel): RedirectResponse
    {
        Gate::authorize('cancel', $payment);

        $cancel->handle($payment);

        if ($payment->status === PaymentStatus::Cancelled) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('billing.flash.payment_cancelled')]);
        }

        return to_route('payments.show', $payment);
    }
}
