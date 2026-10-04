<?php

use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Etapas 9c: kategorijų riba vedlyje pagal prenumeratą (PlanBenefits + SyncProviderCategories).
 * Visa grupė (tėvas) skaičiuojama kaip viena eilutė; viršijantys ribą seni teikėjai kategorijas pasilieka.
 */
beforeEach(function () {
    config(['marketplace.free_max_categories' => 2]);
    $this->profile = ProviderProfile::factory()->create();
});

/**
 * Kiekvienai užklausai – naujas User objektas. Teste tas pats objektas lieka prisijungęs per kelias užklausas,
 * o jo providerProfile ryšys (ir currentSubscriptions) užkraunamas tik kartą – tikroje užklausoje taip nebūna.
 */
function freshProviderUser(ProviderProfile $profile): User
{
    return $profile->user()->firstOrFail();
}

/**
 * @return list<int>
 */
function leafIds(int $count): array
{
    return Category::factory()->leaf()->count($count)->create()->modelKeys();
}

/**
 * @return list<int>
 */
function savedCategoryIds(ProviderProfile $profile): array
{
    return $profile->categories()->pluck('categories.id')->sort()->values()->all();
}

test('be prenumeratos riba imama iš config ir perduodama puslapiui', function () {
    SubscriptionPlan::factory()->create(['features' => ['max_categories' => 10]]);

    $this->actingAs(freshProviderUser($this->profile))->get(route('provider.categories.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('account/profile/Categories')
            ->where('categoryLimit', ['max' => 2, 'plan' => null, 'can_upgrade' => true]));
});

test('viršijus ribą – lietuviška klaida ir niekas neįrašoma', function () {
    $this->actingAs(freshProviderUser($this->profile))
        ->put(route('provider.categories.update'), ['category_ids' => leafIds(3)])
        ->assertSessionHasErrors([
            'category_ids' => 'Galite pasirinkti iki 2 kategorijų, o pažymėjote 3. Pašalinkite nereikalingas arba rinkitės planą su daugiau kategorijų.',
        ]);

    expect(savedCategoryIds($this->profile))->toBe([]);
});

test('visa grupė su vaikais skaičiuojama kaip viena kategorija', function () {
    [$root, $group, $leaf] = categoryBranch();
    $other = Category::factory()->leaf()->create();

    // Atsiųsti 3 ID, bet vaikas apimtas tėvo – DB liks 2 eilutės, o tai telpa į ribą
    $this->actingAs(freshProviderUser($this->profile))
        ->put(route('provider.categories.update'), ['category_ids' => [$group->id, $leaf->id, $other->id]])
        ->assertSessionHasNoErrors();

    expect(savedCategoryIds($this->profile))->toBe(collect([$group->id, $other->id])->sort()->values()->all());
});

test('galiojanti prenumerata padidina ribą, o pasibaigusi – grąžina nemokamą', function () {
    $plan = SubscriptionPlan::factory()->create(['name' => 'Startas', 'features' => ['max_categories' => 4]]);
    $subscription = Subscription::factory()->for($this->profile)->for($plan, 'plan')->create();

    $this->actingAs(freshProviderUser($this->profile))->get(route('provider.categories.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('categoryLimit', ['max' => 4, 'plan' => 'Startas', 'can_upgrade' => false]));

    $this->actingAs(freshProviderUser($this->profile))
        ->put(route('provider.categories.update'), ['category_ids' => leafIds(4)])
        ->assertSessionHasNoErrors();

    expect(savedCategoryIds($this->profile))->toHaveCount(4);

    // Prenumerata baigėsi: kategorijos neištrinamos, bet riba vėl 2
    $subscription->update(['status' => 'expired', 'ends_at' => now()->subDay()]);

    $this->actingAs(freshProviderUser($this->profile))->get(route('provider.categories.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('categoryLimit.max', 2)->where('categoryLimit.plan', null));

    expect(savedCategoryIds($this->profile))->toHaveCount(4);
});

test('atšaukta, bet dar galiojanti prenumerata ribą dar duoda; nesumokėta (past_due) – ne', function (string $state, int $expected) {
    $plan = SubscriptionPlan::factory()->create(['features' => ['max_categories' => 7]]);
    Subscription::factory()->for($this->profile)->for($plan, 'plan')->{$state}()->create();

    $this->actingAs(freshProviderUser($this->profile))->get(route('provider.categories.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('categoryLimit.max', $expected));
})->with([
    'atšaukta' => ['cancelled', 7],
    'nesumokėta' => ['pastDue', 2],
    'pasibaigusi' => ['expired', 2],
]);

test('teikėjas virš ribos kategorijas pasilieka, gali pašalinti, bet naujų pridėti negali', function () {
    $old = leafIds(4);
    $this->profile->categories()->attach($old);
    $new = leafIds(1)[0];

    $this->actingAs(freshProviderUser($this->profile))->get(route('provider.categories.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('categoryLimit.max', 2)->has('selected', 4));

    // Išsaugoti nieko nekeitus – galima
    $this->actingAs(freshProviderUser($this->profile))
        ->put(route('provider.categories.update'), ['category_ids' => $old])
        ->assertSessionHasNoErrors();

    // Pašalinti vieną (vis dar virš ribos) – galima
    $this->actingAs(freshProviderUser($this->profile))
        ->put(route('provider.categories.update'), ['category_ids' => array_slice($old, 0, 3)])
        ->assertSessionHasNoErrors();
    expect(savedCategoryIds($this->profile))->toHaveCount(3);

    // Pakeisti vieną seną nauja, kol virš ribos – negalima
    $this->actingAs(freshProviderUser($this->profile))
        ->put(route('provider.categories.update'), ['category_ids' => [...array_slice($old, 0, 2), $new]])
        ->assertSessionHasErrors([
            'category_ids' => 'Jūsų planas leidžia iki 2 kategorijų, o dabar turite 3. Esamas galite palikti arba pašalinti, o naują pridėti galėsite tik tada, kai iš viso liks ne daugiau kaip 2.',
        ]);
    expect(savedCategoryIds($this->profile))->toHaveCount(3);

    // Pašalinus tiek, kad su nauja telpa į ribą – galima
    $this->actingAs(freshProviderUser($this->profile))
        ->put(route('provider.categories.update'), ['category_ids' => [$old[0], $new]])
        ->assertSessionHasNoErrors();
    expect(savedCategoryIds($this->profile))->toBe(collect([$old[0], $new])->sort()->values()->all());
});

test('plano keitimas: naujo plano riba galioja tik jam prasidėjus', function () {
    $small = SubscriptionPlan::factory()->create(['name' => 'Startas', 'features' => ['max_categories' => 3]]);
    $large = SubscriptionPlan::factory()->create(['name' => 'Profesionalas', 'features' => ['max_categories' => 6]]);
    $current = Subscription::factory()->for($this->profile)->for($small, 'plan')->cancelled()->create(['ends_at' => now()->addDays(5)]);
    Subscription::factory()->for($this->profile)->for($large, 'plan')->create([
        'starts_at' => $current->ends_at,
        'ends_at' => $current->ends_at->addMonth(),
    ]);

    $this->actingAs(freshProviderUser($this->profile))
        ->put(route('provider.categories.update'), ['category_ids' => leafIds(6)])
        ->assertSessionHasErrors('category_ids');

    $this->travel(6)->days();

    $this->actingAs(freshProviderUser($this->profile))->get(route('provider.categories.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('categoryLimit', ['max' => 6, 'plan' => 'Profesionalas', 'can_upgrade' => false]));

    $this->actingAs(freshProviderUser($this->profile))
        ->put(route('provider.categories.update'), ['category_ids' => leafIds(6)])
        ->assertSessionHasNoErrors();
});

test('nemokama riba keičiama per config, o planas niekada neduoda mažiau už ją', function () {
    config(['marketplace.free_max_categories' => 5]);
    $plan = SubscriptionPlan::factory()->create(['features' => ['max_categories' => 3, 'badge' => true]]);
    Subscription::factory()->for($this->profile)->for($plan, 'plan')->create();

    $this->actingAs(freshProviderUser($this->profile))->get(route('provider.categories.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('categoryLimit.max', 5));
});
