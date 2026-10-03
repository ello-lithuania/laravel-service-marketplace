<?php

namespace App\Services\Seo;

use App\Models\ProviderProfile;
use App\Services\Catalog\CachedCategory;
use App\Services\Catalog\CachedCity;
use App\Services\Catalog\CatalogCache;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use XMLWriter;

/**
 * sitemap.xml – sąrašas puslapių, kuriuos norim matyti Google rezultatuose (Etapas 8).
 *
 * Struktūra: /sitemap.xml – indeksas (sąrašas kitų sitemap failų), o kiekviena dalis – /sitemaps/{dalis}/{nr}.xml:
 *  - paslaugos – pradžia, katalogas, kategorijos, teikėjų sąrašai pagal miestą;
 *  - miestai – „{Paslauga} {mieste}" puslapiai, kuriuose yra teikėjų (CategoryCityIndex);
 *  - meistrai – aktyvių teikėjų profiliai su paskutinio atnaujinimo data (lastmod).
 * Viename faile – iki CHUNK adresų (Google riba – 50 000 ir 50 MB). Neindeksuojami puslapiai (paieška, tušti
 * „paslauga mieste", paskyros) į sitemap nededami.
 *
 * Kiekvienas failas laikomas cache 6 val.: robotai jį skaito dažnai, o duomenys keičiasi lėtai.
 * Cache saugomas XML tekstas (eilutė), ne objektai. https://www.sitemaps.org/protocol.html
 */
final class SitemapBuilder
{
    public const CHUNK = 10_000;

    public const SECTIONS = ['paslaugos', 'miestai', 'meistrai'];

    private const TTL_HOURS = 6;

    public function __construct(
        private readonly CatalogCache $catalog,
        private readonly CategoryCityIndex $categoryCities,
    ) {}

    public function index(): string
    {
        return Cache::remember('seo:sitemap:index:v1', now()->addHours(self::TTL_HOURS), function (): string {
            $xml = $this->start('sitemapindex');

            foreach (self::SECTIONS as $section) {
                $pages = $this->pageCount($section);

                for ($page = 1; $page <= $pages; $page++) {
                    $xml->startElement('sitemap');
                    $xml->writeElement('loc', route('seo.sitemap.section', ['section' => $section, 'page' => $page]));
                    $xml->endElement();
                }
            }

            return $this->finish($xml);
        });
    }

    /**
     * Viena sitemap dalis arba null, jei tokio puslapio nėra (→ 404).
     */
    public function section(string $section, int $page): ?string
    {
        if (! in_array($section, self::SECTIONS, true) || $page < 1 || $page > $this->pageCount($section)) {
            return null;
        }

        return Cache::remember("seo:sitemap:{$section}:{$page}:v1", now()->addHours(self::TTL_HOURS), function () use ($section, $page): string {
            $xml = $this->start('urlset');

            foreach ($this->urls($section, $page) as [$loc, $lastmod]) {
                $xml->startElement('url');
                $xml->writeElement('loc', $loc);

                if ($lastmod !== null) {
                    $xml->writeElement('lastmod', $lastmod->toAtomString());
                }

                $xml->endElement();
            }

            return $this->finish($xml);
        });
    }

    public function pageCount(string $section): int
    {
        $total = match ($section) {
            'paslaugos' => 1,
            'miestai' => array_sum(array_map('count', $this->categoryCities->pages())),
            'meistrai' => ProviderProfile::query()->active()->count(),
            default => 0,
        };

        return max(1, (int) ceil($total / self::CHUNK));
    }

    /**
     * @return iterable<array{0: string, 1: CarbonInterface|null}>
     */
    private function urls(string $section, int $page): iterable
    {
        return match ($section) {
            'paslaugos' => $this->catalogUrls(),
            'miestai' => array_slice($this->categoryCityUrls(), ($page - 1) * self::CHUNK, self::CHUNK),
            'meistrai' => $this->providerUrls($page),
            default => [],
        };
    }

    /**
     * @return list<array{0: string, 1: null}>
     */
    private function catalogUrls(): array
    {
        $urls = [[route('home'), null], [route('categories.index'), null], [route('providers.index'), null]];

        foreach ($this->catalog->categories()->all() as $category) {
            $urls[] = [route('categories.show', ['category' => $category->slug]), null];
        }

        // Teikėjų sąrašas mieste (/meistrai?miestas=…) – indeksuojamas puslapis su savo canonical (CatalogSeo::providers)
        foreach ($this->catalog->geography()->popularCities(PHP_INT_MAX) as $city) {
            $urls[] = [route('providers.index', ['miestas' => $city->slug]), null];
        }

        return $urls;
    }

    /**
     * @return list<array{0: string, 1: null}>
     */
    private function categoryCityUrls(): array
    {
        $tree = $this->catalog->categories();
        $geography = $this->catalog->geography();
        $urls = [];

        foreach ($this->categoryCities->pages() as $categoryId => $cityIds) {
            $category = $tree->find($categoryId);

            if (! $category instanceof CachedCategory) {
                continue;
            }

            foreach ($cityIds as $cityId) {
                $city = $geography->findCity($cityId);

                if ($city instanceof CachedCity) {
                    $urls[] = [route('categories.city', ['category' => $category->slug, 'city' => $city->slug]), null];
                }
            }
        }

        return $urls;
    }

    /**
     * Aktyvūs profiliai pagal id: puslapiai pastovūs, o užklausa eina pirminiu raktu.
     *
     * @return list<array{0: string, 1: CarbonInterface|null}>
     */
    private function providerUrls(int $page): array
    {
        // toBase() – be Eloquent modelių (10 000 objektų sukūrimas kainuotų daugiau nei pati užklausa)
        return array_values(ProviderProfile::query()
            ->active()
            ->orderBy('id')
            ->forPage($page, self::CHUNK)
            ->toBase()
            ->get(['slug', 'updated_at'])
            ->map(fn (object $provider): array => [
                route('providers.show', ['providerProfile' => $provider->slug]),
                $provider->updated_at === null ? null : CarbonImmutable::parse($provider->updated_at, 'UTC'),
            ])
            ->all());
    }

    private function start(string $root): XMLWriter
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement($root);
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        return $xml;
    }

    private function finish(XMLWriter $xml): string
    {
        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }
}
