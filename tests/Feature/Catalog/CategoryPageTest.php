<?php

use App\Models\Category;
use App\Models\City;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Kategorijų puslapiai: /paslaugos ir /paslaugos/{kategorija} (visi 3 lygiai).
 */
test('visų paslaugų puslapyje – visas aktyvus medis', function () {
    [$root, $group, $leaf] = categoryBranch();
    Category::factory()->inactive()->create();

    $this->get(route('categories.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/categories/Index')
            ->has('categories', 1)
            ->where('categories.0.slug', $root->slug)
            ->where('categories.0.children.0.slug', $group->slug)
            ->where('categories.0.children.0.children.0.slug', $leaf->slug)
            ->where('seo.title', 'Visos paslaugos'));
});

test('1 lygio puslapyje – 2 lygio kategorijos su 3 lygio vaikais ir visų jų teikėjai', function () {
    [$root, $group, $leaf] = categoryBranch();
    $provider = catalogProvider([$leaf]);

    $this->get(route('categories.show', $root))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/categories/Show')
            ->where('category.slug', $root->slug)
            ->where('category.depth', 1)
            ->where('breadcrumbs', [])
            ->has('subcategories', 1)
            ->where('subcategories.0.slug', $group->slug)
            ->where('subcategories.0.children.0.slug', $leaf->slug)
            ->where('city', null)
            ->has('providers.data', 1)
            ->where('providers.data.0.slug', $provider->slug)
            ->where('providers.meta.total', 1));
});

test('3 lygio puslapyje – „duonos trupiniai", tos pačios grupės paslaugos ir kaina „nuo"', function () {
    [$root, $group, $leaf] = categoryBranch();
    $sibling = Category::factory()->childOf($group)->create(['sort_order' => 1]);
    catalogProvider([$group]);

    $this->get(route('categories.show', $leaf))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('breadcrumbs.0.slug', $root->slug)
            ->where('breadcrumbs.1.slug', $group->slug)
            ->has('subcategories', 2)
            ->where('subcategories.1.slug', $sibling->slug)
            ->where('providers.data.0.price_from', ['cents' => 1500, 'unit' => 'val.'])
            // 2–3 lygiai neturi savo ikonos – paveldi 1 lygio
            ->where('category.icon', $root->icon));
});

test('teikėjo kortelėje – tik vieši laukai', function () {
    [, , $leaf] = categoryBranch();
    $provider = catalogProvider([$leaf], attributes: ['company_code' => '999123456', 'credits_balance' => 77]);

    $response = $this->get(route('categories.show', $leaf))->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->has('providers.data.0', fn (Assert $card) => $card
            ->hasAll(['id', 'slug', 'display_name', 'headline', 'logo_url', 'city', 'serves_whole_country',
                'is_verified', 'rating_avg', 'reviews_count', 'completed_jobs_count', 'years_experience',
                'categories', 'price_from', 'has_pro_badge']) // has_pro_badge – Etapas 9c
            ->missing('company_code')
            ->missing('credits_balance')
            ->missing('user_id')));

    expect($response->getContent())->not->toContain($provider->user->email)->not->toContain('999123456');
});

test('išjungta kategorija ar kategorija po išjungtu tėvu – 404', function () {
    [$root, , $leaf] = categoryBranch();
    $inactive = Category::factory()->inactive()->create();

    $this->get(route('categories.show', $inactive))->assertNotFound();

    $root->update(['is_active' => false]);
    $this->get(route('categories.show', $leaf))->assertNotFound();
    $this->get('/paslaugos/tokios-nera')->assertNotFound();
});

