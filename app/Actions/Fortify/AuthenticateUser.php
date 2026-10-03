<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

/**
 * Prisijungimo patikra (Fortify::authenticateUsing): tas pats, ką Fortify daro numatytai, plius
 * užblokuotų paskyrų patikra (Etapas 8).
 *
 * Kodėl užblokavimas tikrinamas PO slaptažodžio: kitaip bet kas, žinantis tik el. paštą, sužinotų,
 * kad paskyra užblokuota. Grąžinus null, Fortify parodo įprastą „Prisijungimo duomenys neatitinka".
 * Ištrinti (anonimizuoti) vartotojai nerandami visai – User modelis naudoja SoftDeletes.
 * https://laravel.com/docs/13.x/fortify#customizing-user-authentication
 */
final class AuthenticateUser
{
    public function __invoke(Request $request): ?User
    {
        /** @var UserProvider $provider */
        $provider = Auth::guard(config('fortify.guard'))->getProvider();

        $credentials = ['password' => (string) $request->input('password')];
        $user = $provider->retrieveByCredentials([Fortify::username() => (string) $request->input(Fortify::username())]);

        if (! $user instanceof User || ! $provider->validateCredentials($user, $credentials)) {
            return null;
        }

        if ($user->isBanned()) {
            throw ValidationException::withMessages([Fortify::username() => __('moderation.banned_login')]);
        }

        // Kaip Auth::attempt(): jei pasikeitė hash'o parametrai (pvz. BCRYPT_ROUNDS), slaptažodis perhash'inamas
        $provider->rehashPasswordIfRequired($user, $credentials);

        return $user;
    }
}
