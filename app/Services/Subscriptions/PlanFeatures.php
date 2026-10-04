<?php

namespace App\Services\Subscriptions;

/**
 * Plano privalumai iš subscription_plans.features (JSON), paversti tipizuotu objektu.
 *
 * Kodėl ne tiesiog masyvas: JSON gali būti NULL, be rakto ar su netikėtu tipu ("30" vietoj 30). Vienoje vietoje
 * nusprendžiam, ką tai reiškia, o kitur rašom $features->badge, o ne ($plan->features['badge'] ?? false) === true.
 * Raktai: {"max_categories": 30, "badge": true, "priority_support": true} (database/data/monetization.php).
 */
final readonly class PlanFeatures
{
    public function __construct(
        /** Kategorijų riba; null – plane nenurodyta (galioja nemokama riba) */
        public ?int $maxCategories = null,
        /** Ženklelis „PRO" profilyje ir katalogo kortelėse */
        public bool $badge = false,
        public bool $prioritySupport = false,
    ) {}

    /**
     * @param  array<array-key, mixed>|null  $features
     */
    public static function fromArray(?array $features): self
    {
        $max = $features['max_categories'] ?? null;

        return new self(
            maxCategories: is_numeric($max) && (int) $max > 0 ? (int) $max : null,
            badge: ($features['badge'] ?? false) === true,
            prioritySupport: ($features['priority_support'] ?? false) === true,
        );
    }
}
