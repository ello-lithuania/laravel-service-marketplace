// Etapas 7: „Pirkti" mygtukai kainų ir kreditų puslapiuose.
// Serveris sukuria laukiantį mokėjimą ir atsako Inertia::location – naršyklė visu langu atidaro
// mokėjimų tiekėjo (Paysera arba testinį) puslapį. Klaida (pvz. planas jau turimas) grįžta kaip errors.purchase.

import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { purchase as purchasePackage } from '@/routes/credits';
import { purchase as purchasePlan } from '@/routes/subscriptions';

export function usePurchase() {
    const page = usePage();
    const processing = ref<string | null>(null);

    const error = computed(() => {
        const errors = page.props.errors as Record<string, string | undefined>;

        return errors.purchase ?? null;
    });

    function post(key: string, url: string): void {
        router.post(
            url,
            {},
            {
                preserveScroll: true,
                onStart: () => (processing.value = key),
                onFinish: () => (processing.value = null),
            },
        );
    }

    return {
        processing,
        error,
        buyPackage: (id: number) =>
            post(`package-${id}`, purchasePackage(id).url),
        buyPlan: (slug: string) => post(`plan-${slug}`, purchasePlan(slug).url),
    };
}
