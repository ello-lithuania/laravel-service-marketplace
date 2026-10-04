// Etapas 9c: prenumeratų privalumai (App\Services\Subscriptions\PlanBenefits).

/** Kategorijų riba vedlyje (ProviderCategoriesController::edit → categoryLimit) */
export type CategoryLimit = {
    /** Kiek kategorijų leidžia planas (be prenumeratos – config marketplace.free_max_categories) */
    max: number;
    /** Dabartinio plano pavadinimas; null – be prenumeratos */
    plan: string | null;
    /** Ar parduodamas planas su didesne riba (rodom nuorodą į /kainos) */
    can_upgrade: boolean;
};
