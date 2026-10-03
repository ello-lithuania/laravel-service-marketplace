<?php

use App\Enums\ProviderStatus;
use App\Models\Category;
use App\Models\City;
use App\Models\ProviderProfile;
use App\Services\Seo\SitemapBuilder;

/**
 * @return list<string> visi <loc> adresai iš XML
 */
function sitemapLocs(string $xml): array
{
    $document = simplexml_load_string($xml);
    expect($document)->not->toBeFalse();

    $locs = [];
    foreach ($document->children() as $node) {
        $locs[] = (string) $node->loc;
    }

    return $locs;
}

beforeEach(function () {
    $this->root = Category::factory()->create(['slug' => 'statyba', 'depth' => 1]);
    $this->child = Category::factory()->create(['slug' => 'apdaila', 'parent_id' => $this->root->id, 'depth' => 2]);
    $this->leaf = Category::factory()->create(['slug' => 'plyteles', 'parent_id' => $this->child->id, 'depth' => 3]);
    $this->otherLeaf = Category::factory()->create(['slug' => 'santechnika', 'depth' => 1]);
    $this->vilnius = City::factory()->create(['slug' => 'vilnius']);
    $this->kaunas = City::factory()->create(['slug' => 'kaunas']);
});

test('sitemap indeksas rodo visas dalis ir yra validus XML', function () {
    $response = $this->get('/sitemap.xml')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/xml')
        ->and(sitemapLocs($response->getContent()))->toBe([
            route('seo.sitemap.section', ['section' => 'paslaugos', 'page' => 1]),
            route('seo.sitemap.section', ['section' => 'miestai', 'page' => 1]),
            route('seo.sitemap.section', ['section' => 'meistrai', 'page' => 1]),
        ]);
});

test('paslaugų dalis: pradžia, katalogas, visos aktyvios kategorijos ir teikėjai pagal miestą', function () {
    $inactive = Category::factory()->create(['slug' => 'isjungta', 'is_active' => false]);

    $locs = sitemapLocs($this->get('/sitemaps/paslaugos/1.xml')->assertOk()->getContent());

    expect($locs)->toContain(
        route('home'),
        route('categories.index'),
        route('categories.show', 'statyba'),
        route('categories.show', 'plyteles'),
        route('providers.index', ['miestas' => 'vilnius']),
    )->not->toContain(route('categories.show', $inactive->slug));
});

test('„paslauga mieste" – tik puslapiai su teikėjais (zona arba visa Lietuva), įskaitant tėvų ir vaikų kategorijas', function () {
    // Teikėjas Vilniuje pasirinko 3 lygio „plyteles" → užpildo plyteles, apdaila, statyba Vilniuje
    $zoned = ProviderProfile::factory()->create();
    $zoned->categories()->attach($this->leaf);
    $zoned->serviceAreas()->attach($this->vilnius);
    // Visa Lietuva su „santechnika" → visuose miestuose
    $national = ProviderProfile::factory()->wholeCountry()->create();
    $national->categories()->attach($this->otherLeaf);
    // Paslėptas teikėjas puslapių neužpildo
    $hidden = ProviderProfile::factory()->create(['status' => ProviderStatus::Hidden]);
    $hidden->categories()->attach($this->leaf);
    $hidden->serviceAreas()->attach($this->kaunas);

    $locs = sitemapLocs($this->get('/sitemaps/miestai/1.xml')->assertOk()->getContent());

    expect($locs)->toContain(
        route('categories.city', ['category' => 'plyteles', 'city' => 'vilnius']),
        route('categories.city', ['category' => 'apdaila', 'city' => 'vilnius']),
        route('categories.city', ['category' => 'statyba', 'city' => 'vilnius']),
        route('categories.city', ['category' => 'santechnika', 'city' => 'kaunas']),
        route('categories.city', ['category' => 'santechnika', 'city' => 'vilnius']),
    )->not->toContain(
        route('categories.city', ['category' => 'plyteles', 'city' => 'kaunas']),
    );

    // Sitemap'e esantis puslapis tikrai indeksuojamas, o neįtrauktas – noindex (ta pati taisyklė)
    $this->get(route('categories.city', ['category' => 'statyba', 'city' => 'vilnius']))
        ->assertInertia(fn ($page) => $page->where('seo.robots', 'index, follow'));
    $this->get(route('categories.city', ['category' => 'plyteles', 'city' => 'kaunas']))
        ->assertInertia(fn ($page) => $page->where('seo.robots', 'noindex, follow'));
});

test('teikėjų dalis: tik aktyvūs profiliai su lastmod, dalijama po CHUNK', function () {
    $active = ProviderProfile::factory()->create(['slug' => 'aktyvus-meistras']);
    $suspended = ProviderProfile::factory()->suspended()->create(['slug' => 'uzblokuotas-meistras']);

    $xml = $this->get('/sitemaps/meistrai/1.xml')->assertOk()->getContent();

    expect(sitemapLocs($xml))->toContain(route('providers.show', 'aktyvus-meistras'))
        ->not->toContain(route('providers.show', 'uzblokuotas-meistras'))
        ->and($xml)->toContain('<lastmod>'.$active->updated_at?->toAtomString().'</lastmod>');

    // Neegzistuojantis puslapis ir nežinoma dalis – 404
    $this->get('/sitemaps/meistrai/2.xml')->assertNotFound();
    $this->get('/sitemaps/vartotojai/1.xml')->assertNotFound();
});

test('sitemap laikomas cache: naujas teikėjas atsiranda tik pasibaigus cache', function () {
    $this->get('/sitemaps/meistrai/1.xml')->assertOk();
    ProviderProfile::factory()->create(['slug' => 'naujas-meistras']);

    expect($this->get('/sitemaps/meistrai/1.xml')->getContent())->not->toContain('naujas-meistras');

    cache()->flush();
    expect($this->get('/sitemaps/meistrai/1.xml')->getContent())->toContain('naujas-meistras');
});

test('CHUNK riba – pagal Google limitą (50 000)', function () {
    expect(SitemapBuilder::CHUNK)->toBeLessThanOrEqual(50_000);
});
