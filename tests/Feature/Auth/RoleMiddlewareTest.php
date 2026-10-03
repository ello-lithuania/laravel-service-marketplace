<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'auth', 'role:provider'])->get('/_test/provider-only', fn () => 'ok');
    Route::middleware(['web', 'auth', 'role:client,provider'])->get('/_test/members', fn () => 'ok');
});

test('role middleware įleidžia tik nurodytas roles', function () {
    $this->actingAs(User::factory()->provider()->create())->get('/_test/provider-only')->assertOk();
    $this->actingAs(User::factory()->create())->get('/_test/provider-only')->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get('/_test/provider-only')->assertForbidden();
});

test('role middleware priima kelias roles', function () {
    $this->actingAs(User::factory()->create())->get('/_test/members')->assertOk();
    $this->actingAs(User::factory()->provider()->create())->get('/_test/members')->assertOk();
    $this->actingAs(User::factory()->admin()->create())->get('/_test/members')->assertForbidden();
});

test('svečias nukreipiamas prisijungti', function () {
    $this->get('/_test/provider-only')->assertRedirect('/login');
});
