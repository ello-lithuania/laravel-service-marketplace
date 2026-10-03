<?php

namespace App\Http\Controllers\ServiceRequests;

use App\Actions\ServiceRequests\RequestCompletion;
use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Teikėjo mygtukas „Paprašyti pažymėti atliktu" (Etapas 6). Vienas veiksmas – __invoke.
 */
class CompletionRequestController extends Controller
{
    public function __invoke(ServiceRequest $serviceRequest, RequestCompletion $request): RedirectResponse
    {
        Gate::authorize('requestCompletion', $serviceRequest);

        $request->handle($serviceRequest);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('service_requests.completion.flash')]);

        return to_route('service-requests.show', $serviceRequest);
    }
}
