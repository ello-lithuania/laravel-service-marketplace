<?php

use App\Models\PortfolioItem;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Policies: ProviderProfilePolicy ir PortfolioItemPolicy.
 * Gate::forUser($user)->allows(...) – tas pats, ką daro $user->can(...), tik be prisijungimo.
 */
test('aktyvų profilį mato visi, net svečiai', function () {
    $profile = ProviderProfile::factory()->create();

    expect(Gate::allows('view', $profile))->toBeTrue()
        ->and(Gate::forUser(User::factory()->create())->allows('view', $profile))->toBeTrue();
});

test('nebaigtą, paslėptą ar užblokuotą profilį mato tik savininkas ir administratorius', function (string $state) {
    $profile = ProviderProfile::factory()->{$state}()->create();

    expect(Gate::allows('view', $profile))->toBeFalse()
        ->and(Gate::forUser(User::factory()->create())->allows('view', $profile))->toBeFalse()
        ->and(Gate::forUser($profile->user)->allows('view', $profile))->toBeTrue()
        ->and(Gate::forUser(User::factory()->admin()->create())->allows('view', $profile))->toBeTrue();
})->with(['pending', 'hidden', 'suspended']);

test('profilį keisti gali tik jo savininkas', function () {
    $profile = ProviderProfile::factory()->create();
    $otherProvider = ProviderProfile::factory()->create()->user;

    expect(Gate::forUser($profile->user)->allows('update', $profile))->toBeTrue()
        ->and(Gate::forUser($otherProvider)->allows('update', $profile))->toBeFalse()
        ->and(Gate::forUser(User::factory()->create())->allows('update', $profile))->toBeFalse();
});

test('profilį susikurti gali tik teikėjas, ir tik vieną', function () {
    $withoutProfile = User::factory()->provider()->create();
    $withProfile = ProviderProfile::factory()->create()->user;

    expect(Gate::forUser($withoutProfile)->allows('create', ProviderProfile::class))->toBeTrue()
        ->and(Gate::forUser($withProfile)->allows('create', ProviderProfile::class))->toBeFalse()
        ->and(Gate::forUser(User::factory()->create())->allows('create', ProviderProfile::class))->toBeFalse();
});

test('atliktus darbus tvarko tik jų savininkas', function () {
    $item = PortfolioItem::factory()->create();
    $owner = $item->providerProfile->user;
    $otherProvider = ProviderProfile::factory()->create()->user;

    expect(Gate::forUser($owner)->allows('update', $item))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('delete', $item))->toBeTrue()
        ->and(Gate::forUser($otherProvider)->allows('update', $item))->toBeFalse()
        ->and(Gate::forUser($otherProvider)->allows('delete', $item))->toBeFalse()
        ->and(Gate::forUser(User::factory()->admin()->create())->allows('update', $item))->toBeFalse();
});

test('kurti darbus gali tik teikėjas, jau turintis profilį', function () {
    expect(Gate::forUser(ProviderProfile::factory()->create()->user)->allows('create', PortfolioItem::class))->toBeTrue()
        ->and(Gate::forUser(User::factory()->provider()->create())->allows('create', PortfolioItem::class))->toBeFalse()
        ->and(Gate::forUser(User::factory()->create())->allows('create', PortfolioItem::class))->toBeFalse();
});
