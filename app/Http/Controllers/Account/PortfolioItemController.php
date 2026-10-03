<?php

namespace App\Http\Controllers\Account;

use App\Actions\Portfolio\SavePortfolioItem;
use App\Actions\ProviderProfile\ListRegionsWithCities;
use App\Http\Controllers\Account\Concerns\InteractsWithProviderProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\PortfolioItemRequest;
use App\Models\Category;
use App\Models\PortfolioItem;
use App\Models\ProviderProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Atlikti darbai (portfolio): sąrašas, kūrimas, redagavimas, trynimas.
 * Teisės – maršrutuose per Policy: ->can('update', 'portfolioItem') (routes/account.php).
 */
class PortfolioItemController extends Controller
{
    use InteractsWithProviderProfile;

    public function index(Request $request): Response
    {
        $profile = $this->currentProfile($request);

        // Visi ryšiai užkraunami iš anksto: 4 užklausos iš viso, kad ir kiek darbų būtų (ne 1 + 3N)
        $items = $profile->portfolioItems()->with(['media', 'category:id,name', 'city:id,name'])->get();

        return Inertia::render('account/portfolio/Index', [
            'items' => array_values($items->map(fn (PortfolioItem $item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'category' => $item->category?->name,
                'city' => $item->city?->name,
                'completed_date' => $item->completed_date?->toDateString(),
                'images_count' => $item->getMedia('images')->count(),
                'cover' => $item->imageUrls()[0]['thumb'] ?? null,
            ])->all()),
            'maxImages' => PortfolioItem::MAX_IMAGES,
        ]);
    }

    public function create(Request $request, ListRegionsWithCities $regions): Response
    {
        $profile = $this->currentProfile($request);

        return Inertia::render('account/portfolio/Create', [
            ...$this->formOptions($profile, $regions),
        ]);
    }

    public function store(PortfolioItemRequest $request, SavePortfolioItem $save): RedirectResponse
    {
        $profile = $this->currentProfile($request);

        $save->handle($profile, $request->itemData(), $request->images());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Darbas pridėtas.']);

        return to_route('portfolio.index');
    }

    public function edit(Request $request, PortfolioItem $portfolioItem, ListRegionsWithCities $regions): Response
    {
        $profile = $this->currentProfile($request);

        return Inertia::render('account/portfolio/Edit', [
            'item' => [
                'id' => $portfolioItem->id,
                ...$portfolioItem->only(['title', 'description', 'category_id', 'city_id']),
                'completed_date' => $portfolioItem->completed_date?->toDateString(),
                'images' => $portfolioItem->imageUrls(),
            ],
            ...$this->formOptions($profile, $regions),
        ]);
    }

    public function update(PortfolioItemRequest $request, PortfolioItem $portfolioItem, SavePortfolioItem $save): RedirectResponse
    {
        $profile = $this->currentProfile($request);

        $save->handle($profile, $request->itemData(), $request->images(), $portfolioItem);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Išsaugota.']);

        return to_route('portfolio.edit', $portfolioItem);
    }

    public function destroy(PortfolioItem $portfolioItem): RedirectResponse
    {
        // Ištrynus modelį medialibrary pati ištrina ir jo nuotraukas (failus bei media eilutes)
        $portfolioItem->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Darbas ištrintas.']);

        return to_route('portfolio.index');
    }

    /**
     * Pasirinkimai formai: tik teikėjo kategorijos ir visos savivaldybės.
     *
     * @return array<string, mixed>
     */
    private function formOptions(ProviderProfile $profile, ListRegionsWithCities $regions): array
    {
        return [
            'categories' => array_values($profile->categories()
                ->orderBy('name')
                ->get(['categories.id', 'categories.name'])
                ->map(fn (Category $category): array => ['id' => $category->id, 'name' => $category->name])
                ->all()),
            'regions' => $regions->handle(),
            'maxImages' => PortfolioItem::MAX_IMAGES,
        ];
    }
}
