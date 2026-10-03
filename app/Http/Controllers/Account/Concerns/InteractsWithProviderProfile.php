<?php

namespace App\Http\Controllers\Account\Concerns;

use App\Enums\ProviderStatus;
use App\Enums\ProviderWizardStep;
use App\Models\ProviderProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Bendri vedlio controller'ių pagalbininkai: dabartinio teikėjo profilis ir nukreipimas po išsaugojimo.
 */
trait InteractsWithProviderProfile
{
    /**
     * Prisijungusio teikėjo profilis. Middleware EnsureProviderProfileExists jau patikrino, kad jis yra,
     * o Policy dar kartą patvirtina, kad jį galima keisti (gynyba keliais sluoksniais).
     */
    protected function currentProfile(Request $request): ProviderProfile
    {
        $profile = $request->user()?->providerProfile;

        if ($profile === null) {
            abort(404);
        }

        Gate::authorize('update', $profile);

        return $profile;
    }

    /**
     * Po žingsnio išsaugojimo – pranešimas ir kitas žingsnis (po paskutinio – „Mano paskyra").
     */
    protected function redirectAfterStep(ProviderWizardStep $step, ProviderProfile $profile, ProviderStatus $statusBefore): RedirectResponse
    {
        $activated = $statusBefore === ProviderStatus::Pending && $profile->status === ProviderStatus::Active;

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $activated ? 'Profilis aktyvuotas – dabar jį mato klientai!' : 'Išsaugota.',
        ]);

        $next = $step->next();

        return to_route($next?->routeName() ?? 'dashboard');
    }
}
