<?php

use App\Enums\PriceUnit;
use App\Enums\ProviderStatus;
use App\Enums\ProviderType;
use App\Models\Category;
use App\Models\City;
use App\Models\ProviderProfile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Teikėjo profilio vedlys: duomenys → kategorijos → zonos → kainos (routes/account.php).
 */
function wizardDetails(array $overrides = []): array
{
    return [
        'type' => 'individual',
        'display_name' => 'Jonas Šimkus',
        'company_code' => null,
        'vat_code' => null,
        'city_id' => City::factory()->create()->id,
        'years_experience' => 10,
        'headline' => 'Plytelių klijavimas Vilniuje',
        'description' => 'Dirbu kruopščiai.',
        'website' => 'www.simkus.lt',
        'phone' => '8 612 34567',
        ...$overrides,
    ];
}

/**
 * Teikėjas su nebaigtu (pending) profiliu – be kategorijų ir zonų.
 */
function pendingProvider(): User
{
    return ProviderProfile::factory()->pending()->create()->user;
}

// --- Prieiga -------------------------------------------------------------------------

test('svečias nukreipiamas prisijungti', function () {
    $this->get(route('provider.wizard'))->assertRedirect(route('login'));
});

test('klientas ir administratorius vedlio neatidaro (role middleware)', function (User $user) {
    $this->actingAs($user)
        ->get(route('provider.details.edit'))
        ->assertForbidden();
})->with([
    // Closure – vartotojas sukuriamas tik paleidus testą (kai DB jau paruošta)
    'klientas' => fn () => User::factory()->create(),
    'administratorius' => fn () => User::factory()->admin()->create(),
]);

test('nepatvirtinęs el. pašto teikėjas pirmiausia nukreipiamas patvirtinti', function () {
    $this->actingAs(User::factory()->provider()->unverified()->create())
        ->get(route('provider.details.edit'))
        ->assertRedirect(route('verification.notice'));
});

// --- Vedlio „įėjimas" ------------------------------------------------------------------

test('vedlys tęsiamas nuo pirmo neatlikto privalomo žingsnio', function () {
    $user = User::factory()->provider()->create();
    $this->actingAs($user)->get(route('provider.wizard'))->assertRedirect(route('provider.details.edit'));

    $user = pendingProvider();
    $this->actingAs($user)->get(route('provider.wizard'))->assertRedirect(route('provider.categories.edit'));

    $user->providerProfile->categories()->attach(Category::factory()->create());
    $this->actingAs($user)->get(route('provider.wizard'))->assertRedirect(route('provider.areas.edit'));

    // Viskas užpildyta – vedlys tampa redagavimu nuo pradžių
    $user->providerProfile->update(['serves_whole_country' => true]);
    $this->actingAs($user)->get(route('provider.wizard'))->assertRedirect(route('provider.details.edit'));
});

test('be profilio kiti žingsniai nukreipia į duomenis', function (string $route) {
    $this->actingAs(User::factory()->provider()->create())
        ->get(route($route))
        ->assertRedirect(route('provider.details.edit'));
})->with([
    'provider.categories.edit', 'provider.areas.edit', 'provider.prices.edit',
]);

// --- 1 žingsnis: duomenys ----------------------------------------------------------------

