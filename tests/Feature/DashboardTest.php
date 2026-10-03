<?php

use App\Models\Category;
use App\Models\PortfolioItem;
use App\Models\ProviderProfile;
use App\Models\ServiceRequest;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('klientas mato savo skydelį su užklausų skaičiumi', function () {
    $client = User::factory()->create();
    ServiceRequest::factory()->for($client, 'client')->count(2)->create();

    $this->actingAs($client)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('client.service_requests_count', 2)
            ->where('provider', null));
});

test('teikėjas be profilio mato kvietimą pradėti vedlį', function () {
    $this->actingAs(User::factory()->provider()->create())->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('client', null)
            ->where('provider.status', null)
            ->where('provider.checklist.percent', 0)
            ->where('provider.wizard_url', route('provider.wizard', absolute: false))
            // Be profilio į kitus žingsnius nuorodų nėra
            ->where('provider.checklist.items.1.href', null));
});

test('teikėjas mato profilio būseną, pilnumą ir kreditus', function () {
    $profile = ProviderProfile::factory()->wholeCountry()->withCredits(25)->create();
    $profile->categories()->attach(Category::factory()->create());
    PortfolioItem::factory()->for($profile)->create();

    // Atlikta: duomenys, kategorijos, zonos, darbai = 4 iš 6 (kainų ir logotipo nėra)
    $this->actingAs($profile->user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('provider.status', 'active')
            ->where('provider.status_label', 'Aktyvus')
            ->where('provider.credits_balance', 25)
            ->where('provider.portfolio_count', 1)
            ->where('provider.checklist.percent', 66)
            ->has('provider.checklist.items', 6));
});

test('administratoriaus skydelyje rolės blokų nėra', function () {
    $this->actingAs(User::factory()->admin()->create())->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('client', null)
            ->where('provider', null)
            ->where('auth.user.role', 'admin'));
});
