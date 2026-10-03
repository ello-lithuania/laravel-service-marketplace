<?php

namespace App\Services\Privacy;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Kur laikomi BDAR eksporto archyvai: privatus diskas „local" (storage/app/private), NE „public".
 * Kelias data-exports/{user_id}/{failas}.zip: atsisiunčiant vartotojo ID imamas iš sesijos, ne iš URL,
 * todėl svetimo archyvo atsisiųsti neįmanoma net žinant failo vardą.
 * Archyvai saugomi RETENTION_DAYS dienų, paskui juos ištrina komanda privacy:prune-exports (Scheduler kasdien).
 */
final class DataExportStorage
{
    public const RETENTION_DAYS = 7;

    private const DIRECTORY = 'data-exports';

    /** Failo vardo formatas – atsisiuntimo maršrute leidžiam tik jį (jokių „../"). */
    public const FILE_PATTERN = '[0-9]{8}-[0-9]{6}-[a-z0-9]{12}\.zip';

    public function disk(): Filesystem
    {
        return Storage::disk('local');
    }

    public function newPath(User $user): string
    {
        return $this->directory($user).'/'.now()->format('Ymd-His').'-'.Str::lower(Str::random(12)).'.zip';
    }

    public function path(User $user, string $file): string
    {
        return $this->directory($user).'/'.basename($file);
    }

    /**
     * Vartotojo archyvai, naujausi pirmi.
     *
     * @return list<array{file: string, size: int, created_at: CarbonImmutable, expires_at: CarbonImmutable}>
     */
    public function list(User $user): array
    {
        $files = array_filter(
            $this->disk()->files($this->directory($user)),
            fn (string $path): bool => preg_match('#/'.self::FILE_PATTERN.'$#', $path) === 1,
        );

        $exports = array_map(function (string $path): array {
            // CarbonImmutable: addDays() grąžina naują objektą, o ne pakeičia created_at (klaida su paprastu Carbon)
            $createdAt = CarbonImmutable::createFromTimestamp($this->disk()->lastModified($path));

            return [
                'file' => basename($path),
                'size' => $this->disk()->size($path),
                'created_at' => $createdAt,
                'expires_at' => $createdAt->addDays(self::RETENTION_DAYS),
            ];
        }, array_values($files));

        usort($exports, fn (array $a, array $b): int => $b['created_at'] <=> $a['created_at']);

        return $exports;
    }

    /**
     * Ištrina senesnius nei RETENTION_DAYS archyvus. Grąžina ištrintų skaičių.
     */
    public function prune(): int
    {
        $deleted = 0;
        $threshold = now()->subDays(self::RETENTION_DAYS)->getTimestamp();

        foreach ($this->disk()->allFiles(self::DIRECTORY) as $path) {
            if ($this->disk()->lastModified($path) < $threshold) {
                $this->disk()->delete($path);
                $deleted++;
            }
        }

        return $deleted;
    }

    public function deleteAll(User $user): void
    {
        $this->disk()->deleteDirectory($this->directory($user));
    }

    private function directory(User $user): string
    {
        return self::DIRECTORY.'/'.$user->id;
    }
}
