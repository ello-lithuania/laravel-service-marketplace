<?php

namespace App\Services\Photos\Providers;

use App\Services\Photos\Exceptions\PhotoSearchFailed;
use App\Services\Photos\Exceptions\ProviderUnavailable;
use App\Services\Photos\PhotoCredit;
use App\Services\Photos\PhotoSpec;
use App\Services\Photos\StockPhoto;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

/**
 * Openverse API (https://api.openverse.org/v1/) – WordPress fondo atvirų licencijų paieška (Flickr, Wikimedia
 * Commons ir kt.). Rakto nereikia, bet anoniminiams klientams limitas mažas (~20 užklausų per minutę, ~200 per parą).
 *
 * Imam tik licencijas, leidžiančias komercinį naudojimą IR keitimą (license_type=commercial,modification):
 * CC0, Public Domain Mark, CC BY, CC BY-SA. Keitimas svarbus, nes miniatiūros nuotrauką apkerpa. CC BY ir CC BY-SA
 * **reikalauja nurodyti autorių** – tam saugom credit ir rodom puslapį „Nuotraukų autoriai".
 */
final class OpenverseProvider implements StockPhotoProvider
{
    use ThrottlesRequests;

    public const SEARCH_URL = 'https://api.openverse.org/v1/images/';

    /** commercial ∩ modification – dvigubas saugiklis, jei API filtras kada nors pasikeistų. */
    private const LICENSES = ['cc0', 'pdm', 'by', 'by-sa'];

    private const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /** Anoniminiams klientams – daugiausia 20 rezultatų puslapyje. */
    private const MAX_PAGE_SIZE = 20;

    /** Kiek sekundžių sutinkam palaukti, jei API paprašo (Retry-After). */
    private const MAX_RETRY_AFTER = 60;

    /** Wikimedia miniatiūros plotis: originalai dažnai 5000+ px ir dešimtys MB. 1920 – vienas standartinių dydžių. */
    private const WIKIMEDIA_THUMB_WIDTH = 1920;

    private const SOURCES = [
        'flickr' => 'Flickr',
        'wikimedia' => 'Wikimedia Commons',
        'stocksnap' => 'StockSnap',
        'rawpixel' => 'rawpixel',
        'nappy' => 'Nappy',
        'geographorguk' => 'Geograph',
    ];

    public function __construct(
        private readonly string $userAgent,
        private readonly int $pauseMs = 3100,
        private readonly int $timeout = 30,
        private readonly int $maxBytes = 10 * 1024 * 1024,
    ) {}

    public function label(): string
    {
        return 'Openverse';
    }

    public function avoidsPeople(): bool
    {
        return true;
    }

    public function search(string $query, PhotoSpec $spec): array
    {
        $response = $this->request($query, $spec);

        // Per greitai: jei API pasako, kiek palaukti, ir tai neilgai – palaukiam ir bandom dar kartą
        if ($response->status() === 429) {
            $retryAfter = (int) $response->header('Retry-After');

            if ($retryAfter > 0 && $retryAfter <= self::MAX_RETRY_AFTER) {
                Sleep::for($retryAfter)->seconds();
                $response = $this->request($query, $spec);
            }
        }

        if ($response->status() === 429) {
            throw new ProviderUnavailable('Openverse: išnaudotas užklausų be rakto limitas – pabandyk vėliau (rytoj) arba naudok Pexels raktą (PEXELS_API_KEY)');
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new ProviderUnavailable("Openverse atmetė užklausą (HTTP {$response->status()})");
        }

        if ($response->failed()) {
            throw new PhotoSearchFailed("Openverse atsakė klaida HTTP {$response->status()}");
        }

        $items = $response->json('results');

        if (! is_array($items)) {
            throw new PhotoSearchFailed('Openverse grąžino netikėtą atsakymą (nėra „results")');
        }

        $results = [];

        foreach ($items as $item) {
            $photo = is_array($item) ? $this->toStockPhoto($item) : null;

            if ($photo !== null) {
                $results[] = $photo;
            }
        }

        return $results;
    }

    private function request(string $query, PhotoSpec $spec): Response
    {
        $this->throttle($this->pauseMs);

        // Openverse prašo aiškaus User-Agent (kas siunčia ir kaip susisiekti), kitaip gali blokuoti
        return Http::withUserAgent($this->userAgent)
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout($this->timeout)
            ->get(self::SEARCH_URL, [
                'q' => $query,
                'license_type' => 'commercial,modification',
                'category' => 'photograph',
                'aspect_ratio' => 'wide',
                'size' => 'large',
                'mature' => 'false',
                'page_size' => min(self::MAX_PAGE_SIZE, $spec->perPage),
            ]);
    }

