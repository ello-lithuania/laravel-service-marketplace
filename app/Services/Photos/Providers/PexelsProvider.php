<?php

namespace App\Services\Photos\Providers;

use App\Services\Photos\Exceptions\PhotoSearchFailed;
use App\Services\Photos\Exceptions\ProviderUnavailable;
use App\Services\Photos\PhotoCredit;
use App\Services\Photos\PhotoSpec;
use App\Services\Photos\StockPhoto;
use Illuminate\Support\Facades\Http;

/**
 * Pexels API (https://www.pexels.com/api/documentation/) – geriausios kokybės nemokamos nuotraukos, reikia rakto.
 *
 * Licencija – Pexels License: galima naudoti nemokamai ir komerciškai, autoriaus nurodyti nebūtina (bet mandagu),
 * todėl autorių vis tiek išsaugom. Limitas – 200 užklausų per valandą (paveikslėlių atsisiuntimas nesiskaičiuoja).
 */
final class PexelsProvider implements StockPhotoProvider
{
    use ThrottlesRequests;

    public const SEARCH_URL = 'https://api.pexels.com/v1/search';

    public const LICENSE = 'Pexels License';

    public const LICENSE_URL = 'https://www.pexels.com/license/';

    /** Didžiausias leidžiamas per_page. */
    private const MAX_PER_PAGE = 80;

    public function __construct(
        private readonly string $apiKey,
        private readonly int $pauseMs = 300,
        private readonly int $timeout = 30,
    ) {}

    public function label(): string
    {
        return 'Pexels';
    }

    public function avoidsPeople(): bool
    {
        return false;
    }

    public function search(string $query, PhotoSpec $spec): array
    {
        $this->throttle($this->pauseMs);

        // Raktas siunčiamas antraštėje „Authorization: <raktas>" (be „Bearer")
        $response = Http::withHeaders(['Authorization' => $this->apiKey])
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout($this->timeout)
            ->get(self::SEARCH_URL, [
                'query' => $query,
                'orientation' => 'landscape',
                'per_page' => min(self::MAX_PER_PAGE, $spec->perPage),
            ]);

        if ($response->status() === 429) {
            throw new ProviderUnavailable('Pexels: išnaudotas užklausų limitas (200 per valandą) – pabandyk po valandos');
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new ProviderUnavailable('Pexels atmetė raktą – patikrink PEXELS_API_KEY .env faile');
        }

        if ($response->failed()) {
            throw new PhotoSearchFailed("Pexels atsakė klaida HTTP {$response->status()}");
        }

        $photos = $response->json('photos');

        if (! is_array($photos)) {
            throw new PhotoSearchFailed('Pexels grąžino netikėtą atsakymą (nėra „photos")');
        }

        $results = [];

        foreach ($photos as $item) {
            $photo = is_array($item) ? $this->toStockPhoto($item) : null;

            if ($photo !== null) {
                $results[] = $photo;
            }
        }

        return $results;
    }

    /**
     * @param  array<mixed>  $item  vienas „photos" elementas
     */
    private function toStockPhoto(array $item): ?StockPhoto
    {
        $src = is_array($item['src'] ?? null) ? $item['src'] : [];
        $url = null;

        // large2x – iki 1880 px pločio (940 × 2): užtenka kategorijos juostai (1600×700) ir pradžios puslapiui.
        // original dažnai 6000 px ir > 10 MB – jį imam tik jei kitų nėra
        foreach (['large2x', 'large', 'original'] as $size) {
            if (is_string($src[$size] ?? null) && $src[$size] !== '') {
                $url = $src[$size];
                break;
            }
        }

        $id = $item['id'] ?? null;

        if ($url === null || ! (is_int($id) || is_string($id))) {
            return null;
        }

        $alt = self::string($item['alt'] ?? null);

        return new StockPhoto(
            provider: 'pexels',
            id: (string) $id,
            downloadUrl: $url,
            width: is_int($item['width'] ?? null) ? $item['width'] : null,
            height: is_int($item['height'] ?? null) ? $item['height'] : null,
            description: (string) $alt,
            credit: new PhotoCredit(
                author: self::string($item['photographer'] ?? null),
                authorUrl: self::string($item['photographer_url'] ?? null),
                source: 'Pexels',
                sourceUrl: self::string($item['url'] ?? null),
                license: self::LICENSE,
                licenseUrl: self::LICENSE_URL,
                title: $alt,
            ),
        );
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
