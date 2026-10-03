<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Services\Seo\SitemapBuilder;
use Illuminate\Http\Response;

/**
 * /sitemap.xml (indeksas) ir /sitemaps/{dalis}/{nr}.xml. XML sudaro SitemapBuilder (su cache).
 * https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap
 */
class SitemapController extends Controller
{
    public function __construct(private readonly SitemapBuilder $sitemap) {}

    public function index(): Response
    {
        return $this->xml($this->sitemap->index());
    }

    public function show(string $section, int $page): Response
    {
        $xml = $this->sitemap->section($section, $page);

        abort_if($xml === null, 404);

        return $this->xml($xml);
    }

    private function xml(string $content): Response
    {
        return response($content, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            // Naršyklė / CDN gali laikyti valandą; serveryje – cache 6 val.
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
