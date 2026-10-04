<?php

namespace App\Services\Photos;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

/**
 * Vietinė atsisiųstų nuotraukų biblioteka – diskas „stock-photos" (storage/app/stock-photos, Git'e ignoruojamas):
 *
 *     categories/{kategorijos-slug}.jpg + credits.json
 *     site/{hero|providers|request|auth}.jpg + credits.json
 *     portfolio/{1 lygio slug}/{slug}-01.jpg … + credits.json   ← demo portfolio rinkinys (MediaGenerator)
 *
 * credits.json – vardas => failas, stock_id, credit (autorius, šaltinis, licencija), matmenys, data.
 * Kodėl biblioteka, o ne iškart į medialibrary: po „migrate:fresh --seed" media lentelė tuščia, o iš bibliotekos
 * nuotraukos vėl prisegamos be interneto ir be API limitų (StockPhotoSeeder, photos:download).
 */
final class StockPhotoLibrary
{
    public const MANIFEST = 'credits.json';

    public const CATEGORIES = 'categories';

    public const SITE = 'site';

    public const PORTFOLIO = 'portfolio';

    public function find(string $directory, string $name): ?LibraryPhoto
    {
        return $this->all($directory)[$name] ?? null;
    }

    /**
     * Visos katalogo nuotraukos, kurių failas tikrai yra diske (pagal vardą).
     *
     * @return array<string, LibraryPhoto>
     */
    public function all(string $directory): array
    {
        $disk = $this->disk();
        $photos = [];

        foreach ($this->manifest($directory) as $name => $entry) {
            $file = is_string($entry['file'] ?? null) ? basename($entry['file']) : null;
            $stockId = is_string($entry['stock_id'] ?? null) ? $entry['stock_id'] : null;
            $credit = is_array($entry['credit'] ?? null) ? PhotoCredit::fromArray($entry['credit']) : null;

            if ($file === null || $stockId === null || $credit === null || ! $disk->exists($directory.'/'.$file)) {
                continue;
            }

            $photos[(string) $name] = new LibraryPhoto((string) $name, $disk->path($directory.'/'.$file), $file, $stockId, $credit);
        }

        ksort($photos);

        return $photos;
    }

    /**
     * Įrašo atsisiųstą nuotrauką: per didelę sumažina (Fit::Max – proporcijos nesikeičia, EXIF pasukimas pritaikomas)
     * ir atnaujina credits.json. Tas pats vardas – senas failas pakeičiamas.
     */
    public function store(string $directory, string $name, FetchedPhoto $photo, int $maxDimension): LibraryPhoto
    {
        $disk = $this->disk();
        $file = $photo->file;
        $manifest = $this->manifest($directory);
        $previous = is_string($manifest[$name]['file'] ?? null) ? basename($manifest[$name]['file']) : null;

        $resize = max($file->width, $file->height) > $maxDimension;
        // Sumažinta nuotrauka visada įrašoma kaip JPEG: tai nuotraukos, o PNG jas saugotų kelis kartus didesnes
        $fileName = $name.'.'.($resize ? 'jpg' : $file->extension());
        $path = $directory.'/'.$fileName;

        if ($previous !== null && $previous !== $fileName) {
            $disk->delete($directory.'/'.$previous);
        }

        // Diskas sukonfigūruotas su throw = false: nepavykus put() grąžina false, todėl tikrinam patys
        if (! $disk->put($path, $file->bytes)) {
            throw new RuntimeException("Nepavyko įrašyti {$path} į nuotraukų biblioteką ({$disk->path('')})");
        }

        [$width, $height] = [$file->width, $file->height];

        if ($resize) {
            $image = Image::load($disk->path($path))->fit(Fit::Max, $maxDimension, $maxDimension)->quality(85);
            $image->save($disk->path($path));
            [$width, $height] = [$image->getWidth(), $image->getHeight()];
        }

        $manifest[$name] = [
            'file' => $fileName,
            'stock_id' => $photo->stock->stockId(),
            'credit' => $photo->stock->credit->toArray(),
            'width' => $width,
            'height' => $height,
            'downloaded_at' => now()->toIso8601String(),
        ];
        $this->writeManifest($directory, $manifest);

        return new LibraryPhoto($name, $disk->path($path), $fileName, $photo->stock->stockId(), $photo->stock->credit);
    }

    /**
     * Ištrina visą katalogą (portfolio rinkinį su --force).
     */
    public function clear(string $directory): void
    {
        $this->disk()->deleteDirectory($directory);
    }

    /**
     * Visų bibliotekoje esančių nuotraukų ID (pexels:123, openverse:uuid) – kad ta pati nuotrauka nebūtų
     * parinkta dviem kategorijoms ar du kartus į portfolio rinkinį.
     *
     * @return array<string, true>
     */
    public function stockIds(): array
    {
        $ids = [];

        foreach ($this->disk()->allFiles() as $file) {
            if (basename($file) !== self::MANIFEST) {
                continue;
            }

            foreach ($this->manifest(dirname($file)) as $entry) {
                if (is_string($entry['stock_id'] ?? null)) {
                    $ids[$entry['stock_id']] = true;
                }
            }
        }

        return $ids;
    }

    /**
     * Demo portfolio rinkinys: 1 lygio slug => nuotraukos (tik sritys, kuriose yra bent viena).
     *
     * @return array<string, list<LibraryPhoto>>
     */
    public function pools(): array
    {
        $pools = [];

        foreach ($this->disk()->directories(self::PORTFOLIO) as $directory) {
            $photos = array_values($this->all($directory));

            if ($photos !== []) {
                $pools[basename($directory)] = $photos;
            }
        }

        ksort($pools);

        return $pools;
    }

    /**
     * Katalogo pavadinimas sričiai portfolio rinkinyje.
     */
    public static function poolDirectory(string $rootSlug): string
    {
        return self::PORTFOLIO.'/'.$rootSlug;
    }

    public function path(string $directory = ''): string
    {
        return $this->disk()->path($directory);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function manifest(string $directory): array
    {
        $json = $this->disk()->get($directory.'/'.self::MANIFEST);
        $data = $json === null ? null : json_decode($json, true);

        if (! is_array($data)) {
            return [];
        }

        return array_filter($data, fn (mixed $entry): bool => is_array($entry));
    }

    /**
     * @param  array<string, array<string, mixed>>  $manifest
     */
    private function writeManifest(string $directory, array $manifest): void
    {
        ksort($manifest);

        $this->disk()->put(
            $directory.'/'.self::MANIFEST,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
    }

    /**
     * Diskas kiekvieną kartą iš naujo: testuose Storage::fake() jį pakeičia netikru.
     */
    private function disk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk vietinis diskas (driver local) – jis turi path() */
        $disk = Storage::disk((string) config('photos.disk', 'stock-photos'));

        return $disk;
    }
}
