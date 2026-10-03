<?php

namespace Tests\Support;

use App\Actions\Payments\ProcessPaymentResult;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Payments\FakeGateway;
use Illuminate\Http\Request;

/**
 * Etapo 7 testų pagalbininkai: teikėjas su profiliu, testinio tiekėjo callback'as.
 */
final class Billing
{
    /**
     * Aktyvus teikėjas (vartotojas + profilis) su nurodytu kreditų balansu.
     */
    public static function provider(int $credits = 0): User
    {
        return ProviderProfile::factory()->withCredits($credits)->create()->user;
    }

    /**
     * Pasirašyti testinio tiekėjo callback'o duomenys – tokie, kokius siųstų tiekėjo serveris.
     *
     * @return array{payment: string, status: string, reference: string, amount: int, signature: string}
     */
    public static function callbackPayload(Payment $payment, PaymentStatus $status = PaymentStatus::Paid, ?string $reference = null): array
    {
        return app(FakeGateway::class)->callbackPayload($payment, $status, $reference);
    }

    /**
     * Apmoka (arba atmeta) mokėjimą tuo pačiu keliu kaip callback'as, tik be HTTP.
     */
    public static function complete(Payment $payment, PaymentStatus $status = PaymentStatus::Paid): Payment
    {
        $request = Request::create('/', 'POST', self::callbackPayload($payment, $status));
        $result = app(FakeGateway::class)->handleCallback($request);

        return app(ProcessPaymentResult::class)->handle(PaymentGateway::Fake, $result)->refresh();
    }
}
