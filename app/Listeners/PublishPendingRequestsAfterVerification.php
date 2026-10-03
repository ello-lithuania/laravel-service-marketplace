<?php

namespace App\Listeners;

use App\Actions\ServiceRequests\PublishServiceRequest;
use App\Enums\ServiceRequestStatus;
use App\Models\User;
use App\Services\Moderation\AutoModerator;
use Illuminate\Auth\Events\Verified;

/**
 * Klientas sukūrė užklausą dar nepatvirtinęs el. pašto – ji liko „pending".
 * Patvirtinus el. paštą, tokios užklausos dar kartą tikrinamos automatinėmis taisyklėmis ir, jei tinka, paskelbiamos.
 *
 * Event ≈ WordPress action hook: Laravel „paskelbia" įvykį Verified, o listener'is į jį reaguoja.
 * Laravel listener'ius kataloge app/Listeners randa pats (pagal handle() parametro tipą).
 * https://laravel.com/docs/13.x/events#event-discovery
 */
class PublishPendingRequestsAfterVerification
{
    public function __construct(
        private readonly AutoModerator $moderator,
        private readonly PublishServiceRequest $publish,
    ) {}

    public function handle(Verified $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || ! $user->isClient()) {
            return;
        }

        $pending = $user->serviceRequests()->where('status', ServiceRequestStatus::Pending)->get();

        foreach ($pending as $serviceRequest) {
            if ($this->moderator->issues($serviceRequest, $user) === []) {
                $this->publish->handle($serviceRequest);
            }
        }
    }
}
