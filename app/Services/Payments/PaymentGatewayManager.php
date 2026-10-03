<?php

namespace App\Services\Payments;

use App\Enums\PaymentGateway as GatewayType;
use App\Services\Payments\Paysera\PayseraGateway;
use Illuminate\Support\Manager;
use LogicException;
use RuntimeException;

/**
 * Mokėjimų tiekėjų „gamykla": pagal pavadinimą („paysera", „fake") sukuria tiekėjo objektą.
 *
 * Laravel Manager klasė – tas pats šablonas, kuriuo veikia Cache, Mail, Filesystem tvarkyklės (drivers):
 * metodas create{Vardas}Driver() kiekvienam tiekėjui, getDefaultDriver() – numatytasis iš config.
 * Nauji mokėjimai eina per numatytąjį tiekėją, o callback'as ir „Apmokėti dar kartą" – per tą, kurio
 * vardas įrašytas mokėjime (payments.gateway), net jei numatytasis vėliau pasikeistų.
 * https://laravel.com/docs/13.x/container
 */
class PaymentGatewayManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return (string) $this->config->get('payments.default');
    }

    public function gateway(?string $name = null): PaymentGateway
    {
        $gateway = $this->driver($name);

        if (! $gateway instanceof PaymentGateway) {
            throw new LogicException('Mokėjimų tiekėjas turi įgyvendinti PaymentGateway sąsają.');
        }

        return $gateway;
    }

    public function for(GatewayType $type): PaymentGateway
    {
        return $this->gateway($type->value);
    }

    /**
     * Ar šiam mokėjimų tiekėjui turim kodą (rankiniai ir seed'ų „manual" mokėjimai jo neturi).
     */
    public function supports(GatewayType $type): bool
    {
        return match ($type) {
            GatewayType::Paysera => true,
            GatewayType::Fake => ! app()->isProduction(),
            GatewayType::Stripe, GatewayType::Manual => false,
        };
    }

    protected function createPayseraDriver(): PaymentGateway
    {
        return $this->container->make(PayseraGateway::class);
    }

    protected function createFakeDriver(): PaymentGateway
    {
        // Saugiklis: netikras tiekėjas „apmoka" be pinigų, todėl produkcijoje jo būti negali
        if (app()->isProduction()) {
            throw new RuntimeException('Netikras mokėjimų tiekėjas (PAYMENT_GATEWAY=fake) produkcijoje uždraustas.');
        }

        return $this->container->make(FakeGateway::class);
    }
}
