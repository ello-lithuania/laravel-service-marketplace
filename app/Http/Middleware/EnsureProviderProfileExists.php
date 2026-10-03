<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vedlio 2–4 žingsniai, nuotraukos ir atlikti darbai saugomi prie profilio, todėl be profilio jų
 * atidaryti nėra prasmės. Tokiu atveju nukreipiam į 1 žingsnį (duomenys), kuriame profilis sukuriamas.
 * https://laravel.com/docs/13.x/middleware
 */
class EnsureProviderProfileExists
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->providerProfile === null) {
            Inertia::flash('toast', ['type' => 'info', 'message' => 'Pirmiausia užpildykite profilio duomenis.']);

            return to_route('provider.details.edit');
        }

        return $next($request);
    }
}