test('filtrai, rikiavimas ir puslapiavimas URL parametrais', function () {
    [, , $leaf] = categoryBranch();
    $verified = catalogProvider([$leaf], attributes: ['verified_at' => now(), 'rating_avg' => 4.2, 'reviews_count' => 50]);
    catalogProvider([$leaf], attributes: ['rating_avg' => 4.8, 'reviews_count' => 2]);
    catalogProvider([$leaf], attributes: ['rating_avg' => 3.1]);

    $this->get(route('categories.show', ['category' => $leaf, 'patikrinti' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.patikrinti', true)
            ->has('providers.data', 1)
            ->where('providers.data.0.slug', $verified->slug));

    $this->get(route('categories.show', ['category' => $leaf, 'reitingas' => 4, 'rikiuoti' => 'atsiliepimai']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.reitingas', 4)
            ->where('filters.rikiuoti', 'atsiliepimai')
            ->has('providers.data', 2)
            ->where('providers.data.0.slug', $verified->slug));
});

test('puslapiavimo nuorodose išlieka filtrai, canonical – be filtrų, bet su puslapiu', function () {
    [, , $leaf] = categoryBranch();
    foreach (range(1, 21) as $i) {
        catalogProvider([$leaf], attributes: ['verified_at' => now()]);
    }

    $this->get(route('categories.show', ['category' => $leaf, 'patikrinti' => 1, 'puslapis' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('providers.data', 1)
            ->where('providers.meta.current_page', 2)
            ->where('providers.meta.last_page', 2)
            ->where('providers.links.prev', fn (string $url) => str_contains($url, 'patikrinti=1') && str_contains($url, 'puslapis=1'))
            ->where('seo.canonical', route('categories.show', $leaf).'?puslapis=2')
            ->where('seo.title', fn (string $title) => str_ends_with($title, '– 2 puslapis')));
});

test('neteisingi URL parametrai ignoruojami, o ne grąžina klaidą', function () {
    [, , $leaf] = categoryBranch();
    catalogProvider([$leaf]);

    $this->get(route('categories.show', ['category' => $leaf, 'rikiuoti' => 'bet-kas', 'reitingas' => 99, 'patikrinti' => 'gal']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.rikiuoti', 'reitingas')
            ->where('filters.reitingas', null)
            ->where('filters.patikrinti', false)
            ->has('providers.data', 1));
});

test('?miestas= nukreipia į SEO adresą /paslaugos/{kategorija}/{miestas}', function () {
    [, , $leaf] = categoryBranch();
    $city = City::factory()->create();

    $this->get(route('categories.show', ['category' => $leaf, 'miestas' => $city->slug, 'patikrinti' => 1, 'puslapis' => 3]))
        ->assertRedirect(route('categories.city', ['category' => $leaf->slug, 'city' => $city->slug, 'patikrinti' => 1]));
});

test('SEO: meta_title ir meta_description, jei įrašyti, kitaip – sugeneruoti', function () {
    [, , $leaf] = categoryBranch(['name' => 'Plytelių klijavimas', 'slug' => 'plyteliu-klijavimas']);
    [, , $custom] = categoryBranch(['meta_title' => 'Plytelių klijuotojai – kainos', 'meta_description' => 'Savas aprašymas.']);

    $this->get(route('categories.show', $leaf))
        ->assertInertia(fn (Assert $page) => $page
            ->where('seo.title', 'Plytelių klijavimas: meistrai, kainos ir atsiliepimai')
            ->where('seo.description', fn (string $text) => str_starts_with($text, 'Plytelių klijavimas: palyginkite'))
            ->where('seo.canonical', url('/paslaugos/plyteliu-klijavimas'))
            ->where('seo.robots', 'index, follow'));

    $this->get(route('categories.show', $custom))
        ->assertInertia(fn (Assert $page) => $page
            ->where('seo.title', 'Plytelių klijuotojai – kainos')
            ->where('seo.description', 'Savas aprašymas.'));
});

test('SEO žymos išvedamos jau serverio HTML (be JavaScript)', function () {
    [, , $leaf] = categoryBranch(['name' => 'Plytelių klijavimas', 'slug' => 'plyteliu-klijavimas']);

    $this->get(route('categories.show', $leaf))
        ->assertSee('<title>Plytelių klijavimas: meistrai, kainos ir atsiliepimai - '.config('app.name').'</title>', false)
        ->assertSee('<link rel="canonical" href="'.url('/paslaugos/plyteliu-klijavimas').'" data-inertia="canonical">', false)
        ->assertSee('<meta name="description" content="Plytelių klijavimas: palyginkite', false);
});
