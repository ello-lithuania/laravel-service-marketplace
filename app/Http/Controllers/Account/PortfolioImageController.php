<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\PortfolioItem;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Vienos atlikto darbo nuotraukos pašalinimas.
 */
class PortfolioImageController extends Controller
{
    /**
     * scopeBindings() maršrute: {media} ieškomas tik tarp $portfolioItem->media() – svetimos
     * nuotraukos ID gautų 404, net jei darbas savas. https://laravel.com/docs/13.x/routing#implicit-model-binding-scoping
     */
    public function destroy(PortfolioItem $portfolioItem, Media $media): RedirectResponse
    {
        abort_unless($media->collection_name === 'images', 404);

        $media->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Nuotrauka pašalinta.']);

        return back();
    }
}
