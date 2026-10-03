<?php

use Illuminate\Support\Carbon;

test('aplikacijos kalba yra lietuvių', function () {
    expect(app()->getLocale())->toBe('lt');
});

test('validacijos klaidos rodomos lietuviškai su lietuviškais laukų pavadinimais', function () {
    $this->post(route('register.store'), [
        'first_name' => 'Jonas',
        'last_name' => 'Petraitis',
        'email' => 'ne-el-pastas',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors([
        'email' => 'Lauko el. paštas reikšmė turi būti galiojantis el. pašto adresas.',
    ]);
});

test('laiškų tekstai išversti į lietuvių kalbą', function () {
    expect(__('Verify Email Address'))->toBe('Patvirtinti el. pašto adresą');
});

test('datos rodomos lietuviškai', function () {
    Carbon::setTestNow('2026-10-03 12:00:00');

    expect(now()->subMinutes(5)->diffForHumans())->toBe('prieš 5 minutes')
        ->and(now()->translatedFormat('F'))->toBe('spalis')
        ->and(now()->translatedFormat('F j'))->toBe('spalio 3');
});
