<?php

namespace App\Http\Controllers\ServiceRequests;

use App\Actions\ServiceRequests\AddServiceRequestPhotos;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequests\StoreServiceRequestPhotosRequest;
use App\Models\ServiceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Užklausos nuotraukos (Etapas 6): klientas prideda ar pašalina, kol užklausa laukia patvirtinimo ar pasiūlymų.
 */
class ServiceRequestPhotoController extends Controller
{
    public function store(StoreServiceRequestPhotosRequest $request, ServiceRequest $serviceRequest, AddServiceRequestPhotos $add): RedirectResponse
    {
        $add->handle($serviceRequest, $request->photos());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('service_requests.flash.photos_added')]);

        return back();
    }

    /**
     * scopeBindings() maršrute: {media} ieškomas tik tarp šios užklausos failų (svetimos nuotraukos ID – 404).
     */
    public function destroy(ServiceRequest $serviceRequest, Media $media): RedirectResponse
    {
        Gate::authorize('updatePhotos', $serviceRequest);
        abort_unless($media->collection_name === 'photos', 404);

        $media->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('service_requests.flash.photo_deleted')]);

        return back();
    }
}
