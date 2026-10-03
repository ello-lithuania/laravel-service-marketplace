<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Jau prisijungęs vartotojas, kurį administratorius ką tik užblokavo, atjungiamas prie kito paspaudimo
 * (sesija gali būti Redis'e ar faile, kurių BanUser ištrinti negali). Prisijungti iš naujo neleidžia AuthenticateUser.
 *
 * Įtrauktas į „web" grupę (bootstrap/app.php), todėl veikia visuose Inertia puslapiuose. Filament panelė turi
 * savo middleware sąrašą – ten užblokuotus administratorius sustabdo User::canAccessPanel().
 * https://laravel.com/docs/13.x/middleware
 */
class EnsureUserIsNotBanned
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->banned_at === null) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // JSON užklausos (pvz. varpelio sąrašas per useHttp) nukreipimo nesupras – grąžinam 401
        if ($request->expectsJson()) {
            return response()->json(['message' => __('moderation.banned_login')], 401);
        }

        return redirect()->route('login')->withErrors(['email' => __('moderation.banned_login')]);
    }
}
