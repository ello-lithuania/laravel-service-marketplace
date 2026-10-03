<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\City;
use App\Models\CreditPackage;
use App\Models\Region;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('10 apskričių ir 60 savivaldybių su vietininkais, didmiesčiai sąrašo viršuje', function () {
    expect(Region::query()->count())->toBe(10)
        ->and(City::query()->count())->toBe(60)
        ->and(City::query()->where('name_locative', '')->count())->toBe(0)
        ->and(City::query()->orderBy('sort_order')->value('name'))->toBe('Vilnius')
        ->and(City::query()->where('slug', 'vilniaus-r')->value('name_locative'))->toBe('Vilniaus rajone');
});

test('kategorijų medis: 3 lygiai, lapai – 3 lygyje, kiekvienas 1 ir 2 lygis turi vaikų', function () {
    $roots = Category::query()->roots()->with('children.children')->get();

    expect($roots)->toHaveCount(12);

    foreach ($roots as $root) {
        expect($root->depth)->toBe(1)->and($root->icon)->not->toBeNull()->and($root->children)->not->toBeEmpty();

        foreach ($root->children as $child) {
            expect($child->depth)->toBe(2)->and($child->children)->not->toBeEmpty();

            foreach ($child->children as $leaf) {
                expect($leaf->depth)->toBe(3)
                    ->and($leaf->isLeaf())->toBeTrue()
                    ->and($leaf->offer_cost_credits)->toBeGreaterThanOrEqual(1);
            }
        }
    }

    // Kainos paveldėjimas: „Namų statyba" (2 lygis, cost 3) → jos lapai kainuoja 3 kreditus
    expect(Category::query()->where('slug', 'karkasiniu-namu-statyba')->value('offer_cost_credits'))->toBe(3);
});

test('kreditų paketai, planai ir administratoriai', function () {
    expect(CreditPackage::query()->count())->toBe(4)
        ->and(SubscriptionPlan::query()->orderBy('sort_order')->pluck('slug')->all())->toBe(['startas', 'profesionalas', 'verslas'])
        ->and(User::query()->where('role', UserRole::Admin)->count())->toBe(3)
        ->and(User::query()->where('email', 'admin1@example.test')->first()?->isAdmin())->toBeTrue();
});

test('žinyninių duomenų seeder\'iai idempotentiški – antras paleidimas nedubliuoja', function () {
    $this->seed(DatabaseSeeder::class);

    expect(City::query()->count())->toBe(60)
        ->and(Category::query()->count())->toBe(251)
        ->and(User::query()->count())->toBe(3);
});
