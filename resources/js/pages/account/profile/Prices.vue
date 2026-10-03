<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import ProviderWizard from '@/components/account/ProviderWizard.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { wizard as wizardRoute } from '@/routes/provider';
import { edit as editCategories } from '@/routes/provider/categories';
import { update } from '@/routes/provider/prices';
import type { SelectOption, WizardStep } from '@/types';

type CategoryPrice = {
    id: number;
    name: string;
    path: string;
    price_from: string | null;
    price_unit: string | null;
};

const props = defineProps<{
    wizard: WizardStep[];
    categories: CategoryPrice[];
    units: SelectOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Teikėjo profilis', href: wizardRoute() }],
    },
});

// Kainos įvedamos eurais („15,50"), serveris jas paverčia centais (1550)
const form = useForm({
    prices: props.categories.map((category) => ({
        category_id: category.id,
        price_from: category.price_from ?? '',
        price_unit: category.price_unit ?? '',
    })),
});

function error(
    index: number,
    field: 'price_from' | 'price_unit',
): string | undefined {
    return (form.errors as Record<string, string | undefined>)[
        `prices.${index}.${field}`
    ];
}

function submit(): void {
    form.put(update().url, { preserveScroll: true });
}
</script>

<template>
    <Head title="Teikėjo profilis – kainos" />

    <ProviderWizard
        :steps="wizard"
        current="prices"
        title="4. Kainos „nuo“"
        description="Neprivaloma, bet klientai dažniau renkasi teikėjus, kurie nurodo orientacines kainas. Palikite tuščią, jei kaina priklauso nuo darbo."
    >
        <p v-if="categories.length === 0" class="text-sm text-muted-foreground">
            Pirmiausia
            <Link :href="editCategories()" class="underline underline-offset-4"
                >pasirinkite kategorijas</Link
            >.
        </p>

        <form v-else class="space-y-6" @submit.prevent="submit">
            <div class="divide-y rounded-lg border">
                <div
                    v-for="(category, index) in categories"
                    :key="category.id"
                    class="grid items-start gap-3 p-3 sm:grid-cols-[1fr_9rem_9rem]"
                >
                    <div>
                        <p class="font-medium">{{ category.name }}</p>
                        <p
                            v-if="category.path"
                            class="text-xs text-muted-foreground"
                        >
                            {{ category.path }}
                        </p>
                    </div>
                    <div class="grid gap-1">
                        <div class="relative">
                            <Input
                                v-model="form.prices[index].price_from"
                                inputmode="decimal"
                                placeholder="nuo"
                                class="pr-7"
                                :aria-label="`${category.name}: kaina nuo, eurais`"
                            />
                            <span
                                class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm text-muted-foreground"
                                >€</span
                            >
                        </div>
                        <InputError :message="error(index, 'price_from')" />
                    </div>
                    <div class="grid gap-1">
                        <NativeSelect
                            v-model="form.prices[index].price_unit"
                            :aria-label="`${category.name}: kainos vienetas`"
                        >
                            <option value="">vienetas…</option>
                            <option
                                v-for="unit in units"
                                :key="unit.value"
                                :value="unit.value"
                            >
                                € / {{ unit.label }}
                            </option>
                        </NativeSelect>
                        <InputError :message="error(index, 'price_unit')" />
                    </div>
                </div>
            </div>

            <Button type="submit" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                Išsaugoti
            </Button>
        </form>
    </ProviderWizard>
</template>
