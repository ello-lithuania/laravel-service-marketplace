<?php

use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;

test('vieši puslapiai gauna saugos antraštes ir CSP su nonce, kurį turi ir puslapio skriptai', function () {
    $response = $this->get('/')->assertOk();

    $csp = (string) $response->headers->get('Content-Security-Policy');
    preg_match("/'nonce-([^']+)'/", $csp, $matches);
    $nonce = $matches[1] ?? null;

    expect($nonce)->not->toBeNull()
        ->and($csp)->toContain("default-src 'self'")
        ->toContain("object-src 'none'")
        ->toContain("frame-ancestors 'none'")
        ->not->toContain('unsafe-eval')
        ->and($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
        ->and($response->headers->get('Permissions-Policy'))->toContain('camera=()')
        // Ne produkcijoje HSTS nesiunčiam: localhost'ui naršyklė metus reikalautų HTTPS
        ->and($response->headers->has('Strict-Transport-Security'))->toBeFalse();

    // Įterptas tamsaus režimo skriptas ir Vite skriptai – su tuo pačiu nonce
    expect($response->getContent())->toContain('<script nonce="'.$nonce.'"');
});

test('Filament panelei leidžiamas Alpine.js (unsafe-eval), bet ne svetimi domenai', function () {
    $this->actingAs(User::factory()->admin()->create());

    $csp = (string) $this->get('/admin')->assertOk()->headers->get('Content-Security-Policy');

    expect($csp)->toContain("script-src 'self' 'unsafe-inline' 'unsafe-eval'")
        ->not->toContain('nonce-')
        ->not->toContain('ui-avatars.com');
});

test('produkcijoje – HSTS ir upgrade-insecure-requests', function () {
    app()->detectEnvironment(fn () => 'production');

    $response = $this->get('/');

    expect($response->headers->get('Strict-Transport-Security'))->toBe('max-age=31536000; includeSubDomains')
        ->and($response->headers->get('Content-Security-Policy'))->toContain('upgrade-insecure-requests');
});

test('report-only režimas ir papildomi šaltiniai iš konfigūracijos', function () {
    config([
        'security.csp.report_only' => true,
        'security.csp.report_uri' => 'https://report.example.test/csp',
        'security.csp.extra.img-src' => ['https://cdn.example.test'],
    ]);

    $response = $this->get('/');

    expect($response->headers->has('Content-Security-Policy'))->toBeFalse()
        ->and($response->headers->get('Content-Security-Policy-Report-Only'))
        ->toContain('img-src \'self\' data: blob: https://cdn.example.test')
        ->toContain('report-uri https://report.example.test/csp');
});

/**
 * CSP antraštė, kai veikia Vite dev serveris su nurodytu adresu (public/hot turinys).
 */
function cspWithViteDevServer(string $url): string
{
    $hot = storage_path('framework/testing-vite.hot');
    File::put($hot, $url);
    Vite::useHotFile($hot);

    try {
        return (string) test()->get('/login')->headers->get('Content-Security-Policy');
    } finally {
        File::delete($hot);
    }
}

test('dev\'e su Vite serveriu leidžiamas jo adresas ir HMR websocket\'as', function () {
    expect(cspWithViteDevServer('http://localhost:5173'))
        ->toContain("script-src 'self' 'nonce-")
        ->toContain("style-src 'self' 'unsafe-inline' http://localhost:5173")
        ->toContain('ws://localhost:5173');
});

test('Vite IPv6 adresas ([::1], Windows) į CSP neįrašomas – naršyklė jį atmestų ir užblokuotų CSS', function () {
    expect(cspWithViteDevServer('http://[::1]:5173'))
        ->not->toContain('[::1]')
        ->toContain("style-src 'self' 'unsafe-inline' http: https:")
        ->toContain('ws: wss:');
});

test('antraštes gauna ir JSON bei klaidų atsakymai', function () {
    $this->get('/neegzistuojantis-puslapis')
        ->assertNotFound()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Security-Policy');

    Route::get('/_test/json', fn () => ['ok' => true]);
    $this->getJson('/_test/json')->assertHeader('X-Frame-Options', 'DENY');
});

test('išjungus – antraščių nėra', function () {
    config(['security.enabled' => false]);

    $response = $this->get('/');

    expect($response->headers->has('Content-Security-Policy'))->toBeFalse()
        ->and($response->headers->has('X-Frame-Options'))->toBeFalse();
});
