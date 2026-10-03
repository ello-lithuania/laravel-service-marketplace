<?php

use App\Models\Category;
use App\Models\City;
use App\Models\PortfolioItem;
use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Viešas teikėjo profilis /meistrai/{slug}.
 */
test('profilyje – aprašymas, paslaugos su kainomis, zonos, portfolio ir įvertinimas', function () {
    [$root, , $leaf] = categoryBranch();
    $zone = City::factory()->create(['name' => 'Kaunas']);
    $provider = catalogProvider([$leaf], [$zone], [
        'type' => 'company',
        'display_name' => 'UAB Plytelė',
        'description' => 'Klijuojame plyteles.',
        'website' => 'https://plytele.example.test',
        'years_experience' => 12,
        'verified_at' => now(),
        'rating_avg' => 4.5,
        'reviews_count' => 2,
    ]);
    PortfolioItem::factory()->for($provider)->create(['title' => 'Vonios plytelės', 'category_id' => $leaf->id]);

    $this->get(route('providers.show', $provider))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/providers/Show')
            ->where('provider.display_name', 'UAB Plytelė')
            ->where('provider.type', 'Įmonė')
            ->where('provider.description', 'Klijuojame plyteles.')
            ->where('provider.website', 'https://plytele.example.test')
            ->where('provider.is_verified', true)
            ->where('provider.years_experience', 12)
            ->where('provider.rating_avg', 4.5)
            ->where('provider.serves_whole_country', false)
            ->where('provider.service_areas', [['name' => 'Kaunas', 'slug' => $zone->slug]])
            ->where('provider.services', [[
                'name' => $leaf->name, 'slug' => $leaf->slug, 'price_from_cents' => 1500, 'price_unit' => 'val.',
            ]])
            ->has('portfolio', 1, fn (Assert $item) => $item
                ->where('title', 'Vonios plytelės')
                ->where('category', $leaf->name)
                ->where('images', [])
                ->etc())
            ->where('mainCategory.slug', $root->slug)
            ->where('seo.title', 'UAB Plytelė – '.$provider->headline));
});

test('visoje Lietuvoje dirbančiam teikėjui zonų sąrašas nesiunčiamas', function () {
    $provider = catalogProvider(attributes: ['serves_whole_country' => true]);

    $this->get(route('providers.show', $provider))
        ->assertInertia(fn (Assert $page) => $page
            ->where('provider.serves_whole_country', true)
            ->where('provider.service_areas', []));
});

test('rodomi tik paskelbti atsiliepimai su „Vardas P." ir teikėjo atsakymu', function () {
    $provider = catalogProvider();
    $author = User::factory()->create(['first_name' => 'Jonas', 'last_name' => 'Petraitis']);
    Review::factory()->for($provider)->withReply()->create(['author_id' => $author->id, 'rating' => 5]);
    Review::factory()->for($provider)->hidden()->create();
    Review::factory()->for($provider)->create(['status' => 'pending']);

    $this->get(route('providers.show', $provider))
        ->assertInertia(fn (Assert $page) => $page
            ->has('reviews.data', 1, fn (Assert $review) => $review
                ->where('author_name', 'Jonas P.')
                ->where('rating', 5)
                ->where('provider_reply', 'Ačiū už atsiliepimą!')
                ->where('is_verified', false)
                ->etc())
            ->where('ratingDistribution', [
                ['rating' => 5, 'count' => 1], ['rating' => 4, 'count' => 0], ['rating' => 3, 'count' => 0],
                ['rating' => 2, 'count' => 0], ['rating' => 1, 'count' => 0],
            ]));
});

test('atsiliepimai puslapiuojami po 10, naujausi pirmi', function () {
    $provider = catalogProvider();
    Review::factory()->for($provider)->count(10)->create(['published_at' => now()->subYear()]);
    $newest = Review::factory()->for($provider)->create(['published_at' => now()]);

    $this->get(route('providers.show', $provider))
        ->assertInertia(fn (Assert $page) => $page
            ->has('reviews.data', 10)
            ->where('reviews.data.0.id', $newest->id)
            ->where('reviews.meta.last_page', 2));

    $this->get(route('providers.show', ['providerProfile' => $provider, 'puslapis' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('reviews.data', 1));
});

test('ištrintos paskyros atsiliepimas lieka', function () {
    $provider = catalogProvider();
    $author = User::factory()->create(['first_name' => 'Ona', 'last_name' => 'Kazlauskienė']);
    Review::factory()->for($provider)->create(['author_id' => $author->id]);
    $author->delete();

    $this->get(route('providers.show', $provider))
        ->assertInertia(fn (Assert $page) => $page->where('reviews.data.0.author_name', 'Ona K.'));
});

test('neaktyvus ar ištrintas profilis – 404', function (Closure $makeProvider) {
    $provider = $makeProvider();

    $this->get('/meistrai/'.$provider->slug)->assertNotFound();
})->with([
    'laukia' => [fn () => ProviderProfile::factory()->pending()->create()],
    'paslėptas' => [fn () => ProviderProfile::factory()->hidden()->create()],
    'užblokuotas' => [fn () => ProviderProfile::factory()->suspended()->create()],
    'ištrintas' => [fn () => tap(ProviderProfile::factory()->create())->delete()],
]);

test('profilyje nėra privačių duomenų: el. pašto, telefono, įmonės kodų, kreditų', function () {
    $provider = catalogProvider(attributes: [
        'company_code' => '999111222', 'vat_code' => 'LT999111222', 'credits_balance' => 55,
    ]);
    $provider->user->forceFill(['phone' => '+37060000001'])->save();

    $response = $this->get(route('providers.show', $provider))->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->has('provider', fn (Assert $profile) => $profile
            ->missing('company_code')
            ->missing('vat_code')
            ->missing('credits_balance')
            ->missing('status')
            ->missing('user')
            ->etc()));

    expect($response->getContent())
        ->not->toContain($provider->user->email)
        ->not->toContain('+37060000001')
        ->not->toContain('999111222');
});

test('svetainė rodoma tik su http(s) adresu', function () {
    $provider = catalogProvider(attributes: ['website' => 'javascript:alert(1)']);

    $this->get(route('providers.show', $provider))
        ->assertInertia(fn (Assert $page) => $page->where('provider.website', null));
});

test('išjungtos kategorijos paslauga rodoma be nuorodos', function () {
    $inactive = Category::factory()->inactive()->create();
    $provider = catalogProvider([$inactive]);

    $this->get(route('providers.show', $provider))
        ->assertInertia(fn (Assert $page) => $page
            ->where('provider.services.0.slug', null)
            ->where('mainCategory', null));
});
