<?php

use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Inertia bendri props: auth.user – tik reikalingi laukai (App\Http\Resources\AuthUserResource).
 */
const AUTH_USER_KEYS = [
    'id', 'first_name', 'last_name', 'name', 'email', 'email_verified_at', 'role', 'avatar', 'provider_profile',
];

test('svečiui auth.user yra null', function () {
    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.user', null));
});

test('klientui siunčiami tik reikalingi laukai, be slaptų', function () {
    $user = User::factory()->create(['first_name' => 'Ona', 'last_name' => 'Jonaitė']);

    $this->actingAs($user)->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('auth.user', fn (Assert $authUser) => $authUser
                ->where('id', $user->id)
                ->where('first_name', 'Ona')
                ->where('last_name', 'Jonaitė')
                ->where('name', 'Ona Jonaitė')
                ->where('email', $user->email)
                ->where('role', 'client')
                ->where('avatar', null)
                ->where('provider_profile', null)
                ->has('email_verified_at')
                // etc() nenaudojam: bet koks papildomas laukas (password, ban_reason…) – testo klaida
            ));
});

test('auth.user raktai tiksliai tokie, kokių tikisi Vue tipai', function () {
    $user = User::factory()->create();

    $props = $this->actingAs($user)->get(route('home'))->viewData('page')['props'];

    expect(array_keys($props['auth']['user']))->toEqualCanonicalizing(AUTH_USER_KEYS);
});

test('teikėjui – profilio santrauka su kreditų balansu', function () {
    $profile = ProviderProfile::factory()->withCredits(12)->create(['slug' => 'jonas-meistras']);

    $this->actingAs($profile->user)->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.role', 'provider')
            ->where('auth.user.provider_profile', [
                'id' => $profile->id,
                'slug' => 'jonas-meistras',
                'status' => 'active',
                'credits_balance' => 12,
            ]));
});

test('teikėjui be profilio provider_profile yra null', function () {
    $user = User::factory()->provider()->create();

    $this->actingAs($user)->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.provider_profile', null));
});

test('avataras siunčiamas kaip miniatiūros URL', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $user->addMedia(UploadedFile::fake()->image('a.jpg', 300, 300))->toMediaCollection('avatar');

    $this->actingAs($user)->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where(
            'auth.user.avatar',
            fn (string $url) => str_contains($url, '/storage/') && str_contains($url, '-thumb.jpg'),
        ));
});
