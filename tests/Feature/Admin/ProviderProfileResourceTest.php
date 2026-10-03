<?php

use App\Enums\ProviderStatus;
use App\Filament\Resources\ProviderProfiles\Pages\ListProviderProfiles;
use App\Filament\Resources\ProviderProfiles\Pages\ViewProviderProfile;
use App\Models\Category;
use App\Models\City;
use App\Models\PortfolioItem;
use App\Models\ProviderProfile;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

/**
 * Užpildytas profilis: kategorija ir zona – tokį galima aktyvuoti.
 */
function completeProfile(ProviderStatus $status = ProviderStatus::Active): ProviderProfile
{
    $profile = ProviderProfile::factory()->create(['status' => $status]);
    $profile->categories()->attach(Category::factory()->leaf()->create());
    $profile->serviceAreas()->attach($profile->city_id);

    return $profile;
}

test('sąrašas: numatytasis skirtukas – nepatikrinti aktyvūs; filtrai ir paieška', function () {
    $unverified = ProviderProfile::factory()->create(['display_name' => 'Tvorų meistrai']);
    $verified = ProviderProfile::factory()->verified()->create();
    $hidden = ProviderProfile::factory()->hidden()->create();

    $this->get('/admin/teikejai')->assertOk()->assertSee('Teikėjai');

    Livewire::test(ListProviderProfiles::class)
        ->assertCanSeeTableRecords([$unverified])
        ->assertCanNotSeeTableRecords([$verified, $hidden])
        ->set('activeTab', 'all')
        ->assertCanSeeTableRecords([$unverified, $verified, $hidden])
        ->filterTable('status', ProviderStatus::Hidden->value)
        ->assertCanSeeTableRecords([$hidden])
        ->assertCanNotSeeTableRecords([$unverified])
        ->resetTableFilters()
        ->filterTable('verified_at', true)
        ->assertCanSeeTableRecords([$verified])
        ->assertCanNotSeeTableRecords([$unverified])
        ->resetTableFilters()
        ->searchTable('Tvorų')
        ->assertCanSeeTableRecords([$unverified])
        ->assertCanNotSeeTableRecords([$verified]);
});

test('peržiūra rodo paslaugas, zonas ir atliktus darbus', function () {
    Storage::fake('public');
    $profile = completeProfile();
    $profile->categories()->first()?->update(['name' => 'Plytelių klijavimas']);
    $item = PortfolioItem::factory()->for($profile)->create(['title' => 'Vonios plytelės']);
    $item->addMedia(UploadedFile::fake()->image('vonia.jpg', 400, 300))->toMediaCollection('images');

    $this->get("/admin/teikejai/{$profile->id}")
        ->assertOk()
        ->assertSee('Plytelių klijavimas')
        ->assertSee(City::query()->find($profile->city_id)?->name)
        ->assertSee('Vonios plytelės');
});

test('„Patikrintas" uždedamas ir nuimamas', function () {
    $profile = completeProfile();

    Livewire::test(ViewProviderProfile::class, ['record' => $profile->getRouteKey()])
        ->callAction('verify')
        ->assertNotified();
    expect($profile->refresh()->verified_at)->not->toBeNull();

    Livewire::test(ViewProviderProfile::class, ['record' => $profile->getRouteKey()])
        ->assertActionHidden('verify')
        ->callAction('unverify');
    expect($profile->refresh()->verified_at)->toBeNull();
});

test('paslėpti, užblokuoti ir vėl aktyvuoti', function () {
    $profile = completeProfile();

    Livewire::test(ListProviderProfiles::class)
        ->set('activeTab', 'all')
        ->callAction(TestAction::make('hide')->table($profile));
    expect($profile->refresh()->status)->toBe(ProviderStatus::Hidden);

    Livewire::test(ViewProviderProfile::class, ['record' => $profile->getRouteKey()])
        ->assertActionHidden('hide')
        ->callAction('suspend');
    expect($profile->refresh()->status)->toBe(ProviderStatus::Suspended);

    Livewire::test(ViewProviderProfile::class, ['record' => $profile->getRouteKey()])
        ->callAction('activate');
    expect($profile->refresh()->status)->toBe(ProviderStatus::Active);
});

test('neužpildyto ar užblokuoto savininko profilio aktyvuoti negalima', function () {
    $incomplete = ProviderProfile::factory()->suspended()->create();
    $bannedOwner = completeProfile(ProviderStatus::Suspended);
    $bannedOwner->user->forceFill(['banned_at' => now()])->save();

    Livewire::test(ViewProviderProfile::class, ['record' => $incomplete->getRouteKey()])
        ->callAction('activate')
        ->assertNotified(__('moderation.provider_status.incomplete'));
    Livewire::test(ViewProviderProfile::class, ['record' => $bannedOwner->getRouteKey()])
        ->callAction('activate')
        ->assertNotified(__('moderation.provider_status.user_banned'));

    expect($incomplete->refresh()->status)->toBe(ProviderStatus::Suspended)
        ->and($bannedOwner->refresh()->status)->toBe(ProviderStatus::Suspended);
});

test('nebaigtą (pending) profilį galima tik užblokuoti', function () {
    $pending = ProviderProfile::factory()->pending()->create();

    Livewire::test(ViewProviderProfile::class, ['record' => $pending->getRouteKey()])
        ->assertActionHidden('activate')
        ->assertActionHidden('hide')
        ->assertActionVisible('suspend');
});

test('ne administratorius teikėjų sąrašo nemato', function () {
    $this->actingAs(User::factory()->provider()->create())
        ->get('/admin/teikejai')
        ->assertForbidden();
});
