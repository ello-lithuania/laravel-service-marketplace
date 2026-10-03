<?php

namespace App\Http\Controllers\Billing;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Billing\CreditPackageResource;
use App\Http\Resources\Billing\CreditTransactionResource;
use App\Http\Resources\Billing\PaymentResource;
use App\Http\Resources\Billing\SubscriptionResource;
use App\Models\CreditPackage;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Teikėjo „Kreditai": balansas, paketų pirkimas, prenumerata ir kreditų istorija (ledger).
 * Single action controller – vienas puslapis, vienas metodas.
 */
class CreditsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        // EnsureProviderProfileExists jau patikrino, kad profilis yra
        $provider = $user->providerProfile()->firstOrFail();

        $subscriptions = $provider->subscriptions()->live()->with('plan')->orderBy('starts_at')->get();
        $current = $subscriptions->first(fn (Subscription $subscription): bool => $subscription->starts_at->isPast());
        $scheduled = $subscriptions->first(fn (Subscription $subscription): bool => $subscription->isScheduled());

        // Laukiantis pratęsimo mokėjimas – rodom „Apmokėti pratęsimą"
        $renewal = $current === null ? null : Payment::query()
            ->with('purchasable')
            ->where('subscription_id', $current->id)
            ->where('status', PaymentStatus::Pending)
            ->latest('id')
            ->first();

        // Ledger istorija: indeksas provider_profile_id + PK, naujausi viršuje
        $transactions = $provider->creditTransactions()->latest('id')->paginate(20);

        return Inertia::render('billing/Credits', [
            'balance' => $provider->credits_balance,
            'lowCreditsThreshold' => (int) config('payments.low_credits_threshold'),
            'packages' => CreditPackageResource::collection(
                CreditPackage::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(),
            )->resolve(),
            'subscription' => $current === null ? null : SubscriptionResource::make($current)->resolve(),
            'scheduledSubscription' => $scheduled === null ? null : SubscriptionResource::make($scheduled)->resolve(),
            'renewalPayment' => $renewal === null ? null : PaymentResource::make($renewal)->resolve(),
            'transactions' => CreditTransactionResource::collection($transactions),
        ]);
    }
}
