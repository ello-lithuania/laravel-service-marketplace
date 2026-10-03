<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProviderWizard from '@/components/account/ProviderWizard.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Spinner } from '@/components/ui/spinner';
import { wizard as wizardRoute } from '@/routes/provider';
import { update } from '@/routes/provider/areas';
import type { RegionOption, WizardStep } from '@/types';

const props = defineProps<{
    wizard: WizardStep[];
    regions: RegionOption[];
    selected: number[];
    servesWholeCountry: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Teikėjo profilis', href: wizardRoute() }],
    },
});

const form = useForm({
    serves_whole_country: props.servesWholeCountry,
    city_ids: [...props.selected],
});

const selectedSet = computed(() => new Set(form.city_ids));

function regionState(region: RegionOption): boolean | 'indeterminate' {
    const count = region.cities.filter((city) =>
        selectedSet.value.has(city.id),
    ).length;

    if (count === 0) {
        return false;
    }

    return count === region.cities.length ? true : 'indeterminate';
}

// Visa apskritis: pažymim arba nuimam visas jos savivaldybes iš karto
function toggleRegion(
    region: RegionOption,
    value: boolean | 'indeterminate',
): void {
    const ids = region.cities.map((city) => city.id);
    const others = form.city_ids.filter((id) => !ids.includes(id));

    form.city_ids = value === true ? [...others, ...ids] : others;
}

function toggleCity(id: number, value: boolean | 'indeterminate'): void {
    form.city_ids =
        value === true
            ? [...form.city_ids, id]
            : form.city_ids.filter((cityId) => cityId !== id);
}

function submit(): void {
    form.put(update().url, { preserveScroll: true });
}
</script>

<template>
    <Head title="Teikėjo profilis – aptarnavimo zonos" />

    <ProviderWizard
        :steps="wizard"
        current="areas"
        title="3. Aptarnavimo zonos"
        description="Kuriuose miestuose ir rajonuose dirbate? Užklausas gausite tik iš pažymėtų vietų."
    >
        <form class="space-y-6" @submit.prevent="submit">
            <label
                class="flex cursor-pointer items-start gap-3 rounded-lg border p-4"
                :class="{
                    'border-primary bg-primary/5': form.serves_whole_country,
                }"
            >
                <Checkbox
                    :model-value="form.serves_whole_country"
                    class="mt-0.5"
                    @update:model-value="
                        form.serves_whole_country = $event === true
                    "
                />
                <span>
                    <span class="block font-medium">Visa Lietuva</span>
                    <span class="text-sm text-muted-foreground">
                        Dirbu bet kurioje Lietuvos vietoje (pvz. nuotoliniai
                        darbai arba važiuoju visur).
                    </span>
                </span>
            </label>

            <div
                v-if="!form.serves_whole_country"
                class="grid gap-4 md:grid-cols-2"
            >
                <div
                    v-for="region in regions"
                    :key="region.id"
                    class="rounded-lg border p-4"
                >
                    <div class="mb-3 flex items-center gap-2">
                        <Checkbox
                            :id="`region-${region.id}`"
                            :model-value="regionState(region)"
                            @update:model-value="toggleRegion(region, $event)"
                        />
                        <label
                            :for="`region-${region.id}`"
                            class="flex-1 cursor-pointer font-medium"
                            >{{ region.name }}</label
                        >
                        <span class="text-xs text-muted-foreground">
                            {{
                                region.cities.filter((city) =>
                                    selectedSet.has(city.id),
                                ).length
                            }}/{{ region.cities.length }}
                        </span>
                    </div>
                    <div class="grid grid-cols-2 gap-x-3 gap-y-1.5 pl-6">
                        <div
                            v-for="city in region.cities"
                            :key="city.id"
                            class="flex items-center gap-2"
                        >
                            <Checkbox
                                :id="`city-${city.id}`"
                                :model-value="selectedSet.has(city.id)"
                                @update:model-value="
                                    toggleCity(city.id, $event)
                                "
                            />
                            <label
                                :for="`city-${city.id}`"
                                class="cursor-pointer text-sm"
                                >{{ city.name }}</label
                            >
                        </div>
                    </div>
                </div>
            </div>

            <InputError :message="form.errors.city_ids" />

            <Button type="submit" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                Išsaugoti ir tęsti
            </Button>
        </form>
    </ProviderWizard>
</template>
