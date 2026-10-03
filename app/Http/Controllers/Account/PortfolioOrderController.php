<?php

namespace App\Http\Controllers\Account;

use App\Actions\Portfolio\ReorderPortfolioItems;
use App\Http\Controllers\Account\Concerns\InteractsWithProviderProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ReorderPortfolioItemsRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Atliktų darbų tvarka viešame profilyje (sort_order).
 */
class PortfolioOrderController extends Controller
{
    use InteractsWithProviderProfile;

    public function update(ReorderPortfolioItemsRequest $request, ReorderPortfolioItems $reorder): RedirectResponse
    {
        $reorder->handle($this->currentProfile($request), $request->orderedIds());

        return back();
    }
}
