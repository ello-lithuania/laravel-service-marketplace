<?php

namespace App\Services\Photos;

use App\Services\Photos\Providers\OpenverseProvider;
use App\Services\Photos\Providers\PexelsProvider;
use App\Services\Photos\Providers\StockPhotoProvider;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Šaltinių ir atsisiuntėjo kūrimas pagal config/photos.php ir config/services.php (pexels.key).
 */
final class PhotoSources
{
    public const SOURCES = ['auto', 'pexels', 'openverse'];

    /**
     * auto – Pexels (jei yra raktas), o jei jis nieko tinkamo neranda ar išnaudoja limitą – Openverse.
     *
     * @return list<StockPhotoProvider>
     *
     * @throws InvalidArgumentException nežinomas šaltinis arba Pexels be rakto
     */
    public function providers(string $source): array
    {
        $key = config('services.pexels.key');
        $pexels = is_string($key) && $key !== ''
            ? new PexelsProvider($key, (int) config('photos.pause_ms.pexels', 300), $this->timeout())
            : null;
        $openverse = new OpenverseProvider(
            self::userAgent(),
            (int) config('photos.pause_ms.openverse', 3100),
            $this->timeout(),
            $this->maxBytes(),
        );

        return match ($source) {
            'auto' => $pexels === null ? [$openverse] : [$pexels, $openverse],
            'pexels' => $pexels === null
                ? throw new InvalidArgumentException('Nenurodytas PEXELS_API_KEY (.env). Nemokamą raktą gausi https://www.pexels.com/api/')
                : [$pexels],
            'openverse' => [$openverse],
            default => throw new InvalidArgumentException("Nežinomas šaltinis „{$source}\" (galimi: ".implode(', ', self::SOURCES).')'),
        };
    }

    public function downloader(): PhotoDownloader
    {
        return new PhotoDownloader(self::userAgent(), $this->maxBytes(), $this->timeout());
    }

    /**
     * „paslaugu-platforma/1.0 (+https://…; photos:download)". HTTP antraštėse – tik ASCII, todėl slug.
     */
    public static function userAgent(): string
    {
        $custom = config('photos.user_agent');

        if (is_string($custom) && $custom !== '') {
            return $custom;
        }

        $name = Str::slug((string) config('app.name')) ?: 'laravel';

        return sprintf('%s/1.0 (+%s; photos:download)', $name, (string) config('app.url'));
    }

    private function timeout(): int
    {
        return (int) config('photos.timeout', 30);
    }

    private function maxBytes(): int
    {
        return (int) config('photos.max_bytes', 10 * 1024 * 1024);
    }
}
