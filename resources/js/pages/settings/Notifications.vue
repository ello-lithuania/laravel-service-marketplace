<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { edit, update } from '@/routes/notification-settings';

type Group = {
    key: string;
    label: string;
    description: string;
    channels: { mail: boolean; database: boolean };
};

const props = defineProps<{
    groups: Group[];
    emailVerified: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Pranešimų nustatymai', href: edit() }],
    },
});

// { settings: { new_requests: { mail: true, database: true }, … } } – tokią struktūrą tikrina Form Request
const form = useForm({
    settings: Object.fromEntries(
        props.groups.map((group) => [group.key, { ...group.channels }]),
    ) as Record<string, { mail: boolean; database: boolean }>,
});

function submit(): void {
    form.submit(update(), { preserveScroll: true });
}
</script>

<template>
    <Head title="Pranešimų nustatymai" />

    <h1 class="sr-only">Pranešimų nustatymai</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Pranešimai"
            description="Pasirinkite, apie ką pranešti el. paštu ir varpelyje svetainėje"
        />

        <p
            v-if="!emailVerified"
            class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
        >
            El. laiškus pradėsime siųsti, kai patvirtinsite el. pašto adresą.
        </p>

        <form class="space-y-6" @submit.prevent="submit">
            <div class="overflow-hidden rounded-lg border">
                <div
                    class="grid grid-cols-[1fr_5rem_5rem] gap-2 bg-muted/50 px-4 py-2 text-xs font-medium text-muted-foreground"
                >
                    <span>Pranešimas</span>
                    <span class="text-center">El. paštu</span>
                    <span class="text-center">Varpelyje</span>
                </div>
                <div
                    v-for="group in groups"
                    :key="group.key"
                    class="grid grid-cols-[1fr_5rem_5rem] items-center gap-2 border-t px-4 py-3"
                >
                    <div>
                        <p class="text-sm font-medium">{{ group.label }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ group.description }}
                        </p>
                    </div>
                    <div class="flex justify-center">
                        <Checkbox
                            v-model="form.settings[group.key].mail"
                            :aria-label="`${group.label}: el. paštu`"
                            :data-test="`${group.key}-mail`"
                        />
                    </div>
                    <div class="flex justify-center">
                        <Checkbox
                            v-model="form.settings[group.key].database"
                            :aria-label="`${group.label}: varpelyje`"
                        />
                    </div>
                </div>
            </div>

            <!-- Etapas 9c: neišjungiami pranešimai (IgnoresNotificationSettings, ComplaintResolved) -->
            <p class="text-xs text-muted-foreground">
                Svarbūs pranešimai apie jūsų paskyrą (pvz. administratoriaus
                pakeista profilio būsena ar išnagrinėtas skundas) siunčiami
                visada.
            </p>

            <Button
                :disabled="form.processing"
                data-test="save-notification-settings"
            >
                Išsaugoti
            </Button>
        </form>
    </div>
</template>
