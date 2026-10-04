<?php

use App\Models\City;
use App\Models\ProviderProfile;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Etapas 9c: ženklelis „PRO" katalogo kortelėse ir viešame profilyje (PlanBenefits::hasBadge).
 */
beforeEach(function () {
    $this->proPlan = SubscriptionPlan::factory()->create(['features' => ['max_categories' => 30, 'badge' => true]]);
    $this->basicPlan = SubscriptionPlan::factory()->create(['features' => ['max_categories' => 10, 'badge' => false]]);
});

/**
 * SQL užklausų skaičius antrą kartą atidarius puslapį (pirmas kartas „apšildo" katalogo cache).
 * forgetScopedInstances() – kaip naujoje HTTP užklausoje: #[Scoped] objektai (PlanBenefits planų sąrašas)
 * teste kitaip išliktų tarp užklausų.
 */
function badgeQueryCount(Closure $request): int
{
    $request();
    app()->forgetScopedInstances();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $request();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

test('kortelėse ženklelis – tik galiojančiai prenumeratai su badge planu; rikiavimas nesikeičia', function () {
    $pro = ProviderProfile::factory()->create(['display_name' => 'PRO meistras', 'rating_avg' => 3, 'reviews_count' => 3]);
    Subscription::factory()->for($pro)->for($this->proPlan, 'plan')->create();

    $basic = ProviderProfile::factory()->create(['rating_avg' => 4, 'reviews_count' => 3]);
    Subscription::factory()->for($basic)->for($this->basicPlan, 'plan')->create();

    $expired = ProviderProfile::factory()->create(['rating_avg' => 5, 'reviews_count' => 3]);
    Subscription::factory()->for($expired)->for($this->proPlan, 'plan')->expired()->create();

    $this->get(route('providers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/providers/Index')
            // Ženklelis rikiavimo nekeičia: vis tiek pagal reitingą
            ->where('providers.data.0.id', $expired->id)
            ->where('providers.data.0.has_pro_badge', false)
            ->where('providers.data.1.id', $basic->id)
            ->where('providers.data.1.has_pro_badge', false)
            ->where('providers.data.2.id', $pro->id)
            ->where('providers.data.2.has_pro_badge', true));
});

test('ženklelis ir kategorijos puslapio kortelėse, ir pradžios puslapyje', function () {
    [, , $leaf] = categoryBranch();
    $city = City::factory()->create();
    $pro = catalogProvider([$leaf], [$city], ['rating_avg' => 5, 'reviews_count' => 5]);
    Subscription::factory()->for($pro)->for($this->proPlan, 'plan')->cancelled()->create();

    $this->get(route('categories.show', $leaf))
        ->assertInertia(fn (Assert $page) => $page->where('providers.data.0.has_pro_badge', true));

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('featuredProviders.0.has_pro_badge', true));
});

test('viešame profilyje ženklelis rodomas, o pasibaigus prenumeratai – dingsta', function () {
    $profile = ProviderProfile::factory()->create();
    $subscription = Subscription::factory()->for($profile)->for($this->proPlan, 'plan')->create();

    $this->get(route('providers.show', $profile))
        ->assertInertia(fn (Assert $page) => $page->where('provider.has_pro_badge', true));

    // Nieko netrinam ir nesaugom – ženklelis skaičiuojamas iš galiojančios prenumeratos
    $this->travelTo($subscription->ends_at->addMinute());

    $this->get(route('providers.show', $profile))
        ->assertInertia(fn (Assert $page) => $page->where('provider.has_pro_badge', false));
});

test('ženklelis nedidina užklausų skaičiaus, kad ir kiek teikėjų turi prenumeratas', function (Closure $url) {
    $city = City::factory()->create();
    $seed = function (int $count) use ($city): void {
        foreach (range(1, $count) as $i) {
            $provider = catalogProvider([], [$city], ['rating_avg' => 4, 'reviews_count' => 3 + $i]);
            Subscription::factory()->for($provider)->for($i % 2 ? $this->proPlan : $this->basicPlan, 'plan')->create();
        }
    };

    $seed(2);
    $few = badgeQueryCount(fn () => $this->get($url($city))->assertOk());

    $seed(15);
    $many = badgeQueryCount(fn () => $this->get($url($city))->assertOk());

    // +2 užklausos visam puslapiui: galiojančios prenumeratos ir planų sąrašas (PlanBenefits)
    expect($many)->toBe($few)->toBeLessThanOrEqual(8);
})->with([
    'meistrai' => [fn (City $city) => route('providers.index', ['miestas' => $city->slug])],
    'pradžia' => [fn (City $city) => route('home')],
]);

test('profilio užklausų skaičius su prenumerata pastovus', function () {
    $profile = ProviderProfile::factory()->create();
    $free = badgeQueryCount(fn () => $this->get(route('providers.show', $profile))->assertOk());

    Subscription::factory()->for($profile)->for($this->proPlan, 'plan')->create();
    $subscribed = badgeQueryCount(fn () => $this->get(route('providers.show', $profile))->assertOk());

    // Su prenumerata – tik +1: planų sąrašas skaitomas, kai yra galiojanti prenumerata
    expect($subscribed)->toBe($free + 1);
});
