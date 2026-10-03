<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import CategoryTree from '@/components/account/CategoryTree.vue';
import ProviderWizard from '@/components/account/ProviderWizard.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { wizard as wizardRoute } from '@/routes/provider';
import { update } from '@/routes/provider/categories';
import type { CategoryNode, WizardStep } from '@/types';

const props = defineProps<{
    wizard: WizardStep[];
    tree: CategoryNode[];
    selected: number[];
    maxCategories: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Teikėjo profilis', href: wizardRoute() }],
    },
});

const form = useForm({
    category_ids: [...props.selected],
});

// Klaida gali būti ir apie konkretų elementą: „category_ids.3"
const error = computed(
    () =>
        form.errors.category_ids ??
        Object.entries(form.errors).find(([key]) =>
            key.startsWith('category_ids.'),
        )?.[1],
);

function submit(): void {
    form.put(update().url, { preserveScroll: true });
}
</script>

<template>
    <Head title="Teikėjo profilis – kategorijos" />

    <ProviderWizard
        :steps="wizard"
        current="categories"
        title="2. Kategorijos"
        description="Pažymėkite, kokias paslaugas teikiate. Pažymėję grupę (pvz. „Apdailos darbai“) gausite užklausas visose jos kategorijose."
    >
        <form class="space-y-6" @submit.prevent="submit">
            <CategoryTree
                v-model="form.category_ids"
                :tree="tree"
                :max="maxCategories"
            />
            <InputError :message="error" />

            <Button type="submit" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                Išsaugoti ir tęsti
            </Button>
        </form>
    </ProviderWizard>
</template>
