<?php

namespace App\Http\Controllers\Account;

use App\Actions\ProviderProfile\UpdateProviderPrices;
use App\Enums\PriceUnit;
use App\Enums\ProviderWizardStep;
use App\Http\Controllers\Account\Concerns\InteractsWithProviderProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdateProviderPricesRequest;
use App\Models\Category;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vedlio 4 žingsnis – kainos „nuo" pasirinktoms kategorijoms (neprivalomas).
 */
class ProviderPricesController extends Controller
{
    use InteractsWithProviderProfile;

    public function edit(Request $request): Response
    {
        $profile = $this->currentProfile($request);

        // with('parent.parent') – tėvai ir seneliai užkraunami 2 papildomomis užklausomis visiems iš karto
        // (eager loading), o ne po užklausą kiekvienai kategorijai (N+1)
        $categories = $profile->categories()
            ->with('parent.parent')
            ->orderBy('depth')
            ->orderBy('name')
            ->get();

        return Inertia::render('account/profile/Prices', [
            'wizard' => ProviderWizardStep::progress($profile),
            'categories' => array_values($categories->map(function (Category $category): array {
                // pivot – tarpinės lentelės eilutė (category_provider_profile) su withPivot() laukais
                $pivot = $category->getRelation('pivot');
                $cents = $pivot instanceof Pivot ? $pivot->getAttribute('price_from_cents') : null;
                $unit = $pivot instanceof Pivot ? $pivot->getAttribute('price_unit') : null;

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'path' => $this->path($category),
                    'price_from' => is_numeric($cents) ? $this->toEuros((int) $cents) : null,
                    'price_unit' => is_string($unit) ? $unit : null,
                ];
            })->all()),
            'units' => array_map(fn (PriceUnit $unit): array => [
                'value' => $unit->value,
                'label' => $unit->label(),
            ], PriceUnit::cases()),
        ]);
    }

    public function update(UpdateProviderPricesRequest $request, UpdateProviderPrices $update): RedirectResponse
    {
        $profile = $this->currentProfile($request);
        $statusBefore = $profile->status;

        $update->handle($profile, $request->prices());

        return $this->redirectAfterStep(ProviderWizardStep::Prices, $profile, $statusBefore);
    }

    /**
     * „Statyba ir remontas › Apdailos darbai" – kad būtų aišku, kuriai šakai priklauso kategorija.
     */
    private function path(Category $category): string
    {
        $names = [];
        $node = $category;

        // Tikrinam parent_id, o ne $node->parent: 1 lygio kategorijos „parent" ryšys neužkrautas,
        // ir preventLazyLoading už tokį prieigą mestų išimtį
        while ($node->parent_id !== null && ($node = $node->parent) !== null) {
            array_unshift($names, $node->name);
        }

        return implode(' › ', $names);
    }

    /**
     * 1550 → „15,50", 1500 → „15" (formos laukui, lietuviškas kablelis).
     */
    private function toEuros(int $cents): string
    {
        return $cents % 100 === 0
            ? (string) intdiv($cents, 100)
            : sprintf('%d,%02d', intdiv($cents, 100), $cents % 100);
    }
}
