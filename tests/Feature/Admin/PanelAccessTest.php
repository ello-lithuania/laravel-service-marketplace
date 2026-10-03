<?php

use App\Models\User;

test('svečias nukreipiamas į admin panelės prisijungimą', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

test('admin panelės prisijungimo puslapis rodomas lietuviškai', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('Prisijunkite prie savo paskyros');
});

test('administratorius patenka į admin panelę', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertOk();
});

test('klientas, teikėjas, nepatvirtinęs el. pašto ar užblokuotas admin į panelę neįleidžiami', function (User $user) {
    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
})->with([
    'klientas' => fn () => User::factory()->create(),
    'teikėjas' => fn () => User::factory()->provider()->create(),
    'nepatvirtintas admin' => fn () => User::factory()->admin()->unverified()->create(),
    'užblokuotas admin' => fn () => User::factory()->admin()->banned()->create(),
]);
