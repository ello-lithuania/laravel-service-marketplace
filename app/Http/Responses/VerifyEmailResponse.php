<?php

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kur nukreipti patvirtinus el. paštą. Paprastai – atgal ten, kur žmogus ėjo (intended),
 * o jei to nežinom (pvz. nuorodą atidarė kitoje naršyklėje) – teikėją į vedlį, kitus į paskyrą.
 */
class VerifyEmailResponse implements VerifyEmailResponseContract
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        $user = $request->user();

        $home = $user instanceof User && $user->needsProviderOnboarding()
            ? route('provider.wizard', absolute: false)
            : Fortify::redirects('email-verification');

        return redirect()->intended($home.'?verified=1');
    }
}
