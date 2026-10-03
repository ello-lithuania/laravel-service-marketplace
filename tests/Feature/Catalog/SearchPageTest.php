<?php

use App\Models\Category;
use App\Models\City;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Paieška tekstu /paieska (testuose – SQLite, todėl LIKE kelias; MySQL FULLTEXT – žr. docs/drafts/etapas-4.md).
 */
test('paieška randa teikėjus ir tinkamas kategorijas', function () {
    [$root, $group] = categoryBranch();
    $leaf = Category::factory()->childOf($group)->create(['name' => 'Plytelių klijavimas', 'slug' => 'plyteliu-klijavimas-x']);
    $match = catalogProvider([$leaf], attributes: ['headline' => 'Plytelių klijavimas vonioje']);
    catalogProvider(attributes: ['headline' => 'Elektros darbai', 'description' => 'Instaliacija']);

    $this->get(route('search', ['q' => 'plyteles']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/Search')
            ->where('filters.q', 'plyteles')
            ->where('filters.rikiuoti', 'aktualumas')
            ->has('sortOptions', 5)
            ->has('providers.data', 1)
            ->where('providers.data.0.slug', $match->slug)
            ->has('categories', 1)
            ->where('categories.0.slug', $leaf->slug)
            ->where('categories.0.path', $root->name.' › '.$group->name));
})->skip(fn () => usesMysqlFulltext(), 'InnoDB FULLTEXT nemato neįrašytų eilučių transakcijoje (žr. usesMysqlFulltext())');

test('paieška su miesto filtru', function () {
    $vilnius = City::factory()->create();
    $inVilnius = catalogProvider(zones: [$vilnius], attributes: ['headline' => 'Santechnikas']);
    catalogProvider(zones: [City::factory()->create()], attributes: ['headline' => 'Santechnikas']);

    $this->get(route('search', ['q' => 'santechnikai', 'miestas' => $vilnius->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('providers.data', 1)
            ->where('providers.data.0.slug', $inVilnius->slug));
})->skip(fn () => usesMysqlFulltext(), 'InnoDB FULLTEXT nemato neįrašytų eilučių transakcijoje (žr. usesMysqlFulltext())');

test('paieškos rezultatai neindeksuojami', function () {
    $this->get(route('search', ['q' => 'dažymas']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('seo.title', 'Paieška: dažymas')
            ->where('seo.robots', 'noindex, follow'));
});

test('be paieškos teksto nukreipiama į teikėjų sąrašą su tais pačiais filtrais', function () {
    $city = City::factory()->create();

    $this->get(route('search', ['q' => '  ', 'miestas' => $city->slug]))
        ->assertRedirect(route('providers.index', ['miestas' => $city->slug]));
});

test('specialūs simboliai paieškoje nesugadina užklausos', function () {
    catalogProvider(attributes: ['display_name' => '100% kokybė']);

    $this->get(route('search', ['q' => '100%_" OR 1=1 --']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('filters.q', '100%_" OR 1=1 --'));
});
