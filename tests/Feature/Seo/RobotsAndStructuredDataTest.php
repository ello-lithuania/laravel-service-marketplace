<?php

use App\Models\Category;
use App\Models\City;
use App\Models\ProviderProfile;
use App\Models\Review;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * JSON-LD iš Inertia prop'o „seo.json_ld" kaip masyvas (@graph mazgai).
 *
 * @return list<array<string, mixed>>
 */
function jsonLdGraph(TestResponse $response): array
{
    $json = null;
    $response->assertInertia(function (Assert $page) use (&$json) {
        $json = $page->toArray()['props']['seo']['json_ld'];
    });

    $decoded = json_decode((string) $json, true);
    expect($decoded['@context'] ?? null)->toBe('https://schema.org');

    return $decoded['@graph'];
}

test('robots.txt produkcijoje: privačios sritys uždraustos, nuoroda į sitemap', function () {
    app()->detectEnvironment(fn () => 'production');

    $body = $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')->getContent();

    expect($body)->toContain('Disallow: /admin')
        ->toContain('Disallow: /paskyra')
        ->toContain('Disallow: /paieska')
        ->toContain('Sitemap: '.route('seo.sitemap'))
        ->not->toContain("Disallow: /\n");
});

test('robots.txt ne produkcijoje draudžia viską (staging neturi patekti į Google)', function () {
    expect($this->get('/robots.txt')->getContent())->toBe("User-agent: *\nDisallow: /\n");
});

test('pradžios puslapis: WebSite su SearchAction ir Organization – ir pirmame HTML', function () {
    $response = $this->get('/');
    $graph = jsonLdGraph($response);

    expect(array_column($graph, '@type'))->toBe(['WebSite', 'Organization'])
        ->and($graph[0]['potentialAction']['target']['urlTemplate'])->toBe(route('search').'?q={search_term_string}');

    // Serverio HTML (robotams be JavaScript) – tas pats tekstas su Inertia raktu
    expect($response->getContent())->toContain('<script type="application/ld+json" data-inertia="json-ld">');
});

test('kategorijos puslapis: BreadcrumbList nuo pradžios iki kategorijos ir miesto', function () {
    $root = Category::factory()->create(['name' => 'Statyba', 'slug' => 'statyba', 'depth' => 1]);
    $leaf = Category::factory()->create(['name' => 'Dažymas', 'slug' => 'dazymas', 'parent_id' => $root->id, 'depth' => 2]);
    City::factory()->create(['slug' => 'kaunas', 'name_locative' => 'Kaune']);

    $graph = jsonLdGraph($this->get(route('categories.city', ['category' => 'dazymas', 'city' => 'kaunas'])));
    $items = $graph[0]['itemListElement'];

    expect($graph[0]['@type'])->toBe('BreadcrumbList')
        ->and(array_column($items, 'name'))->toBe(['Pradžia', 'Paslaugos', 'Statyba', 'Dažymas', 'Dažymas Kaune'])
        ->and(array_column($items, 'position'))->toBe([1, 2, 3, 4, 5])
        ->and($items[3]['item'])->toBe(route('categories.show', 'dazymas'));
});

test('teikėjo profilis: ProfessionalService su vieta, zonomis ir įvertinimu', function () {
    $provider = ProviderProfile::factory()->create(['display_name' => 'Meistras Jonas', 'headline' => 'Plytelių klijavimas']);
    $provider->serviceAreas()->attach($provider->city_id);
    Review::factory()->count(2)->for($provider)->create(['rating' => 5]);
    $provider->forceFill(['rating_avg' => 4.5, 'reviews_count' => 2])->save();

    $business = jsonLdGraph($this->get(route('providers.show', $provider->slug)))[0];

    expect($business)->toMatchArray([
        '@type' => 'ProfessionalService',
        'name' => 'Meistras Jonas',
        'description' => 'Plytelių klijavimas',
        'url' => route('providers.show', $provider->slug),
    ])
        ->and($business['address'])->toMatchArray(['addressLocality' => City::query()->find($provider->city_id)?->name, 'addressCountry' => 'LT'])
        ->and($business['areaServed'][0]['@type'])->toBe('City')
        ->and($business['aggregateRating'])->toMatchArray(['ratingValue' => '4.50', 'reviewCount' => 2, 'bestRating' => 5]);
});

test('be atsiliepimų įvertinimo nėra; „visa Lietuva" – Country', function () {
    $provider = ProviderProfile::factory()->wholeCountry()->create();

    $business = jsonLdGraph($this->get(route('providers.show', $provider->slug)))[0];

    expect($business)->not->toHaveKey('aggregateRating')
        ->and($business['areaServed'])->toBe(['@type' => 'Country', 'name' => 'Lietuva']);
});

test('JSON-LD saugus: </script> tekste užkoduojamas', function () {
    $provider = ProviderProfile::factory()->create(['headline' => '</script><script>alert(1)</script>']);

    $html = $this->get(route('providers.show', $provider->slug))->getContent();

    expect($html)->not->toContain('</script><script>alert(1)')
        ->toContain('</script>');
});

test('paieškos puslapis JSON-LD neturi', function () {
    $this->get(route('search', ['q' => 'plytelės']))
        ->assertInertia(fn (Assert $page) => $page->where('seo.json_ld', null));
});
