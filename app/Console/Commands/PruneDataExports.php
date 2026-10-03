<?php

namespace App\Console\Commands;

use App\Services\Privacy\DataExportStorage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Ištrina senus BDAR archyvus (duomenų minimizavimas: kopija su visais asmens duomenimis neturi gulėti amžinai).
 * Scheduler paleidžia kasdien (routes/console.php).
 */
#[Signature('privacy:prune-exports')]
#[Description('Ištrina senesnius nei 7 d. BDAR duomenų archyvus')]
class PruneDataExports extends Command
{
    public function handle(DataExportStorage $storage): int
    {
        $deleted = $storage->prune();

        $this->info("Ištrinta archyvų: {$deleted}");

        return self::SUCCESS;
    }
}
