<?php

use App\Enums\UserRole;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

/**
 * @return array<string, string>
 */
function registrationData(array $overrides = []): array
{
    return [
        'role' => 'client',
        'first_name' => 'Jonas',
        'last_name' => 'Petraitis',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        ...$overrides,
    ];
}

test('registration screen can be rendered', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/Register')->where('role', 'client'));
});

test('nuoroda „Tapti teikėju" iškart pažymi teikėjo rolę', function () {
    $this->get(route('register', ['role' => 'provider']))
        ->assertInertia(fn (Assert $page) => $page->where('role', 'provider'));

    // Bet kokia kita reikšmė (pvz. admin) – numatytoji kliento rolė
    $this->get(route('register', ['role' => 'admin']))
        ->assertInertia(fn (Assert $page) => $page->where('role', 'client'));
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), registrationData());

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::firstWhere('email', 'test@example.com');
    expect($user->role)->toBe(UserRole::Client)
        ->and($user->first_name)->toBe('Jonas')
        ->and($user->last_name)->toBe('Petraitis');
});

test('teikėjas po registracijos nukreipiamas į profilio vedlį', function () {
    $response = $this->post(route('register.store'), registrationData(['role' => 'provider']));

    $this->assertAuthenticated();
    $response->assertRedirect(route('provider.wizard', absolute: false));

    $user = User::firstWhere('email', 'test@example.com');
    // Profilis dar nesukurtas – jis atsiras vedlio 1 žingsnyje
    expect($user->role)->toBe(UserRole::Provider)
        ->and($user->providerProfile)->toBeNull();
});

test('administratoriaus rolės registruojantis „įšvirkšti" negalima', function () {
    $this->post(route('register.store'), registrationData(['role' => 'admin']))
        ->assertSessionHasErrors('role');

    $this->assertGuest();
    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
});

test('rolė privaloma', function () {
    $this->post(route('register.store'), registrationData(['role' => '']))
        ->assertSessionHasErrors(['role' => 'Pasirinkite, kaip naudosite platformą.']);

    $this->assertGuest();
});

test('vardas ir pavardė privalomi', function () {
    $this->post(route('register.store'), registrationData(['first_name' => '', 'last_name' => '']))
        ->assertSessionHasErrors(['first_name', 'last_name']);
});

test('sisteminių laukų per registracijos formą nustatyti negalima', function () {
    $this->post(route('register.store'), registrationData([
        'email_verified_at' => now()->toDateTimeString(),
        'banned_at' => now()->toDateTimeString(),
    ]));

    $user = User::firstWhere('email', 'test@example.com');
    expect($user->email_verified_at)->toBeNull()
        ->and($user->banned_at)->toBeNull();
});
