<?php

use App\Enums\SubscriptionStatus;
use App\Models\ProviderProfile;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\Subscriptions\PlanBenefits;
use App\Services\Subscriptions\PlanFeatures;

/**
 * Etapas 9c: „dabar galiojanti prenumerata ir jos privalumai" – vienoje vietoje (PlanBenefits).
 */
test('scope current() atrenka lygiai tas pačias prenumeratas kaip isCurrent()', function () {
    $profile = ProviderProfile::factory()->create();
    $make = fn (array $attributes) => Subscription::factory()->for($profile)->create($attributes);

    $subscriptions = collect([
        $make([]), // aktyvi dabar
        $make(['status' => SubscriptionStatus::Cancelled, 'auto_renew' => false]), // atšaukta, bet dar galioja
        $make(['starts_at' => now()->addDays(5), 'ends_at' => now()->addDays(35)]), // suplanuota (plano keitimas)
        $make(['status' => SubscriptionStatus::PastDue, 'starts_at' => now()->subDays(31), 'ends_at' => now()->subDay()]),
        $make(['status' => SubscriptionStatus::Expired, 'starts_at' => now()->subDays(40), 'ends_at' => now()->subDays(10)]),
        $make(['ends_at' => now()->subMinute()]), // aktyvi, bet laikotarpis ką tik baigėsi (renew dar nesuveikė)
    ]);

    $expected = $subscriptions->filter(fn (Subscription $subscription) => $subscription->isCurrent())->pluck('id');

    expect(Subscription::query()->current()->pluck('id')->sort()->values()->all())
        ->toBe($expected->sort()->values()->all())
        ->toHaveCount(2);
});

test('PlanFeatures saugiai skaito features JSON', function (?array $json, ?int $max, bool $badge) {
    $features = PlanFeatures::fromArray($json);

    expect($features->maxCategories)->toBe($max)->and($features->badge)->toBe($badge);
})->with([
    'tuščia' => [null, null, false],
    'pilna' => [['max_categories' => 30, 'badge' => true], 30, true],
    'skaičius tekstu' => [['max_categories' => '12'], 12, false],
    'neigiamas ir ne bool' => [['max_categories' => -1, 'badge' => 'taip'], null, false],
]);

test('ženklelis – tik dabar galiojančiai prenumeratai su badge planu', function () {
    $pro = SubscriptionPlan::factory()->create(['features' => ['badge' => true]]);
    $basic = SubscriptionPlan::factory()->create(['features' => ['badge' => false]]);
    $benefits = app(PlanBenefits::class);

    $withPro = ProviderProfile::factory()->create();
    Subscription::factory()->for($withPro)->for($pro, 'plan')->cancelled()->create();

    $withBasic = ProviderProfile::factory()->create();
    Subscription::factory()->for($withBasic)->for($basic, 'plan')->create();

    $expired = ProviderProfile::factory()->create();
    Subscription::factory()->for($expired)->for($pro, 'plan')->expired()->create();

    $scheduled = ProviderProfile::factory()->create();
    Subscription::factory()->for($scheduled)->for($pro, 'plan')->create(['starts_at' => now()->addDay(), 'ends_at' => now()->addMonth()]);

    $free = ProviderProfile::factory()->create();

    expect($benefits->hasBadge($withPro))->toBeTrue()
        ->and($benefits->hasBadge($withBasic))->toBeFalse()
        ->and($benefits->hasBadge($expired))->toBeFalse()
        ->and($benefits->hasBadge($scheduled))->toBeFalse()
        ->and($benefits->hasBadge($free))->toBeFalse()
        ->and($benefits->currentPlanName($withPro))->toBe($pro->name)
        ->and($benefits->currentPlanName($free))->toBeNull();
});

test('nuoroda į didesnį planą – tik jei toks planas parduodamas', function () {
    config(['marketplace.free_max_categories' => 5]);
    $benefits = app(PlanBenefits::class);

    expect($benefits->canRaiseCategoryLimit(5))->toBeFalse();

    SubscriptionPlan::factory()->create(['features' => ['max_categories' => 30], 'is_active' => false]);
    expect($benefits->canRaiseCategoryLimit(5))->toBeFalse();

    // Išsaugojus planą PlanBenefits pamiršta atmintyje laikytą sąrašą (SubscriptionPlan::booted)
    SubscriptionPlan::factory()->create(['features' => ['max_categories' => 10]]);
    expect($benefits->canRaiseCategoryLimit(5))->toBeTrue()
        ->and($benefits->canRaiseCategoryLimit(10))->toBeFalse();
});

test('kainų puslapio punktai skaitomi per tą patį PlanFeatures', function () {
    SubscriptionPlan::factory()->create([
        'credits_per_period' => 60,
        'features' => ['max_categories' => 30, 'badge' => true],
    ]);

    $this->get(route('pricing'))->assertInertia(fn ($page) => $page
        ->where('plans.0.features', ['60 kreditų kas mėn.', 'Iki 30 paslaugų kategorijų', 'Ženklelis „PRO" profilyje ir kataloge']));
});
