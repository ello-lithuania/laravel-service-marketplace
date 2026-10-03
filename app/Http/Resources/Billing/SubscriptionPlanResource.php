<?php

namespace App\Http\Resources\Billing;

use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Prenumeratos planas kainų puslapiui. features (JSON) paverčiamos lietuviškais punktais.
 *
 * @property SubscriptionPlan $resource
 */
class SubscriptionPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $plan = $this->resource;

        return [
            'id' => $plan->id,
            'slug' => $plan->slug,
            'name' => $plan->name,
            'description' => $plan->description,
            'price_cents' => $plan->price_cents,
            'billing_period' => [
                'value' => $plan->billing_period->value,
                'label' => $plan->billing_period->label(),
                'per' => $plan->billing_period->perLabel(),
            ],
            'credits_per_period' => $plan->credits_per_period,
            'price_per_credit_cents' => $plan->credits_per_period > 0 ? (int) round($plan->price_cents / $plan->credits_per_period) : null,
            'features' => self::featureLines($plan),
        ];
    }

    /**
     * {"max_categories": 30, "badge": true} → ["Iki 30 paslaugų kategorijų", "Ženklelis profilyje"].
     *
     * @return list<string>
     */
    public static function featureLines(SubscriptionPlan $plan): array
    {
        $features = $plan->features ?? [];
        $lines = [__('billing.plan_features.credits', ['credits' => $plan->credits_per_period, 'period' => $plan->billing_period->perLabel()])];

        if (isset($features['max_categories']) && is_numeric($features['max_categories'])) {
            $lines[] = __('billing.plan_features.max_categories', ['count' => (int) $features['max_categories']]);
        }

        if (($features['badge'] ?? false) === true) {
            $lines[] = __('billing.plan_features.badge');
        }

        if (($features['priority_support'] ?? false) === true) {
            $lines[] = __('billing.plan_features.priority_support');
        }

        return $lines;
    }
}
