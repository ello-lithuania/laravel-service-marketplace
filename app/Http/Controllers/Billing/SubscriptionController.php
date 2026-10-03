<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Subscriptions\CancelSubscription;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Teikėjas atšaukia savo prenumeratą: ji galioja iki apmokėto laikotarpio pabaigos ir nebepratęsiama.
 */
class SubscriptionController extends Controller
{
    public function cancel(Subscription $subscription, CancelSubscription $cancel): RedirectResponse
    {
        Gate::authorize('cancel', $subscription);

        $cancel->handle($subscription);

        $message = $subscription->status === SubscriptionStatus::Cancelled
            ? __('billing.flash.subscription_cancelled', ['date' => $subscription->ends_at->timezone('Europe/Vilnius')->format('Y-m-d')])
            : __('billing.flash.subscription_ended');

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('credits.index');
    }
}
