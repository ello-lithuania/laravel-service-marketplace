<?php

namespace App\Jobs;

use App\Enums\ServiceRequestStatus;
use App\Models\ProviderProfile;
use App\Models\ServiceRequest;
use App\Notifications\NewMatchingRequest;
use App\Services\Matching\ProviderMatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Paskelbus užklausą, praneša visiems tinkamiems teikėjams (atitikimo taisyklė – ProviderMatcher).
 *
 * Job, nes teikėjų gali būti šimtai: klientas neturi laukti, kol visi pranešimai bus sukurti.
 * Teikėjai skaitomi dalimis po CHUNK (chunkById), kad atmintyje nebūtų tūkstančių modelių vienu metu.
 * https://laravel.com/docs/13.x/queues · https://laravel.com/docs/13.x/eloquent#chunking-results
 */
class NotifyMatchingProviders implements ShouldQueue
{
    use Queueable;

    public const CHUNK = 500;

    /** Kiek kartų bandyti, jei job'as nepavyko (pvz. DB trumpam nepasiekiama). */
    public int $tries = 3;

    /**
     * Queueable trait'e yra SerializesModels: į eilę įrašomas tik užklausos ID, o vykdant modelis paimamas iš DB.
     */
    public function __construct(public ServiceRequest $serviceRequest) {}

    public function handle(ProviderMatcher $matcher): void
    {
        // Kol job'as laukė eilėje, užklausa galėjo būti atšaukta
        if ($this->serviceRequest->status !== ServiceRequestStatus::Open) {
            return;
        }

        $matcher->eligibleProviders($this->serviceRequest)
            ->select(['provider_profiles.id', 'provider_profiles.user_id'])
            ->with('user')
            ->chunkById(self::CHUNK, function (Collection $providers): void {
                /** @var Collection<int, ProviderProfile> $providers */
                // NewMatchingRequest yra ShouldQueue: kiekvienam gavėjui – atskiras mažas job'as eilėje
                Notification::send($providers->pluck('user'), new NewMatchingRequest($this->serviceRequest));
            }, 'provider_profiles.id', 'id');
    }
}
