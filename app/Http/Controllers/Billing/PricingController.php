<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Resources\Billing\CreditPackageResource;
use App\Http\Resources\Billing\SubscriptionPlanResource;
use App\Models\Category;
use App\Models\CreditPackage;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Catalog\SeoMeta;
use App\Services\Subscriptions\PlanBenefits;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Viešas kainų puslapis /kainos: kreditų paketai ir prenumeratų planai (tik aktyvūs, pagal sort_order).
 * Teikėjui mygtukai iškart perka, svečiui – veda į registraciją kaip teikėją.
 */
class PricingController extends Controller
{
    public function __invoke(Request $request, PlanBenefits $benefits): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        $provider = $user?->isProvider() ? $user->providerProfile : null;

        $packages = CreditPackage::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $plans = SubscriptionPlan::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();

        // Pasiūlymo kaina priklauso nuo kategorijos – parodom intervalą („1–4 kreditai")
        $offerCost = Category::query()->where('depth', 3)->toBase()
            ->selectRaw('MIN(offer_cost_credits) as min_cost, MAX(offer_cost_credits) as max_cost')
            ->first();

        return Inertia::render('public/Pricing', [
            'packages' => CreditPackageResource::collection($packages)->resolve(),
            'plans' => SubscriptionPlanResource::collection($plans)->resolve(),
            'offerCost' => [
                'min' => (int) ($offerCost->min_cost ?? 1),
                'max' => (int) ($offerCost->max_cost ?? 1),
            ],
            // Etapas 9c: su kuo lyginti planų „Iki N kategorijų" (vedlio nuoroda „Daugiau kategorijų" veda čia)
            'freeMaxCategories' => $benefits->freeMaxCategories(),
            'viewer' => [
                'role' => $user?->role->value,
                'can_purchase' => $provider !== null,
                'current_plan_id' => $provider === null ? null : $this->currentPlanId($provider->subscriptions()->live()->get()),
            ],
            'seo' => (new SeoMeta(
                title: 'Kainos teikėjams: kreditai ir prenumeratos',
                description: 'Klientams platforma nemokama. Teikėjai pasiūlymus siunčia už kreditus: pirkite kreditų paketą '
                    .'arba rinkitės mėnesio prenumeratą su kreditais kas mėnesį.',
                canonical: route('pricing'),
            ))->toArray(),
        ]);
    }

    /**
     * Dabar galiojančios ir automatiškai pratęsiamos prenumeratos planas – jo kortelėje rodom „Jūsų planas".
     *
     * @param  Collection<int, Subscription>  $subscriptions
     */
    private function currentPlanId(Collection $subscriptions): ?int
    {
        return $subscriptions
            ->first(fn (Subscription $subscription): bool => $subscription->isCurrent() && $subscription->auto_renew)
            ?->subscription_plan_id;
    }
}
