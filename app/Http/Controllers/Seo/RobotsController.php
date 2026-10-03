<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * /robots.txt – generuojamas, o ne statinis public/robots.txt:
 *  - nuoroda į sitemap turi būti absoliuti, su tikru domenu (APP_URL), o jis kiekvienoje aplinkoje kitoks;
 *  - ne produkcijoje (staging, dev) draudžiam viską, kad bandomoji svetainė nepatektų į Google.
 * Disallow – privačios sritys ir begalinės paieškos rezultatų erdvės (taupom roboto „biudžetą").
 * Pastaba: robots.txt nėra apsauga – privačius puslapius saugo prisijungimas, ne šis failas.
 * https://developers.google.com/search/docs/crawling-indexing/robots/intro
 */
class RobotsController extends Controller
{
    private const DISALLOW = [
        '/admin', '/filament', '/livewire', '/paskyra', '/settings', '/dashboard', '/pranesimai', '/teikejas',
        '/mano-uzklausos', '/mano-pasiulymai', '/uzklausos', '/pasiulymai', '/paieska', '/login', '/register',
        '/forgot-password', '/reset-password', '/email', '/user',
    ];

    public function __invoke(): Response
    {
        $lines = ['User-agent: *'];

        if (app()->isProduction()) {
            foreach (self::DISALLOW as $path) {
                $lines[] = 'Disallow: '.$path;
            }

            $lines[] = '';
            $lines[] = 'Sitemap: '.route('seo.sitemap');
        } else {
            $lines[] = 'Disallow: /';
        }

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
