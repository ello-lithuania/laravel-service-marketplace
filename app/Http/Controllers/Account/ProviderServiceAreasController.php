<?php

namespace App\Http\Controllers\Account;

use App\Actions\ProviderProfile\ListRegionsWithCities;
use App\Actions\ProviderProfile\SyncProviderServiceAreas;
use App\Enums\ProviderWizardStep;
use App\Http\Controllers\Account\Concerns\InteractsWithProviderProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdateProviderServiceAreasRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vedlio 3 žingsnis – aptarnavimo zonos: apskritis → savivaldybės arba „Visa Lietuva".
 */
class ProviderServiceAreasController extends Controller
{
    use InteractsWithProviderProfile;

    public function edit(Request $request, ListRegionsWithCities $regions): Response
    {
        $profile = $this->currentProfile($request);
        $selected = $profile->serviceAreas()->pluck('cities.id')->all();

        return Inertia::render('account/profile/Areas', [
            'wizard' => ProviderWizardStep::progress($profile),
            'regions' => $regions->handle(),
            // Pirmą kartą pasiūlom bazinį miestą – dažniausiai teikėjas dirba bent jau ten
            'selected' => $selected === [] && ! $profile->serves_whole_country ? [$profile->city_id] : $selected,
            'servesWholeCountry' => $profile->serves_whole_country,
        ]);
    }

    public function update(UpdateProviderServiceAreasRequest $request, SyncProviderServiceAreas $sync): RedirectResponse
    {
        $profile = $this->currentProfile($request);
        $statusBefore = $profile->status;

        $sync->handle($profile, $request->boolean('serves_whole_country'), $request->cityIds());

        return $this->redirectAfterStep(ProviderWizardStep::ServiceAreas, $profile, $statusBefore);
    }
}
