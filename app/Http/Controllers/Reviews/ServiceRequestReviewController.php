<?php

namespace App\Http\Controllers\Reviews;

use App\Actions\Reviews\CreateVerifiedReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reviews\StoreVerifiedReviewRequest;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Patvirtintas atsiliepimas: klientas įvertina per platformą atliktą darbą (forma – užklausos puslapyje).
 */
class ServiceRequestReviewController extends Controller
{
    public function store(StoreVerifiedReviewRequest $request, ServiceRequest $serviceRequest, CreateVerifiedReview $create): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $create->handle($serviceRequest, $user, $request->rating(), $request->comment());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('reviews.flash.verified_created')]);

        return to_route('service-requests.show', $serviceRequest);
    }
}
