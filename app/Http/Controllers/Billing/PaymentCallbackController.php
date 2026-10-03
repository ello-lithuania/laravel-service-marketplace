<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Payments\ProcessPaymentResult;
use App\Enums\PaymentGateway as GatewayType;
use App\Exceptions\InvalidPaymentCallbackException;
use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mokėjimų tiekėjo callback'as (webhook'as): Paysera serveris praneša apie mokėjimo rezultatą.
 *
 * Be prisijungimo ir be CSRF (routes/billing.php, bootstrap/app.php) – todėl VIENINTELĖ apsauga yra parašo
 * patikra (PaymentGateway::handleCallback). Klaidos atveju atsakom 400 – Paysera callback'ą pakartos vėliau,
 * o mes klaidą matysim log'e. Sėkmės atveju – „OK", net jei callback'as pakartotinis (idempotencija).
 */
class PaymentCallbackController extends Controller
{
    public function __invoke(Request $request, string $gateway, PaymentGatewayManager $gateways, ProcessPaymentResult $process): Response
    {
        abort_unless($gateways->supports(GatewayType::from($gateway)), 404);

        $driver = $gateways->gateway($gateway);

        try {
            $result = $driver->handleCallback($request);
            $process->handle($driver->type(), $result);
        } catch (InvalidPaymentCallbackException $e) {
            Log::warning('Atmestas mokėjimo callback\'as.', ['gateway' => $gateway, 'reason' => $e->getMessage(), 'ip' => $request->ip()]);

            return response('Error: '.$e->getMessage(), 400, ['Content-Type' => 'text/plain']);
        }

        return $driver->acknowledge($result);
    }
}
