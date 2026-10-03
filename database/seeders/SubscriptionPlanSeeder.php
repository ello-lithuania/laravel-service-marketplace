<?php

namespace Database\Seeders;

use App\Enums\BillingPeriod;
use App\Models\SubscriptionPlan;
use Database\Seeders\Support\ReferenceData;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ReferenceData::monetization()['subscription_plans'] as $sort => $plan) {
            SubscriptionPlan::query()->updateOrCreate(['slug' => $plan['slug']], [
                ...$plan,
                'billing_period' => BillingPeriod::Month,
                'is_active' => true,
                'sort_order' => $sort,
            ]);
        }
    }
}
