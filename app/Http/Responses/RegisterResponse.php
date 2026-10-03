<?php

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kur nukreipti ką tik užsiregistravusį vartotoją. Fortify leidžia pakeisti savo atsakymo klasę
 * per service container (registruojama FortifyServiceProvider::register()).
 * https://laravel.com/docs/13.x/fortify#customizing-redirects
 *
 * Teikėjas keliauja į profilio vedlį, klientas – į paskyrą. Jei el. paštas dar nepatvirtintas,
 * „verified" middleware pirma parodys patvirtinimo puslapį, o patvirtinus grąžins čia (intended).
 */
class RegisterResponse implements RegisterResponseContract
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 201);
        }

        $user = $request->user();

        $home = $user instanceof User && $user->needsProviderOnboarding()
            ? route('provider.wizard', absolute: false)
            : Fortify::redirects('register');

        return redirect()->intended($home);
    }
}
