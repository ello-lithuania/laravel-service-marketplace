<?php

namespace App\Http\Controllers\Account;

use App\Actions\ProviderProfile\BuildCategoryTree;
use App\Actions\ProviderProfile\SyncProviderCategories;
use App\Enums\ProviderWizardStep;
use App\Http\Controllers\Account\Concerns\InteractsWithProviderProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdateProviderCategoriesRequest;
use App\Services\Subscriptions\PlanBenefits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vedlio 2 žingsnis – kategorijos (3 lygių medis su varnelėmis).
 */
class ProviderCategoriesController extends Controller
{
    use InteractsWithProviderProfile;

    public function edit(Request $request, BuildCategoryTree $tree, PlanBenefits $benefits): Response
    {
        $profile = $this->currentProfile($request);
        $limit = $benefits->maxCategories($profile);

        return Inertia::render('account/profile/Categories', [
            'wizard' => ProviderWizardStep::progress($profile),
            'tree' => $tree->handle(),
            'selected' => $profile->categories()->pluck('categories.id')->all(),
            // Etapas 9c: riba pagal prenumeratą (be jos – config/marketplace.php). Vue ją rodo „X iš Y" ir
            // neleidžia pažymėti daugiau, bet galutinai tikrina SyncProviderCategories
            'categoryLimit' => [
                'max' => $limit,
                'plan' => $benefits->currentPlanName($profile),
                'can_upgrade' => $benefits->canRaiseCategoryLimit($limit),
            ],
        ]);
    }

    public function update(UpdateProviderCategoriesRequest $request, SyncProviderCategories $sync): RedirectResponse
    {
        $profile = $this->currentProfile($request);
        $statusBefore = $profile->status;

        $sync->handle($profile, $request->categoryIds());

        return $this->redirectAfterStep(ProviderWizardStep::Categories, $profile, $statusBefore);
    }
}
