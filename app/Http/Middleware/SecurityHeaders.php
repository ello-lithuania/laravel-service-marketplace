<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Saugos antraštės kiekvienam atsakymui (Etapas 8). Registruotas kaip globalus middleware (bootstrap/app.php),
 * todėl veikia ir Inertia puslapiams, ir Filament panelei, ir klaidų puslapiams.
 *
 * - Content-Security-Policy (CSP) – iš kur naršyklė gali krauti skriptus, stilius, paveikslėlius. Net jei į puslapį
 *   pakliūtų svetimas <script> (XSS), naršyklė jo nevykdys, nes jis neturi teisingo nonce.
 *   Vieša dalis: skriptai tik iš savo domeno ir su nonce (vienkartiniu atsitiktiniu raktu, kurį Vite prideda prie
 *   savo <script> žymų – Vite::useCspNonce()). Filament (/admin) veikia ant Alpine.js, kuris vertina išraiškas per
 *   new Function(), todėl ten reikia 'unsafe-eval' ir 'unsafe-inline'. Panelė pasiekiama tik administratoriams.
 * - X-Frame-Options + frame-ancestors – svetainės negalima įdėti į svetimą <iframe> (clickjacking).
 * - X-Content-Type-Options: nosniff – naršyklė „neatspėlioja" failo tipo (pvz. paveikslėlio kaip HTML).
 * - Referrer-Policy – kitoms svetainėms siunčiamas tik domenas, ne pilnas adresas su parametrais.
 * - Permissions-Policy – išjungiam naršyklės API, kurių nenaudojam (kamera, mikrofonas, vieta…).
 * - Strict-Transport-Security (HSTS) – tik produkcijoje: naršyklė jungsis tik per HTTPS.
 *
 * https://developer.mozilla.org/docs/Web/HTTP/CSP · https://laravel.com/docs/13.x/vite#content-security-policy-csp-nonce
 */
class SecurityHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('security.enabled')) {
            return $next($request);
        }

        // Nonce sugeneruojamas PRIEŠ piešiant puslapį – @vite jį pridės prie <script> ir <link rel="modulepreload">
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        if (app()->isProduction()) {
            $headers['Strict-Transport-Security'] = 'max-age='.config('security.hsts.max_age')
                .(config('security.hsts.include_subdomains') ? '; includeSubDomains' : '');
        }

        if ($this->shouldAddCsp($response)) {
            $headerName = config('security.csp.report_only')
                ? 'Content-Security-Policy-Report-Only'
                : 'Content-Security-Policy';
            $headers[$headerName] = $this->policy($request, $nonce);
        }

        foreach ($headers as $name => $value) {
            // Jei antraštę jau nustatė controller'is (pvz. failo atsisiuntimui) – nekeičiam
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    /**
     * Laravel klaidos puslapis dev'e (APP_DEBUG=true) turi įterptų skriptų – CSP jį „sulaužytų".
     */
    private function shouldAddCsp(Response $response): bool
    {
        if (! config('security.csp.enabled')) {
            return false;
        }

        return ! (config('app.debug') && $response->getStatusCode() >= 500);
    }

    private function policy(Request $request, string $nonce): string
    {
        $isAdmin = $request->is('admin', 'admin/*');
        $dev = $this->viteDevServer();
        $extra = config('security.csp.extra');

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => $isAdmin
                ? ["'self'", "'unsafe-inline'", "'unsafe-eval'"]
                : ["'self'", "'nonce-{$nonce}'"],
            // Inertia progreso juosta ir Vue/Filament komponentai įterpia stilius – stiliai mažiau pavojingi nei skriptai
            'style-src' => ["'self'", "'unsafe-inline'"],
            'img-src' => ["'self'", 'data:', 'blob:', ...$this->mediaOrigins(), ...$extra['img-src']],
            'font-src' => ["'self'", 'data:'],
            'connect-src' => ["'self'", ...$extra['connect-src']],
            'media-src' => ["'self'"],
            'frame-src' => ["'self'", ...$extra['frame-src']],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'", ...$extra['form-action']],
            'frame-ancestors' => ["'none'"],
        ];

        // Dev'e (npm run dev) skriptai, stiliai ir HMR websocket'as ateina iš Vite serverio (pvz. http://[::1]:5173)
        if ($dev !== null) {
            foreach (['script-src', 'style-src', 'img-src', 'font-src'] as $directive) {
                $directives[$directive][] = $dev;
            }
            $directives['connect-src'][] = $dev;
            $directives['connect-src'][] = preg_replace('#^http#', 'ws', $dev);
        }

        $policy = [];

        foreach ($directives as $name => $sources) {
            $policy[] = $name.' '.implode(' ', array_unique($sources));
        }

        if (app()->isProduction()) {
            $policy[] = 'upgrade-insecure-requests';
        }

        if (filled(config('security.csp.report_uri'))) {
            $policy[] = 'report-uri '.config('security.csp.report_uri');
        }

        return implode('; ', $policy);
    }

    /**
     * Vite dev serverio adresas iš public/hot failo (jis yra tik kai veikia „npm run dev").
     */
    private function viteDevServer(): ?string
    {
        if (! Vite::isRunningHot()) {
            return null;
        }

        $url = trim((string) file_get_contents(Vite::hotFile()));

        return $url !== '' ? rtrim($url, '/') : null;
    }

    /**
     * Jei nuotraukos laikomos kitur (pvz. S3 ar CDN – MEDIA_DISK su savo url), jų domenas leidžiamas img-src.
     *
     * @return list<string>
     */
    private function mediaOrigins(): array
    {
        $url = config('filesystems.disks.'.config('media-library.disk_name').'.url');

        if (! is_string($url) || ! str_starts_with($url, 'http')) {
            return [];
        }

        $origin = $this->origin($url);

        // Numatytasis public diskas – tas pats domenas kaip svetainė, jį jau leidžia 'self'
        return $origin === $this->origin((string) config('app.url')) ? [] : [$origin];
    }

    private function origin(string $url): string
    {
        $port = parse_url($url, PHP_URL_PORT);

        return parse_url($url, PHP_URL_SCHEME).'://'.parse_url($url, PHP_URL_HOST).($port ? ':'.$port : '');
    }
}
