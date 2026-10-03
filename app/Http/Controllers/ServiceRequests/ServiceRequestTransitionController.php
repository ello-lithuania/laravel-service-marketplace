<?php

namespace App\Http\Controllers\ServiceRequests;

use App\Actions\ServiceRequests\CancelServiceRequest;
use App\Actions\ServiceRequests\CompleteServiceRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequests\CancelServiceRequestRequest;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Užklausos būsenų perėjimai, kuriuos inicijuoja klientas: atšaukti ir pažymėti atliktą (docs/STATES.md 1 sk.).
 */
class ServiceRequestTransitionController extends Controller
{
    public function cancel(CancelServiceRequestRequest $request, ServiceRequest $serviceRequest, CancelServiceRequest $cancel): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $cancel->handle($serviceRequest, $user, $request->string('reason')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('service_requests.flash.cancelled')]);

        return to_route('service-requests.show', $serviceRequest);
    }

    public function complete(Request $request, ServiceRequest $serviceRequest, CompleteServiceRequest $complete): RedirectResponse
    {
        Gate::authorize('complete', $serviceRequest);

        $complete->handle($serviceRequest);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('service_requests.flash.completed')]);

        return to_route('service-requests.show', $serviceRequest);
    }
}
