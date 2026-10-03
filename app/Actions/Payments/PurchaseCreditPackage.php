<?php

namespace App\Actions\Payments;

use App\Models\CreditPackage;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Teikėjas perka kreditų paketą: sukuriamas laukiantis mokėjimas. Kreditai užskaitomi tik tada, kai mokėjimų
 * tiekėjas patvirtina apmokėjimą (ProcessPaymentResult → CompletePayment), o ne paspaudus „Pirkti".
 */
class PurchaseCreditPackage
{
    public function __construct(private readonly CreatePayment $createPayment) {}

    /**
     * @throws ValidationException kai paketas nebeparduodamas
     */
    public function handle(User $user, CreditPackage $package): Payment
    {
        if (! $package->is_active) {
            throw ValidationException::withMessages(['purchase' => __('billing.errors.package_inactive')]);
        }

        return $this->createPayment->handle($user, $package);
    }
}