    /**
     * @param  array<mixed>  $item  vienas „results" elementas
     */
    private function toStockPhoto(array $item): ?StockPhoto
    {
        $id = self::string($item['id'] ?? null);
        $url = self::string($item['url'] ?? null);
        $license = strtolower((string) self::string($item['license'] ?? null));

        if ($id === null || $url === null || ! in_array($license, self::LICENSES, true)) {
            return null;
        }

        $extension = strtolower((string) (self::string($item['filetype'] ?? null)
            ?? pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION)));

        // GIF, SVG, TIFF ir pan. netinka (SVG gali turėti JavaScript, TIFF naršyklės nerodo)
        if ($extension !== '' && ! in_array($extension, self::EXTENSIONS, true)) {
            return null;
        }

        $width = is_int($item['width'] ?? null) ? $item['width'] : null;
        $source = (string) (self::string($item['source'] ?? null) ?? self::string($item['provider'] ?? null) ?? 'openverse');
        $downloadUrl = $this->downloadUrl($url, $source, $width);

        // Jei API žino failo dydį ir jis per didelis – net nebandom (Wikimedia miniatiūrai dydis kitas)
        $filesize = is_int($item['filesize'] ?? null) ? $item['filesize'] : null;

        if ($downloadUrl === $url && $filesize !== null && $filesize > $this->maxBytes) {
            return null;
        }

        $title = self::string($item['title'] ?? null);
        $version = self::string($item['license_version'] ?? null);

        return new StockPhoto(
            provider: 'openverse',
            id: $id,
            downloadUrl: $downloadUrl,
            width: $width,
            height: is_int($item['height'] ?? null) ? $item['height'] : null,
            description: trim($title.' '.implode(' ', $this->tags($item['tags'] ?? null))),
            credit: new PhotoCredit(
                author: self::string($item['creator'] ?? null),
                authorUrl: self::string($item['creator_url'] ?? null),
                source: self::SOURCES[$source] ?? Str::headline($source),
                sourceUrl: self::string($item['foreign_landing_url'] ?? null),
                license: $this->licenseName($license, $version),
                licenseUrl: self::string($item['license_url'] ?? null) ?? $this->licenseUrl($license, $version),
                title: $title,
            ),
        );
    }

    /**
     * „by" + „2.0" → „CC BY 2.0", „cc0" → „CC0 1.0", „pdm" → „Public Domain Mark 1.0".
     */
    private function licenseName(string $license, ?string $version): string
    {
        $suffix = $version !== null ? ' '.$version : '';

        return match ($license) {
            'cc0' => 'CC0'.$suffix,
            'pdm' => 'Public Domain Mark'.$suffix,
            default => 'CC '.strtoupper($license).$suffix,
        };
    }

    private function licenseUrl(string $license, ?string $version): string
    {
        return match ($license) {
            'cc0' => 'https://creativecommons.org/publicdomain/zero/1.0/',
            'pdm' => 'https://creativecommons.org/publicdomain/mark/1.0/',
            default => 'https://creativecommons.org/licenses/'.$license.'/'.($version ?? '4.0').'/',
        };
    }

    /**
     * Wikimedia Commons originalas gali būti dešimtys MB, todėl didelėms nuotraukoms imam oficialią miniatiūrą:
     * …/commons/a/ab/Failas.jpg → …/commons/thumb/a/ab/Failas.jpg/1920px-Failas.jpg
     */
    private function downloadUrl(string $url, string $source, ?int $width): string
    {
        $pattern = '#^(https://upload\.wikimedia\.org/wikipedia/commons)/([0-9a-f])/([0-9a-f]{2})/([^/?]+\.(?:jpe?g|png))$#i';

        if ($source !== 'wikimedia' || $width === null || $width <= self::WIKIMEDIA_THUMB_WIDTH
            || ! preg_match($pattern, $url, $match)) {
            return $url;
        }

        return sprintf('%s/thumb/%s/%s/%s/%dpx-%s', $match[1], $match[2], $match[3], $match[4], self::WIKIMEDIA_THUMB_WIDTH, $match[4]);
    }

    /**
     * @return list<string> žymų pavadinimai (iš jų spėjama, ar nuotraukoje žmonės)
     */
    private function tags(mixed $tags): array
    {
        if (! is_array($tags)) {
            return [];
        }

        $names = [];

        foreach ($tags as $tag) {
            if (is_array($tag) && is_string($tag['name'] ?? null)) {
                $names[] = $tag['name'];
            }
        }

        return $names;
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
