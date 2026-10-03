<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('teikėjas mato savo grupes su numatytosiomis reikšmėmis', function () {
    $this->actingAs(User::factory()->provider()->create())
        ->get('/settings/notifications')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Notifications')
            ->has('groups', 4)
            ->where('groups.0.key', 'new_requests')
            ->where('groups.0.channels', ['mail' => true, 'database' => true])
            ->where('emailVerified', true));
});

test('klientas mato kliento grupes', function () {
    $this->actingAs(User::factory()->create())
        ->get('/settings/notifications')
        ->assertInertia(fn (Assert $page) => $page
            ->where('groups.0.key', 'new_offers')
            ->where('groups.1.key', 'request_updates'));
});

test('nustatymai išsaugomi, neatsiųsti laukai ir nežinomi raktai nekeičiami', function () {
    $user = User::factory()->provider()->create();

    $this->actingAs($user)
        ->patch('/settings/notifications', [
            'settings' => [
                'new_requests' => ['mail' => false, 'database' => true],
                'nezinoma' => ['mail' => false],
            ],
        ])
        ->assertRedirect('/settings/notifications')
        ->assertInertiaFlash('toast.message', 'Pranešimų nustatymai išsaugoti.');

    $settings = $user->refresh()->notification_settings;

    expect($settings['new_requests'])->toBe(['mail' => false, 'database' => true])
        ->and($settings['offer_updates'])->toBe(['mail' => true, 'database' => true])
        ->and($settings)->not->toHaveKey('nezinoma');
});

test('reikšmės turi būti bool', function () {
    $this->actingAs(User::factory()->create())
        ->patch('/settings/notifications', ['settings' => ['new_offers' => ['mail' => 'gal']]])
        ->assertSessionHasErrors('settings.new_offers.mail');
});

test('svečias nukreipiamas prisijungti', function () {
    $this->get('/settings/notifications')->assertRedirect('/login');
});
