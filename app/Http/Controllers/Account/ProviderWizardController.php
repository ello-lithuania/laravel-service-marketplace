<?php

namespace App\Http\Controllers\Account;

use App\Enums\ProviderWizardStep;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * /paskyra/profilis – vedlio „įėjimas": nukreipia į žingsnį, nuo kurio reikia tęsti.
 * Teikėjas gali bet kada išeiti ir vėliau grįžti – kiekvienas žingsnis jau išsaugotas.
 */
class ProviderWizardController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        return to_route(ProviderWizardStep::resume($request->user()?->providerProfile)->routeName());
    }
}
