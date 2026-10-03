<?php

namespace App\Http\Resources\Billing;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Teikėjo prenumerata kreditų puslapyje. plan ryšys turi būti užkrautas.
 *
 * @property Subscription $resource
 */
class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $subscription = $this->resource;

        return [
            'id' => $subscription->id,
            'plan' => [
                'name' => $subscription->plan->name,
                'slug' => $subscription->plan->slug,
                'credits_per_period' => $subscription->plan->credits_per_period,
                'price_cents' => $subscription->plan->price_cents,
                'period_label' => $subscription->plan->billing_period->perLabel(),
            ],
            'status' => ['value' => $subscription->status->value, 'label' => $subscription->status->label()],
            'starts_at' => $subscription->starts_at->toIso8601String(),
            'ends_at' => $subscription->ends_at->toIso8601String(),
            'auto_renew' => $subscription->auto_renew,
            'is_scheduled' => $subscription->isScheduled(),
            'can_cancel' => $request->user()?->can('cancel', $subscription) ?? false,
        ];
    }
}
