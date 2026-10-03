<?php

use App\Enums\ProviderStatus;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

test('sąrašas: paieška pagal vardą ir el. paštą, filtrai, skirtukai', function () {
    $jonas = User::factory()->create(['first_name' => 'Jonas', 'last_name' => 'Petraitis', 'email' => 'jonas@example.test']);
    $provider = User::factory()->provider()->create(['first_name' => 'Ona']);
    $banned = User::factory()->banned()->create();

    $this->get('/admin/vartotojai')->assertOk()->assertSee('Vartotojai');

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$jonas, $provider, $banned])
        ->searchTable('Petraitis')
        ->assertCanSeeTableRecords([$jonas])
        ->assertCanNotSeeTableRecords([$provider])
        ->searchTable('jonas@example')
        ->assertCanSeeTableRecords([$jonas])
        ->searchTable(null)
        ->filterTable('role', 'provider')
        ->assertCanSeeTableRecords([$provider])
        ->assertCanNotSeeTableRecords([$jonas])
        ->resetTableFilters()
        ->set('activeTab', 'banned')
        ->assertCanSeeTableRecords([$banned])
        ->assertCanNotSeeTableRecords([$jonas]);
});

test('ištrinti (anonimizuoti) vartotojai matomi tik su filtru', function () {
    $deleted = User::factory()->create();
    $deleted->delete();

    Livewire::test(ListUsers::class)
        ->assertCanNotSeeTableRecords([$deleted])
        ->filterTable('trashed', true)
        ->assertCanSeeTableRecords([$deleted]);

    $this->get("/admin/vartotojai/{$deleted->id}")->assertOk()->assertSee('Ištrintas (anonimizuotas)');
});

test('peržiūra rodo paskyrą, veiklą ir teikėjo profilį', function () {
    $profile = ProviderProfile::factory()->create(['display_name' => 'Plytelių meistras UAB']);

    $this->get("/admin/vartotojai/{$profile->user_id}")
        ->assertOk()
        ->assertSee($profile->user->email)
        ->assertSee('Veikla')
        ->assertSee('Plytelių meistras UAB');
});

test('užblokavimas su priežastimi; teikėjo profilis tampa suspended', function () {
    $profile = ProviderProfile::factory()->create();
    $user = $profile->user;

    Livewire::test(ViewUser::class, ['record' => $user->getRouteKey()])
        ->callAction('ban', ['reason' => ''])
        ->assertHasActionErrors(['reason' => 'required']);

    Livewire::test(ViewUser::class, ['record' => $user->getRouteKey()])
        ->callAction('ban', ['reason' => 'Šlamštas žinutėse', 'withdraw_offers' => true])
        ->assertHasNoActionErrors()
        ->assertNotified('Vartotojas užblokuotas');

    expect($user->refresh())
        ->banned_at->not->toBeNull()
        ->ban_reason->toBe('Šlamštas žinutėse')
        ->and($profile->refresh()->status)->toBe(ProviderStatus::Suspended);
});

test('atblokavimas iš sąrašo atkuria profilį', function () {
    $profile = ProviderProfile::factory()->suspended()->wholeCountry()->create();
    $profile->categories()->attach(Category::factory()->leaf()->create());
    $profile->user->forceFill(['banned_at' => now(), 'ban_reason' => 'x'])->save();

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('unban')->table($profile->user), ['restore_profile' => true])
        ->assertHasNoActionErrors();

    expect($profile->user->refresh()->banned_at)->toBeNull()
        ->and($profile->refresh()->status)->toBe(ProviderStatus::Active);
});

test('administratoriaus užblokuoti negalima, o neužblokuoto – atblokuoti', function () {
    $otherAdmin = User::factory()->admin()->create();
    $client = User::factory()->create();

    Livewire::test(ViewUser::class, ['record' => $otherAdmin->getRouteKey()])
        ->assertActionHidden('ban');

    Livewire::test(ViewUser::class, ['record' => $client->getRouteKey()])
        ->assertActionVisible('ban')
        ->assertActionHidden('unban');
});

test('ne administratorius vartotojų sąrašo nemato', function () {
    $this->actingAs(User::factory()->provider()->create())
        ->get('/admin/vartotojai')
        ->assertForbidden();
});
