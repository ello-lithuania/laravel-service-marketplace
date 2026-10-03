<?php

namespace App\Console\Commands;

use App\Actions\ServiceRequests\ExpireServiceRequest;
use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Uždaro atviras užklausas, kurių expires_at jau praėjo (open → expired). Paleidžia Scheduler kas valandą
 * (routes/console.php), rankiniu būdu: php artisan service-requests:expire
 *
 * Kiekviena užklausa – atskira DB transakcija (ExpireServiceRequest): viena nepavykusi nesugriauna kitų,
 * o užraktai laikomi trumpai.
 */
#[Signature('service-requests:expire')]
#[Description('Uždaro pasibaigusias atviras užklausas ir grąžina kreditus už neatidarytus pasiūlymus')]
class ExpireServiceRequests extends Command
{
    public function handle(ExpireServiceRequest $expire): int
    {
        $count = 0;

        // Indeksas (status, expires_at) – Etapas 8; chunkById – dalimis, kad neužkrautume visų iš karto
        ServiceRequest::query()
            ->where('status', ServiceRequestStatus::Open)
            ->where('expires_at', '<=', now())
            ->chunkById(200, function (Collection $requests) use ($expire, &$count): void {
                foreach ($requests as $serviceRequest) {
                    /** @var ServiceRequest $serviceRequest */
                    if ($expire->handle($serviceRequest)) {
                        $count++;
                    }
                }
            });

        $this->info("Uždaryta pasibaigusių užklausų: {$count}");

        return self::SUCCESS;
    }
}
