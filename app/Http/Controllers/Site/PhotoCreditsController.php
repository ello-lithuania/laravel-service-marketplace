<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\Catalog\SeoMeta;
use App\Services\Site\PhotoCredits;
use Illuminate\Http\Resources\Json\JsonResource;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Viešas puslapis /nuotrauku-autoriai: kieno nuotraukos naudojamos ir pagal kokią licenciją.
 * CC BY ir CC BY-SA licencijos autorių nurodyti reikalauja, Pexels – ne, bet rodom visus (Etapas 10).
 */
class PhotoCreditsController extends Controller
{
    private const DEMO_PER_PAGE = 24;

    public function __invoke(PhotoCredits $credits): Response
    {
        $demo = $credits->demo(self::DEMO_PER_PAGE);
        // Svetainės ir kategorijų nuotraukų nedaug – rodom pirmame puslapyje, toliau puslapiuojamas tik demo rinkinys
        $firstPage = $demo->currentPage() === 1;

        return Inertia::render('public/PhotoCredits', [
            'sitePhotos' => $firstPage ? $credits->sitePhotos() : [],
            'categories' => $firstPage ? $credits->categories() : [],
            // JsonResource::collection – tas pats { data, links, meta } formatas kaip kitų puslapiuojamų sąrašų
            'demo' => JsonResource::collection($demo),
            'seo' => (new SeoMeta(
                title: 'Nuotraukų autoriai',
                description: 'Svetainėje naudojamų nuotraukų autoriai, šaltiniai ir licencijos.',
                canonical: route('photo-credits'),
                // Techninis puslapis – paieškos rezultatuose jo nereikia
                indexable: false,
            ))->toArray(),
        ]);
    }
}
