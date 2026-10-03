<?php

namespace App\Http\Controllers\Account;

use App\Enums\ProviderWizardStep;
use App\Http\Controllers\Account\Concerns\InteractsWithProviderProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ImageUploadRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Teikėjo logotipas ir viršelio nuotrauka (medialibrary kolekcijos „logo" ir „cover").
 */
class ProviderImagesController extends Controller
{
    use InteractsWithProviderProfile;

    /**
     * Lietuviškas URL segmentas → medialibrary kolekcija.
     * Maršrute ->whereIn('collection', ...) leidžia tik šias reikšmes.
     */
    public const COLLECTIONS = [
        'logotipas' => 'logo',
        'virselis' => 'cover',
    ];

    public function edit(Request $request): Response
    {
        $profile = $this->currentProfile($request);

        return Inertia::render('account/profile/Images', [
            'wizard' => ProviderWizardStep::progress($profile),
            'logo' => $profile->logoUrl(),
            'cover' => $profile->coverUrl(),
        ]);
    }

    public function update(ImageUploadRequest $request, string $collection): RedirectResponse
    {
        $profile = $this->currentProfile($request);

        // singleFile() kolekcija: senas failas (ir jo miniatiūros) ištrinamas automatiškai
        $profile->addMediaFromRequest('image')->toMediaCollection(self::COLLECTIONS[$collection]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Nuotrauka įkelta.']);

        return back();
    }

    public function destroy(Request $request, string $collection): RedirectResponse
    {
        $profile = $this->currentProfile($request);

        $profile->clearMediaCollection(self::COLLECTIONS[$collection]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Nuotrauka pašalinta.']);

        return back();
    }
}
