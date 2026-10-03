<?php

namespace App\Http\Controllers\Complaints;

use App\Actions\Complaints\FileComplaint;
use App\Http\Controllers\Controller;
use App\Http\Requests\Complaints\StoreComplaintRequest;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * „Pranešti apie pažeidimą": skundas dėl užklausos, pasiūlymo, atsiliepimo, žinutės ar teikėjo profilio.
 * Dažnio riba – throttle:complaints maršrute.
 */
class ComplaintController extends Controller
{
    public function store(StoreComplaintRequest $request, FileComplaint $file): RedirectResponse
    {
        $reportable = $request->reportable();
        // Matai įrašą ir jis ne tavo? (ComplaintPolicy::create – 404 arba 403 su priežastimi)
        Gate::authorize('create', [Complaint::class, $reportable]);

        /** @var User $user */
        $user = $request->user();

        $file->handle($user, $reportable, $request->reason(), $request->description());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('complaints.flash.created')]);

        return back();
    }
}
