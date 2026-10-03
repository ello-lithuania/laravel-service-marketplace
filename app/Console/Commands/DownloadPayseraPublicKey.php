<?php

namespace App\Console\Commands;

use App\Services\Payments\Paysera\PayseraPublicKey;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Parsiunčia Paysera viešąjį raktą (ss2 parašui tikrinti) ir įrašo į config('payments.paysera.public_key_path').
 * Paleisti vieną kartą diegiant (deploy) – tada callback'ai rakto neparsiunčia patys.
 */
#[Signature('payments:paysera-key')]
#[Description('Parsiunčia Paysera viešąjį raktą callback\'ų parašams tikrinti')]
class DownloadPayseraPublicKey extends Command
{
    public function handle(PayseraPublicKey $publicKey): int
    {
        if (! $publicKey->store()) {
            $this->error('Nepavyko parsisiųsti Paysera viešojo rakto (patikrinkite PAYSERA_PUBLIC_KEY_URL ir interneto ryšį).');

            return self::FAILURE;
        }

        $this->info('Paysera viešasis raktas išsaugotas: '.$publicKey->path());

        return self::SUCCESS;
    }
}
