<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Įleidžia tik nurodytų rolių vartotojus: ->middleware('role:provider') arba 'role:client,provider'.
 * Grubus filtras maršrutų grupėms; konkretaus įrašo teises tikrina Policy.
 * https://laravel.com/docs/13.x/middleware#middleware-parameters
 */
class EnsureUserHasRole
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $role = $request->user()?->role;

        abort_unless($role instanceof UserRole && in_array($role->value, $roles, true), 403);

        return $next($request);
    }
}
