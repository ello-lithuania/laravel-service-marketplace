<?php

namespace App\Actions\ServiceRequests;

use App\Models\ServiceRequest;
use Illuminate\Http\UploadedFile;

/**
 * Prideda nuotraukas prie užklausos (medialibrary kolekcija „photos", privatus diskas).
 * Kiek dar galima pridėti (iki ServiceRequest::MAX_PHOTOS), patikrina Form Request.
 */
class AddServiceRequestPhotos
{
    /**
     * @param  list<UploadedFile>  $photos
     */
    public function handle(ServiceRequest $serviceRequest, array $photos): ServiceRequest
    {
        foreach ($photos as $photo) {
            $serviceRequest->addMedia($photo)->toMediaCollection('photos');
        }

        return $serviceRequest;
    }
}
