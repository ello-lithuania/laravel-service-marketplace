<?php

namespace App\Http\Controllers\Account;

use App\Actions\ProviderProfile\ListRegionsWithCities;
use App\Actions\ProviderProfile\SaveProviderDetails;
use App\Enums\ProviderStatus;
use App\Enums\ProviderType;
use App\Enums\ProviderWizardStep;
use App\Http\Controllers\Account\Concerns\InteractsWithProviderProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdateProviderDetailsRequest;
use App\Models\ProviderProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vedlio 1 žingsnis – duomenys. Čia profilis sukuriamas pirmą kartą.
 */
class ProviderDetailsController extends Controller
{
    use InteractsWithProviderProfile;

    public function edit(Request $request, ListRegionsWithCities $regions): Response
    {
        $user = $request->user();
        $profile = $user?->providerProfile;

        return Inertia::render('account/profile/Details', [
            'wizard' => ProviderWizardStep::progress($profile),
            // Tik formos laukai – ne visas modelis (CLAUDE.md: į props tik tai, ko reikia)
            'profile' => $profile === null ? null : [
                'type' => $profile->type->value,
                ...$profile->only([
                    'display_name', 'company_code', 'vat_code', 'city_id', 'years_experience',
                    'headline', 'description', 'website',
                ]),
            ],
            'phone' => $user?->phone,
            'suggestedName' => $user?->name,
            'types' => array_map(fn (ProviderType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ], ProviderType::cases()),
            'regions' => $regions->handle(),
        ]);
    }

    public function update(UpdateProviderDetailsRequest $request, SaveProviderDetails $save): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        $profile = $user->providerProfile;

        // Naujam profiliui – „ar gali sukurti?", esamam – „ar gali keisti būtent šį?"
        $profile === null
            ? Gate::authorize('create', ProviderProfile::class)
            : Gate::authorize('update', $profile);

        $statusBefore = $profile->status ?? ProviderStatus::Pending;
        $profile = $save->handle($user, $request->validated());

        return $this->redirectAfterStep(ProviderWizardStep::Details, $profile, $statusBefore);
    }
}
