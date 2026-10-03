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

test('ne lokalioje aplinkoje paprastas vartotojas į admin panelę neįleidžiamas', function () {
    // Kol User neturi canAccessPanel() (Etapas 3), Filament įleidžia tik lokalioje aplinkoje.
    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertForbidden();
});
