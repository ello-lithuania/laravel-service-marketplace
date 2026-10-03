<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\DataExportReady;
use App\Services\Privacy\DataExportStorage;
use App\Services\Privacy\UserDataExporter;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use ZipArchive;

/**
 * BDAR duomenų eksportas eilėje: ZIP su duomenys.json, README.txt ir įkeltomis nuotraukomis.
 *
 * Kodėl eilėje, o ne iškart atsisiunčiant: aktyvaus teikėjo duomenų – tūkstančiai įrašų ir dešimtys nuotraukų,
 * archyvo kūrimas gali užtrukti ilgiau nei HTTP užklausos laikas (ir užimtų web procesą). Be to, nuoroda ateina
 * el. paštu – patvirtintu adresu, ne bet kam, kas tuo metu sėdi prie atidarytos sesijos.
 * ShouldBeUnique – kol vieno vartotojo archyvas ruošiamas, antras toks pat job'as į eilę nededamas.
 * https://laravel.com/docs/13.x/queues#unique-jobs
 */
class GenerateUserDataExport implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Kiek sekundžių laikomas „unikalumo" užraktas (jei job'as nukrito, po tiek laiko galima bandyti vėl). */
    public int $uniqueFor = 600;

    /** Jei kol job'as laukė eilėje paskyra buvo ištrinta – tiesiog jo nevykdom (ne klaida). */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public User $user) {}

    public function uniqueId(): string
    {
        return (string) $this->user->id;
    }

    public function handle(UserDataExporter $exporter, DataExportStorage $storage): void
    {
        $temporary = tempnam(sys_get_temp_dir(), 'export');

        if ($temporary === false) {
            throw new RuntimeException('Nepavyko sukurti laikino failo eksportui.');
        }

        try {
            $this->buildArchive($temporary, $exporter);

            // writeStream – failas į diską (ar S3) keliauja dalimis, neįkeliant viso archyvo į atmintį
            $stream = fopen($temporary, 'rb');

            if ($stream === false) {
                throw new RuntimeException('Nepavyko atidaryti eksporto archyvo.');
            }

            $storage->disk()->writeStream($storage->newPath($this->user), $stream);
            fclose($stream);
        } finally {
            @unlink($temporary);
        }

        $this->user->notify(new DataExportReady);
    }

    private function buildArchive(string $file, UserDataExporter $exporter): void
    {
        $zip = new ZipArchive;

        if ($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Nepavyko sukurti ZIP archyvo.');
        }

        $data = $exporter->collect($this->user);

        $zip->addFromString('duomenys.json', (string) json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));
        $zip->addFromString('README.txt', __('privacy.export.readme', [
            'app' => config('app.name'),
            'date' => now('Europe/Vilnius')->format('Y-m-d H:i'),
        ]));

        foreach ($exporter->media($this->user) as $media) {
            $stream = $media->stream();
            $contents = is_resource($stream) ? stream_get_contents($stream) : false;

            // Failo diske gali nebelikti (pvz. rankiniu būdu ištrintas) – tada jį praleidžiam, o ne nutraukiam eksportą
            if ($contents !== false) {
                $zip->addFromString($exporter->archivePath($media), $contents);
            }
        }

        $zip->close();
    }
}
