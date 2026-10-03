<?php

namespace App\Console\Commands;

use App\Actions\ServiceRequests\SendCompletionReminder;
use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Kasdien (Scheduler, routes/console.php) primena klientams apie užklausas, vykdomas jau 60+ d.
 * „Vykdoma nuo" = priimto pasiūlymo responded_at (atskiro accepted_at stulpelio nėra).
 * Rankiniu būdu: php artisan service-requests:remind-completion
 */
#[Signature('service-requests:remind-completion')]
#[Description('Primena klientams pažymėti darbą atliktu, kai užklausa vykdoma jau 60 ar daugiau dienų')]
class SendCompletionReminders extends Command
{
    public function handle(SendCompletionReminder $remind): int
    {
        $count = 0;

        ServiceRequest::query()
            ->where('status', ServiceRequestStatus::InProgress)
            ->whereNull('completion_reminded_at')
            ->whereHas('acceptedOffer', fn (Builder $query) => $query
                ->where('responded_at', '<=', now()->subDays(ServiceRequest::COMPLETION_REMINDER_AFTER_DAYS)))
            // chunkById – sąlyga (completion_reminded_at IS NULL) keičiasi cikle, todėl ne chunk() (žr. Etapo 5 klaidas)
            ->chunkById(200, function (Collection $requests) use ($remind, &$count): void {
                foreach ($requests as $serviceRequest) {
                    /** @var ServiceRequest $serviceRequest */
                    if ($remind->handle($serviceRequest)) {
                        $count++;
                    }
                }
            });

        $this->info("Išsiųsta priminimų: {$count}");

        return self::SUCCESS;
    }
}