test('duomenų žingsnis rodomas su pasiūlytu pavadinimu ir progresu', function () {
    $user = User::factory()->provider()->create(['first_name' => 'Jonas', 'last_name' => 'Šimkus']);

    $this->actingAs($user)->get(route('provider.details.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('account/profile/Details')
            ->where('profile', null)
            ->where('suggestedName', 'Jonas Šimkus')
            ->has('wizard', 4)
            ->where('wizard.0.key', 'details')
            ->where('wizard.0.done', false)
            ->where('wizard.1.available', false)
            ->has('types', 2)
            ->has('regions'));
});

test('išsaugojus duomenis sukuriamas nebaigtas profilis su unikaliu adresu', function () {
    $user = User::factory()->provider()->create();

    $this->actingAs($user)
        ->put(route('provider.details.update'), wizardDetails())
        ->assertRedirect(route('provider.categories.edit'));

    $profile = $user->fresh()->providerProfile;

    expect($profile->status)->toBe(ProviderStatus::Pending)
        ->and($profile->type)->toBe(ProviderType::Individual)
        ->and($profile->slug)->toBe('jonas-simkus')
        ->and($profile->website)->toBe('https://www.simkus.lt')
        // Telefonas saugomas vartotojui, sutvarkytas į tarptautinį formatą
        ->and($user->fresh()->phone)->toBe('+37061234567');
});

test('vienodi pavadinimai gauna skirtingus adresus, o pervadinus adresas nesikeičia', function () {
    ProviderProfile::factory()->create(['slug' => 'jonas-simkus']);
    $user = User::factory()->provider()->create();

    $this->actingAs($user)->put(route('provider.details.update'), wizardDetails());
    expect($user->fresh()->providerProfile->slug)->toBe('jonas-simkus-2');

    $this->actingAs($user)->put(route('provider.details.update'), wizardDetails(['display_name' => 'Kitas Vardas']));
    expect($user->fresh()->providerProfile)
        ->display_name->toBe('Kitas Vardas')
        ->slug->toBe('jonas-simkus-2');
});

test('įmonei įmonės kodas privalomas, o fiziniam asmeniui jis išvalomas', function () {
    $user = User::factory()->provider()->create();

    $this->actingAs($user)
        ->put(route('provider.details.update'), wizardDetails(['type' => 'company']))
        ->assertSessionHasErrors(['company_code' => 'Įmonei įmonės kodas privalomas.']);

    $this->actingAs($user)
        ->put(route('provider.details.update'), wizardDetails(['type' => 'company', 'company_code' => '123 456 789', 'vat_code' => 'lt123456789']))
        ->assertSessionHasNoErrors();

    expect($user->fresh()->providerProfile)
        ->company_code->toBe('123456789')
        ->vat_code->toBe('LT123456789');

    $this->actingAs($user)->put(route('provider.details.update'), wizardDetails(['company_code' => '123456789']));
    expect($user->fresh()->providerProfile->company_code)->toBeNull();
});

test('duomenų validacija lietuviškai', function () {
    $this->actingAs(User::factory()->provider()->create())
        ->put(route('provider.details.update'), wizardDetails([
            'display_name' => '',
            'phone' => '12345',
            'vat_code' => 'XX1',
            'city_id' => 999999,
        ]))
        ->assertSessionHasErrors([
            'display_name' => 'Privaloma užpildyti lauką rodomas pavadinimas.',
            'phone' => 'Įveskite Lietuvos telefono numerį, pvz. +370 612 34567 arba 8 612 34567.',
            'vat_code',
            'city_id',
        ]);
});

// --- 2 žingsnis: kategorijos -------------------------------------------------------------

test('kategorijos išsaugomos, o kartu su tėvu atsiųsti vaikai praleidžiami', function () {
    $user = pendingProvider();
    $group = Category::factory()->childOf(Category::factory()->create())->create();
    $leaf = Category::factory()->childOf($group)->create();
    $otherLeaf = Category::factory()->leaf()->create();

    $this->actingAs($user)->get(route('provider.categories.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('account/profile/Categories')
            ->has('tree', 2) // du 1 lygio mazgai, vaikai – jų viduje
            ->has('tree.0.children.0.children', 1)
            ->where('selected', []));

    $this->actingAs($user)
        ->put(route('provider.categories.update'), ['category_ids' => [$group->id, $leaf->id, $otherLeaf->id]])
        ->assertRedirect(route('provider.areas.edit'));

    expect($user->providerProfile->categories()->pluck('categories.id')->sort()->values()->all())
        ->toBe(collect([$group->id, $otherLeaf->id])->sort()->values()->all());
});

test('bent viena kategorija privaloma, išjungtos rinktis negalima', function () {
    $user = pendingProvider();

    $this->actingAs($user)
        ->put(route('provider.categories.update'), ['category_ids' => []])
        ->assertSessionHasErrors(['category_ids' => 'Pasirinkite bent vieną kategoriją.']);

    $this->actingAs($user)
        ->put(route('provider.categories.update'), ['category_ids' => [Category::factory()->inactive()->create()->id]])
        ->assertSessionHasErrors('category_ids.0');
});

test('perrenkant kategorijas jau įvestos kainos neprarandamos', function () {
    $user = pendingProvider();
    $kept = Category::factory()->create();
    $user->providerProfile->categories()->attach($kept, ['price_from_cents' => 2000, 'price_unit' => 'hour']);

    $this->actingAs($user)->put(route('provider.categories.update'), [
        'category_ids' => [$kept->id, Category::factory()->create()->id],
    ]);

    expect($user->providerProfile->categories()->whereKey($kept->id)->first()->pivot->price_from_cents)->toBe(2000);
});

// --- 3 žingsnis: zonos ------------------------------------------------------------------

test('pirmą kartą pasiūlomas bazinis miestas', function () {
    $user = pendingProvider();

    $this->actingAs($user)->get(route('provider.areas.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('account/profile/Areas')
            ->where('selected', [$user->providerProfile->city_id])
            ->where('servesWholeCountry', false));
});

test('savivaldybės išsaugomos; „visa Lietuva" zonų eilučių nekuria', function () {
    $user = pendingProvider();
    $cities = City::factory()->count(2)->create();

    $this->actingAs($user)
        ->put(route('provider.areas.update'), ['serves_whole_country' => false, 'city_ids' => $cities->pluck('id')->all()])
        ->assertRedirect(route('provider.prices.edit'));
    expect($user->providerProfile->serviceAreas()->count())->toBe(2);

    $this->actingAs($user)
        ->put(route('provider.areas.update'), ['serves_whole_country' => true, 'city_ids' => $cities->pluck('id')->all()]);

    $profile = $user->providerProfile->fresh();
    expect($profile->serves_whole_country)->toBeTrue()
        ->and($profile->serviceAreas()->count())->toBe(0);
});

test('be „visos Lietuvos" reikia bent vienos savivaldybės', function () {
    $this->actingAs(pendingProvider())
        ->put(route('provider.areas.update'), ['serves_whole_country' => false, 'city_ids' => []])
        ->assertSessionHasErrors('city_ids');
});

// --- Būsena pending → active ------------------------------------------------------------

test('užpildžius privalomus žingsnius profilis aktyvuojamas automatiškai', function () {
    $user = pendingProvider();
    $user->providerProfile->categories()->attach(Category::factory()->create());

    $this->actingAs($user)
        ->put(route('provider.areas.update'), ['serves_whole_country' => true])
        ->assertSessionHas('inertia.flash_data.toast', fn (array $toast) => str_contains($toast['message'], 'aktyvuotas'));

    expect($user->providerProfile->fresh()->status)->toBe(ProviderStatus::Active);
});

test('paslėpto ar užblokuoto profilio vedlys neaktyvuoja', function (string $state, ProviderStatus $status) {
    $profile = ProviderProfile::factory()->{$state}()->create();
    $profile->categories()->attach(Category::factory()->create());

    $this->actingAs($profile->user)->put(route('provider.areas.update'), ['serves_whole_country' => true]);

    expect($profile->fresh()->status)->toBe($status);
})->with([
    'hidden' => ['hidden', ProviderStatus::Hidden],
    'suspended' => ['suspended', ProviderStatus::Suspended],
]);

// --- 4 žingsnis: kainos -----------------------------------------------------------------

test('kainos įvedamos eurais ir saugomos centais', function () {
    $user = pendingProvider();
    $tile = Category::factory()->create(['name' => 'Plytelės']);
    $paint = Category::factory()->create(['name' => 'Dažymas']);
    $user->providerProfile->categories()->attach([$tile->id, $paint->id]);

    $this->actingAs($user)->get(route('provider.prices.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('account/profile/Prices')
            ->has('categories', 2)
            ->has('units', count(PriceUnit::cases())));

    $this->actingAs($user)
        ->put(route('provider.prices.update'), ['prices' => [
            ['category_id' => $tile->id, 'price_from' => '15,50', 'price_unit' => 'm2'],
            ['category_id' => $paint->id, 'price_from' => null, 'price_unit' => 'hour'],
        ]])
        ->assertRedirect(route('dashboard'));

    $pivots = $user->providerProfile->categories()->get()->keyBy('id');
    expect($pivots[$tile->id]->pivot->price_from_cents)->toBe(1550)
        ->and($pivots[$tile->id]->pivot->price_unit)->toBe('m2')
        // Be kainos vienetas nesaugomas
        ->and($pivots[$paint->id]->pivot->price_from_cents)->toBeNull()
        ->and($pivots[$paint->id]->pivot->price_unit)->toBeNull();

    // Formoje kaina rodoma eurais su lietuvišku kableliu
    $this->actingAs($user)->get(route('provider.prices.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('categories.1.price_from', '15,50'));
});

test('kainą galima nurodyti tik savo kategorijai ir tik su vienetu', function () {
    $user = pendingProvider();
    $own = Category::factory()->create();
    $user->providerProfile->categories()->attach($own);

    $this->actingAs($user)
        ->put(route('provider.prices.update'), ['prices' => [
            ['category_id' => Category::factory()->create()->id, 'price_from' => '10', 'price_unit' => 'hour'],
            ['category_id' => $own->id, 'price_from' => '10', 'price_unit' => null],
        ]])
        ->assertSessionHasErrors(['prices.0.category_id', 'prices.1.price_unit']);

    $this->actingAs($user)
        ->put(route('provider.prices.update'), ['prices' => [
            ['category_id' => $own->id, 'price_from' => '10.999', 'price_unit' => 'hour'],
        ]])
        ->assertSessionHasErrors('prices.0.price_from');
});

test('vedlio progresas atitinka tikrus duomenis', function () {
    $user = pendingProvider();
    $user->providerProfile->categories()->attach(Category::factory()->create(), ['price_from_cents' => 1000, 'price_unit' => 'job']);

    $this->actingAs($user)->get(route('provider.details.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('wizard.0.done', true)
            ->where('wizard.1.done', true)
            ->where('wizard.2.done', false)
            ->where('wizard.3.done', true)
            ->where('wizard.3.required', false));
});
