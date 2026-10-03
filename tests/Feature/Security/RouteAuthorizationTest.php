<?php

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Autorizacijos auditas (Etapas 8): kiekvienas maršrutas turi būti arba sąmoningai viešas (sąrašas žemiau),
 * arba saugomas autentifikacijos middleware. Pridėjus naują maršrutą be „auth" ir nepapildžius sąrašo,
 * šis testas nepraeis – taip „pamirštas" middleware neišvažiuos į produkciją.
 *
 * Kas ką gali daryti su KONKREČIU įrašu (savo užklausa, savo pasiūlymas), tikrina Policies – jų testai
 * tests/Feature/PoliciesTest.php, tests/Feature/Policies ir srautų testuose.
 */

/**
 * Sąmoningai vieši maršrutai (vardai arba URI šablonai) ir kodėl.
 *
 * @return array<string, string>
 */
function publicRoutes(): array
{
    return [
        // Katalogas ir SEO (Etapai 4, 8)
        'home' => 'pradžios puslapis',
        'categories.*' => 'kategorijų katalogas',
        'providers.*' => 'teikėjų sąrašas ir vieši profiliai (neaktyvūs – 404 controller\'yje)',
        'search' => 'paieška',
        'seo.*' => 'sitemap.xml ir robots.txt',
        // Fortify svečių puslapiai (middleware guest – prisijungęs nukreipiamas)
        'login' => 'prisijungimas', 'login.store' => 'prisijungimas',
        'register' => 'registracija', 'register.store' => 'registracija',
        'password.request' => 'slaptažodžio atkūrimas', 'password.email' => 'slaptažodžio atkūrimas',
        'password.reset' => 'slaptažodžio atkūrimas', 'password.update' => 'slaptažodžio atkūrimas',
        // Infrastruktūra
        'up' => 'sveikatos patikra (load balancer\'iui)',
        'storage.*' => 'privataus disko failai tik su pasirašytu (signed) URL',
        'livewire.*' => 'Livewire (Filament) techniniai maršrutai – komponentai patys tikrina teises',
        'default-livewire.*' => 'Livewire atnaujinimai – komponentai patys tikrina teises',
        'filament.admin.auth.*' => 'admin panelės prisijungimas',
        // Etapo 6 ir 7 maršrutai, kurie turi būti vieši (pvz. Paysera callback'as), įrašomi čia sujungiant šakas
        'billing.callback*' => 'mokėjimų tiekėjo callback\'as (tikrinamas parašas)',
        'payments.callback*' => 'mokėjimų tiekėjo callback\'as (tikrinamas parašas)',
    ];
}

/**
 * @return list<string>
 */
function routeIdentifiers(RoutingRoute $route): array
{
    return array_values(array_filter([$route->getName(), $route->uri()]));
}

function isPublicRoute(RoutingRoute $route): bool
{
    foreach (array_keys(publicRoutes()) as $pattern) {
        foreach (routeIdentifiers($route) as $identifier) {
            if (Str::is($pattern, $identifier)) {
                return true;
            }
        }
    }

    // Livewire ir Inertia DevTools URI su atsitiktiniu priešdėliu (livewire-42dd8ff9/…, _inertia/…)
    return Str::startsWith($route->uri(), ['livewire', '_inertia/']);
}

/**
 * Router::gatherRouteMiddleware() išskleidžia grupes ir alias'us į klases („auth" → Authenticate,
 * „filament.actions" → web + auth), todėl tikrinam tai, kas tikrai bus vykdoma.
 */
function hasAuthMiddleware(RoutingRoute $route): bool
{
    foreach (app('router')->gatherRouteMiddleware($route) as $middleware) {
        $class = is_string($middleware) ? Str::before($middleware, ':') : '';

        if (is_a($class, Authenticate::class, true)) {
            return true;
        }
    }

    return false;
}

test('kiekvienas neviešas maršrutas reikalauja prisijungimo', function () {
    $unprotected = collect(Route::getRoutes()->getRoutes())
        ->reject(fn (RoutingRoute $route): bool => isPublicRoute($route))
        ->reject(fn (RoutingRoute $route): bool => hasAuthMiddleware($route))
        ->map(fn (RoutingRoute $route): string => implode('|', $route->methods()).' '.$route->uri().' ('.($route->getName() ?? 'be vardo').')')
        ->values()
        ->all();

    expect($unprotected)->toBe([]);
});

test('rolės middleware: teikėjo ir kliento sritys', function () {
    $requirements = [
        'teikejas/*' => 'role:provider',
        'mano-pasiulymai' => 'role:provider',
        'paskyra/profilis*' => 'role:provider',
        'paskyra/darbai*' => 'role:provider',
        'mano-uzklausos' => 'role:client',
        'uzklausos/nauja' => 'role:client',
    ];

    $missing = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        foreach ($requirements as $pattern => $middleware) {
            if (Str::is($pattern, $route->uri()) && ! in_array($middleware, $route->gatherMiddleware(), true)) {
                $missing[] = $route->uri().' → '.$middleware;
            }
        }
    }

    expect($missing)->toBe([]);
});

test('admin panelės maršrutai (išskyrus prisijungimą) saugomi Filament autentifikacijos', function () {
    $adminRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route): bool => Str::startsWith((string) $route->getName(), 'filament.admin.'))
        ->reject(fn (RoutingRoute $route): bool => Str::is('filament.admin.auth.*', (string) $route->getName()));

    expect($adminRoutes)->not->toBeEmpty();

    $adminRoutes->each(fn (RoutingRoute $route) => expect(hasAuthMiddleware($route))->toBeTrue($route->uri()));
});

test('svečias neviešuose GET puslapiuose nukreipiamas prisijungti, o ne mato turinį', function () {
    $checked = 0;

    foreach (Route::getRoutes()->getRoutes() as $route) {
        if (! in_array('GET', $route->methods(), true) || isPublicRoute($route) || $route->parameterNames() !== []) {
            continue;
        }

        $status = $this->get('/'.ltrim($route->uri(), '/'))->getStatusCode();

        expect($status)->toBeIn([301, 302, 401, 403], $route->uri().' grąžino '.$status);
        $checked++;
    }

    expect($checked)->toBeGreaterThan(10);
});
