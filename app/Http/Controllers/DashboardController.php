<?php

namespace App\Http\Controllers;

use App\Actions\ProviderProfile\BuildProfileChecklist;
use App\Models\ProviderProfile;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * „Mano paskyra": kiekviena rolė mato savo skydelį. Vienas veiksmas – todėl __invoke
 * (single action controller). https://laravel.com/docs/13.x/controllers#single-action-controllers
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, BuildProfileChecklist $checklist): Response
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'client' => $user->isClient() ? [
                'service_requests_count' => $user->serviceRequests()->count(),
            ] : null,
            'provider' => $user->isProvider() ? $this->providerPanel($user->providerProfile, $checklist) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function providerPanel(?ProviderProfile $profile, BuildProfileChecklist $checklist): array
    {
        return [
            'display_name' => $profile?->display_name,
            'status' => $profile?->status->value,
            'status_label' => $profile?->status->label(),
            'credits_balance' => $profile->credits_balance ?? 0,
            'portfolio_count' => $profile?->portfolioItems()->count() ?? 0,
            'checklist' => $checklist->handle($profile),
            'wizard_url' => route('provider.wizard', absolute: false),
        ];
    }
}
