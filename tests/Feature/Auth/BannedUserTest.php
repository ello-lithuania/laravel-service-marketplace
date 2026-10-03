<?php

use App\Enums\ProviderStatus;
use App\Models\ProviderProfile;
use App\Models\User;

test('užblokuotas vartotojas negali prisijungti – aiški žinutė, bet tik žinant slaptažodį', function () {
    $user = User::factory()->banned()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => __('moderation.banned_login')]);
    $this->assertGuest();

    // Neteisingas slaptažodis – įprasta klaida: blokavimo fakto neatskleidžiam
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'neteisingas'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);
    $this->assertGuest();
});

test('neužblokuotas vartotojas prisijungia kaip anksčiau', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($user);
});

test('ištrintas (anonimizuotas) vartotojas neprisijungia', function () {
    $user = User::factory()->create();
    $user->delete();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);
    $this->assertGuest();
});

test('jau prisijungęs ir užblokuotas vartotojas atjungiamas prie kito paspaudimo', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    $user->forceFill(['banned_at' => now()])->save();

    $this->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => __('moderation.banned_login')]);
    $this->assertGuest();
});

test('JSON užklausai užblokuotas vartotojas gauna 401', function () {
    $user = User::factory()->banned()->create();

    $this->actingAs($user)
        ->getJson(route('notifications.latest'))
        ->assertUnauthorized()
        ->assertJson(['message' => __('moderation.banned_login')]);
});

test('užblokuoto teikėjo (suspended) profilio nėra kataloge ir viešas profilis grąžina 404', function () {
    $profile = ProviderProfile::factory()->create(['status' => ProviderStatus::Suspended]);

    $this->get(route('providers.show', $profile->slug))->assertNotFound();
    $this->get(route('providers.index'))->assertOk()->assertDontSee($profile->display_name);
});
