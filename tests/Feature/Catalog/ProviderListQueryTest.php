<?php

use App\Enums\ProviderSort;
use App\Models\Category;
use App\Models\City;
use App\Models\ProviderProfile;
use App\Services\Catalog\CatalogCache;
use App\Services\Catalog\ProviderFilters;
use App\Services\Catalog\ProviderListQuery;
use App\Services\Catalog\SearchTerms;

/**
 * Kurie teikėjai patenka į sąrašą ir kokia tvarka (scope'ai ProviderProfile → „Katalogas ir paieška").
 *
 * @param  list<int>|null  $categoryIds
 * @return list<int>
 */
function listedProviderIds(ProviderFilters $filters = new ProviderFilters, ?array $categoryIds = null): array
{
    return app(ProviderListQuery::class)->paginate($filters, $categoryIds)->getCollection()->modelKeys();
}

/**
 * @return list<int>
 */
function relatedCategoryIds(int $categoryId): array
{
    return app(CatalogCache::class)->categories()->relatedIds($categoryId);
}

test('3 lygio puslapyje rodomi teikėjai su ja pačia, jos tėvu ar seneliu', function () {
    [$root, $group, $leaf] = categoryBranch();
    [, , $otherLeaf] = categoryBranch();

    $byLeaf = catalogProvider([$leaf]);
    $byGroup = catalogProvider([$group]);
    $byRoot = catalogProvider([$root]);
    $other = catalogProvider([$otherLeaf]);

    expect(listedProviderIds(categoryIds: relatedCategoryIds($leaf->id)))
        ->toEqualCanonicalizing([$byLeaf->id, $byGroup->id, $byRoot->id])
        ->not->toContain($other->id);
});

test('1–2 lygio puslapyje rodomi ir siauresnių kategorijų teikėjai', function () {
    [$root, $group, $leaf] = categoryBranch();
    $siblingGroup = Category::factory()->childOf($root)->create();

    $byLeaf = catalogProvider([$leaf]);
    $bySibling = catalogProvider([$siblingGroup]);

    expect(listedProviderIds(categoryIds: relatedCategoryIds($group->id)))->toBe([$byLeaf->id])
        ->and(listedProviderIds(categoryIds: relatedCategoryIds($root->id)))
        ->toEqualCanonicalizing([$byLeaf->id, $bySibling->id]);
});

test('teikėjas su keliomis tinkamomis kategorijomis sąraše nepasikartoja', function () {
    [$root, $group, $leaf] = categoryBranch();
    $provider = catalogProvider([$root, $group, $leaf]);

    $page = app(ProviderListQuery::class)->paginate(new ProviderFilters, relatedCategoryIds($group->id));

    expect($page->total())->toBe(1)
        ->and($page->getCollection()->modelKeys())->toBe([$provider->id]);
});

test('rodomi tik aktyvūs ir neištrinti teikėjai', function () {
    $active = catalogProvider();
    ProviderProfile::factory()->pending()->create();
    ProviderProfile::factory()->hidden()->create();
    ProviderProfile::factory()->suspended()->create();
    catalogProvider()->delete();

    expect(listedProviderIds())->toBe([$active->id]);
});

test('miesto filtras: zonoje esantis ir visoje Lietuvoje dirbantis teikėjas', function () {
    $vilnius = City::factory()->create();
    $kaunas = City::factory()->create();

    $inVilnius = catalogProvider(zones: [$vilnius]);
    $inKaunas = catalogProvider(zones: [$kaunas]);
    $everywhere = catalogProvider(attributes: ['serves_whole_country' => true]);

    $geography = app(CatalogCache::class)->geography();
    $filters = new ProviderFilters(city: $geography->findCity($vilnius->id));

    expect(listedProviderIds($filters))->toEqualCanonicalizing([$inVilnius->id, $everywhere->id])
        ->not->toContain($inKaunas->id);
});

test('bazinis miestas be zonos teikėjo į miesto sąrašą neįtraukia', function () {
    $city = City::factory()->create();
    catalogProvider(attributes: ['city_id' => $city->id]);

    $filters = new ProviderFilters(city: app(CatalogCache::class)->geography()->findCity($city->id));

    expect(listedProviderIds($filters))->toBe([]);
});

test('patikrintų ir minimalaus reitingo filtrai', function () {
    $verified = catalogProvider(attributes: ['verified_at' => now(), 'rating_avg' => 4.9]);
    $good = catalogProvider(attributes: ['rating_avg' => 4.5]);
    catalogProvider(attributes: ['rating_avg' => 3.2]);

    expect(listedProviderIds(new ProviderFilters(verifiedOnly: true)))->toBe([$verified->id])
        ->and(listedProviderIds(new ProviderFilters(minRating: 4.5)))->toBe([$verified->id, $good->id]);
});

