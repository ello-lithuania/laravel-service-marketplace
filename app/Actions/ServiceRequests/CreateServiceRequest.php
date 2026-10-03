<?php

namespace App\Actions\ServiceRequests;

use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Moderation\AutoModerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Klientas sukuria užklausą: (nauja) → pending, o jei praeina automatinis moderavimas – iškart pending → open.
 *
 * Action klasė = viena verslo operacija su vienu viešu metodu handle(). Controller'is lieka plonas,
 * o tą pačią operaciją galima kviesti iš testų, komandų ar job'ų.
 */
class CreateServiceRequest
{
    public function __construct(
        private readonly AutoModerator $moderator,
        private readonly PublishServiceRequest $publish,
        private readonly AddServiceRequestPhotos $addPhotos,
    ) {}

    /**
     * @param  array{category_id: int, city_id: int, title: string, description: string, address: ?string,
     *     budget_min_cents: ?int, budget_max_cents: ?int, start_preference: string, start_date: ?string}  $attributes
     * @param  list<UploadedFile>  $photos  Etapas 6: nuotraukos iš formos (neprivalomos)
     */
    public function handle(User $client, array $attributes, array $photos = []): ServiceRequest
    {
        $serviceRequest = new ServiceRequest([...$attributes, 'slug' => $this->uniqueSlug($attributes['title'])]);
        $serviceRequest->client()->associate($client);
        // status nėra Fillable – jį keičia tik būsenų perėjimai
        $serviceRequest->forceFill(['status' => ServiceRequestStatus::Pending])->save();

        // Nuotraukos – prieš paskelbiant, kad teikėjai, gavę pranešimą, jas jau matytų
        $this->addPhotos->handle($serviceRequest, $photos);

        if ($this->moderator->issues($serviceRequest, $client) === []) {
            $this->publish->handle($serviceRequest);
        }

        return $serviceRequest;
    }

    /**
     * „Plytelių klijavimas vonioje" → „plyteliu-klijavimas-vonioje-k3x9". Atsitiktinė galūnė leidžia turėti
     * daug vienodų pavadinimų, o URL neatskleidžia, kiek užklausų yra (kaip atskleistų ID).
     */
    private function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title), 160, '') ?: 'uzklausa';

        do {
            $slug = $base.'-'.Str::lower(Str::random(5));
        } while (ServiceRequest::withTrashed()->where('slug', $slug)->exists());

        return $slug;
    }
}
