<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ImageUploadRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Vartotojo avataras (visoms rolėms). Rodomas nustatymų puslapyje „Profilis".
 */
class AvatarController extends Controller
{
    public function update(ImageUploadRequest $request): RedirectResponse
    {
        // addMediaFromRequest() paima failą iš užklausos lauko ir įrašo į diską (public) bei media lentelę
        $request->user()?->addMediaFromRequest('image')->toMediaCollection('avatar');

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Nuotrauka atnaujinta.']);

        return back();
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()?->clearMediaCollection('avatar');

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Nuotrauka pašalinta.']);

        return back();
    }
}
