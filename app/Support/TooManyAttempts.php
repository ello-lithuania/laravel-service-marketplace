<?php

namespace App\Support;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Atsakymas, kai viršyta dažnio riba (Etapas 6). Laravel pagal nutylėjimą grąžintų 429 su angliška
 * „Too Many Attempts." – mes grąžinam lietuvišką tekstą ir kiekvienam užklausos tipui tinkamą formą:
 *  - JSON (API, useHttp) – 429 su {"message": "…"};
 *  - Inertia forma – grįžtam atgal su klaida (errors.throttle) ir pranešimu (toast): forma neišsivalo,
 *    o 429 Inertia'i atrodytų kaip klaidos langas;
 *  - kita – 429 klaidos puslapis su tuo pačiu tekstu.
 * https://laravel.com/docs/13.x/rate-limiting · https://laravel.com/docs/13.x/routing#rate-limiting
 */
final class TooManyAttempts
{
    /**
     * @param  array<string, int|string>  $headers  Retry-After, X-RateLimit-* (Laravel paduoda pats)
     */
    public static function response(Request $request, array $headers): Response
    {
        $seconds = (int) ($headers['Retry-After'] ?? 60);
        $message = __('limits.too_many', ['minutes' => max(1, (int) ceil($seconds / 60))]);

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 429, $headers);
        }

        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

            return back()->withErrors(['throttle' => $message])->withHeaders($headers);
        }

        return response($message, 429, $headers);
    }
}