test('rikiavimas pagal reitingą, atsiliepimus, darbus ir naujumą', function (ProviderSort $sort, array $expectedOrder) {
    $providers = [
        'a' => catalogProvider(attributes: ['rating_avg' => 4.9, 'reviews_count' => 5, 'completed_jobs_count' => 1, 'created_at' => now()->subYears(2)]),
        'b' => catalogProvider(attributes: ['rating_avg' => 4.2, 'reviews_count' => 40, 'completed_jobs_count' => 7, 'created_at' => now()->subMonth()]),
        'c' => catalogProvider(attributes: ['rating_avg' => 4.6, 'reviews_count' => 12, 'completed_jobs_count' => 30, 'created_at' => now()->subYear()]),
    ];

    $expected = array_map(fn (string $key) => $providers[$key]->id, $expectedOrder);

    expect(listedProviderIds(new ProviderFilters(sort: $sort)))->toBe($expected);
})->with([
    'reitingas' => [ProviderSort::Rating, ['a', 'c', 'b']],
    'atsiliepimai' => [ProviderSort::Reviews, ['b', 'c', 'a']],
    'darbai' => [ProviderSort::CompletedJobs, ['c', 'b', 'a']],
    'naujausi' => [ProviderSort::Newest, ['b', 'c', 'a']],
]);

test('vienodo reitingo teikėjai rikiuojami stabiliai pagal id', function () {
    $first = catalogProvider(attributes: ['rating_avg' => 4, 'reviews_count' => 3]);
    $second = catalogProvider(attributes: ['rating_avg' => 4, 'reviews_count' => 3]);

    expect(listedProviderIds())->toBe([$second->id, $first->id]);
});

test('puslapiavimas po 20, nuorodose išlieka filtrai', function () {
    foreach (range(1, 21) as $i) {
        catalogProvider(attributes: ['verified_at' => now()]);
    }

    // withQueryString() ima parametrus iš dabartinės užklausos
    request()->merge(['patikrinti' => '1']);

    $page = app(ProviderListQuery::class)->paginate(new ProviderFilters(verifiedOnly: true));

    expect($page->perPage())->toBe(20)
        ->and($page->total())->toBe(21)
        ->and($page->lastPage())->toBe(2)
        ->and($page->nextPageUrl())->toContain('patikrinti=1')->toContain('puslapis=2');
});

test('paieška tekstu SQLite: LIKE per pavadinimą, antraštę ir aprašymą, visi žodžiai privalomi', function () {
    $byName = catalogProvider(attributes: ['display_name' => 'UAB Plytelių meistrai', 'headline' => null, 'description' => null]);
    $byHeadline = catalogProvider(attributes: ['headline' => 'Plytelių klijavimas vonioje', 'description' => null]);
    $byDescription = catalogProvider(attributes: ['headline' => null, 'description' => 'Klijuoju plyteles ir dažau sienas.']);
    catalogProvider(attributes: ['display_name' => 'Elektros darbai', 'headline' => 'Elektrikas', 'description' => 'Instaliacija']);

    $search = fn (string $text) => listedProviderIds(new ProviderFilters(search: SearchTerms::parse($text)));

    expect($search('plytelės'))->toEqualCanonicalizing([$byName->id, $byHeadline->id, $byDescription->id])
        ->and($search('plytelių vonioje'))->toBe([$byHeadline->id])
        ->and($search('santechnikas'))->toBe([]);
})->skip(fn () => usesMysqlFulltext(), 'InnoDB FULLTEXT nemato neįrašytų eilučių transakcijoje (žr. usesMysqlFulltext())');

test('rikiuojant pagal aktualumą pavadinimo atitikmuo pirmesnis už aprašymo', function () {
    $byDescription = catalogProvider(attributes: ['rating_avg' => 5, 'headline' => null, 'description' => 'Atlieku stogo remontą']);
    $byName = catalogProvider(attributes: ['rating_avg' => 3, 'display_name' => 'Stogų remontas LT', 'headline' => null]);

    $filters = new ProviderFilters(sort: ProviderSort::Relevance, search: SearchTerms::parse('stogai'));

    expect(listedProviderIds($filters))->toBe([$byName->id, $byDescription->id]);
})->skip(fn () => usesMysqlFulltext(), 'InnoDB FULLTEXT nemato neįrašytų eilučių transakcijoje (žr. usesMysqlFulltext())');
