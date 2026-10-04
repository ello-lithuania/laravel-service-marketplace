<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Info, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import CategoryTree from '@/components/account/CategoryTree.vue';
import ProviderWizard from '@/components/account/ProviderWizard.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { plural } from '@/lib/format';
import { pricing } from '@/routes';
import { wizard as wizardRoute } from '@/routes/provider';
import { update } from '@/routes/provider/categories';
import type { CategoryLimit, CategoryNode, WizardStep } from '@/types';

const props = defineProps<{
    wizard: WizardStep[];
    tree: CategoryNode[];
    selected: number[];
    // Etapas 9c: riba pagal prenumeratą
    categoryLimit: CategoryLimit;
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

// --- Etapas 9c: kategorijų riba ---
// „iki 5 kategorijų", „iki 21 kategorijos" (kilmininkas)
const limitText = computed(() =>
    plural(props.categoryLimit.max, [
        'kategorijos',
        'kategorijų',
        'kategorijų',
    ]),
);
const selectedCount = computed(() => form.category_ids.length);
// Daugiau nei riba gali būti tik iš anksčiau (pvz. baigėsi prenumerata) – naujų pažymėti vedlys neleidžia
const overLimit = computed(() => selectedCount.value > props.categoryLimit.max);
const atLimit = computed(() => selectedCount.value === props.categoryLimit.max);
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
            <p
                class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground"
                data-test="category-limit"
            >
                <Info class="size-4 shrink-0" aria-hidden="true" />
                <span v-if="categoryLimit.plan">
                    Planas „{{ categoryLimit.plan }}“: iki {{ limitText }}.
                </span>
                <span v-else>
                    Be prenumeratos galite pasirinkti iki {{ limitText }}.
                </span>
                <span>Visa grupė skaičiuojama kaip viena.</span>
                <Link
                    v-if="categoryLimit.can_upgrade"
                    :href="pricing()"
                    class="font-medium text-foreground underline underline-offset-4"
                >
                    {{
                        categoryLimit.plan
                            ? 'Rinktis planą su daugiau kategorijų'
                            : 'Daugiau kategorijų – su prenumerata'
                    }}
                </Link>
            </p>

            <Alert v-if="overLimit" data-test="category-over-limit">
                <TriangleAlert class="text-amber-500" />
                <AlertTitle
                    >Kategorijų daugiau, nei leidžia jūsų planas</AlertTitle
                >
                <AlertDescription>
                    Pasirinkta {{ selectedCount }}, o riba –
                    {{ categoryLimit.max }}. Esamas kategorijas galite palikti:
                    užklausas jose gausite ir toliau. Naujos pažymėti
                    negalėsite, kol iš viso jų bus daugiau nei
                    {{ categoryLimit.max }} – pirmiausia pašalinkite
                    nereikalingas.
                    <Link
                        v-if="categoryLimit.can_upgrade"
                        :href="pricing()"
                        class="font-medium text-foreground underline underline-offset-4"
                        >Peržiūrėti planus</Link
                    >
                </AlertDescription>
            </Alert>

            <CategoryTree
                v-model="form.category_ids"
                :tree="tree"
                :max="categoryLimit.max"
                :initial="selected"
            />
            <p
                v-if="atLimit"
                class="text-sm text-muted-foreground"
                data-test="category-at-limit"
            >
                Pasiekėte plano ribą. Norėdami pažymėti kitą kategoriją,
                pirmiausia pašalinkite kurią nors.
            </p>
            <InputError :message="error" />

            <Button type="submit" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                Išsaugoti ir tęsti
            </Button>
        </form>
    </ProviderWizard>
</template>
