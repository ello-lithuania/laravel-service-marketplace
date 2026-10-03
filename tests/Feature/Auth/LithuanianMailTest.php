<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

/**
 * El. pašto patvirtinimo ir slaptažodžio atkūrimo laiškai – lietuviškai (lang/lt.json).
 */
test('po registracijos išsiunčiamas lietuviškas el. pašto patvirtinimo laiškas', function () {
    Notification::fake();

    $this->post(route('register.store'), [
        'role' => 'client',
        'first_name' => 'Jonas',
        'last_name' => 'Petraitis',
        'email' => 'jonas@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::firstWhere('email', 'jonas@example.com');

    Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user): bool {
        $mail = $notification->toMail($user);

        return $mail->subject === 'Patvirtinkite savo el. pašto adresą'
            && $mail->actionText === 'Patvirtinti el. pašto adresą'
            && in_array('Paspauskite mygtuką žemiau ir patvirtinkite savo el. pašto adresą.', $mail->introLines, true);
    });
});

test('slaptažodžio atkūrimo laiškas – lietuviškai', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertSessionHas('status', 'Išsiuntėme nuorodą slaptažodžiui atkurti. Patikrinkite el. paštą.');

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
        $mail = $notification->toMail($user);

        return $mail->subject === 'Slaptažodžio atkūrimas'
            && $mail->actionText === 'Nustatyti naują slaptažodį'
            && in_array('Ši nuoroda galios 60 min.', $mail->outroLines, true);
    });
});

test('laiško šablono tekstai (pasisveikinimas, poraštė) – lietuviškai', function () {
    $user = User::factory()->create(['first_name' => 'Ona']);

    $html = (string) (new VerifyEmail)->toMail($user)->render();

    expect($html)
        ->toContain('Sveiki!')
        ->toContain('Pagarbiai,')
        ->toContain('Jei mygtukas „Patvirtinti el. pašto adresą“ neveikia')
        ->toContain(config('app.name'));
});
