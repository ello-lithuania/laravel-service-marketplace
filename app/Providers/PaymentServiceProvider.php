<?php

namespace App\Providers;

use App\Services\Payments\FakeGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\Paysera\PayseraGateway;
use App\Services\Payments\Paysera\PayseraPublicKey;
use App\Services\Payments\Paysera\PayseraSigner;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

/**
 * Mokėjimų tiekėjai service container'yje (Etapas 7).
 *
 * Service container – Laravel „objektų dėžė": paprašius klasės ar sąsajos (konstruktoriaus parametru arba
 * app(...)), jis pats sukuria objektą su visomis priklausomybėmis. Čia nurodom, KAIP sukurti tai, ko jis
 * pats neatspėtų: sąsajai PaymentGateway – numatytąjį tiekėją iš config, Paysera klasei – nustatymus iš config.
 * https://laravel.com/docs/13.x/container · https://laravel.com/docs/13.x/providers
 *
 * Atskiras provider'is (ne AppServiceProvider), kad mokėjimų kodas būtų vienoje vietoje;
 * užregistruotas bootstrap/providers.php.
 */
class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // singleton – vienas manager'is visai užklausai; jis atsimena jau sukurtus tiekėjus
        $this->app->singleton(PaymentGatewayManager::class);

        // „Duok man PaymentGateway" → numatytasis tiekėjas (config('payments.default'))
        $this->app->bind(PaymentGateway::class, fn (Application $app): PaymentGateway => $app->make(PaymentGatewayManager::class)->gateway());

        $this->app->bind(PayseraGateway::class, function (Application $app): PayseraGateway {
            $config = $app->make('config');

            return new PayseraGateway(
                signer: new PayseraSigner((string) $config->get('payments.paysera.sign_password')),
                publicKey: $app->make(PayseraPublicKey::class),
                projectId: $config->get('payments.paysera.project_id') === null ? null : (string) $config->get('payments.paysera.project_id'),
                test: (bool) $config->get('payments.paysera.test'),
                payUrl: (string) $config->get('payments.paysera.pay_url'),
                callbackUrl: $config->get('payments.paysera.callback_url') === null ? null : (string) $config->get('payments.paysera.callback_url'),
                signature: (string) $config->get('payments.paysera.signature', 'ss2'),
            );
        });

        $this->app->bind(PayseraPublicKey::class, function (Application $app): PayseraPublicKey {
            $config = $app->make('config');
            $path = $config->get('payments.paysera.public_key_path');

            return new PayseraPublicKey(
                cache: $app->make('cache.store'),
                http: $app->make(HttpFactory::class),
                url: (string) $config->get('payments.paysera.public_key_url'),
                path: is_string($path) && $path !== '' ? $path : null,
            );
        });

        $this->app->bind(FakeGateway::class, fn (Application $app): FakeGateway => new FakeGateway((string) $app->make('config')->get('app.key')));
    }
}
