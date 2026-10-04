<script setup lang="ts">
import { Star } from '@lucide/vue';
import { computed } from 'vue';
import NativeSelect from '@/components/catalog/NativeSelect.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { CatalogFilters, CityOption, SortOption } from '@/types';

// Filtrai. Patys nenaršo – praneša puslapiui naujas reikšmes (change), o puslapis žino,
// kokį URL sudaryti (kategorijos puslapyje miestas – URL dalis /paslaugos/{kategorija}/{miestas}).
// Etapas 10: layout „stack" – stulpelis šoninėje juostoje (kompiuteryje) ir Sheet skydelyje (telefone).
// idPrefix – kad du egzemplioriai (šoninė juosta ir skydelis) neturėtų vienodų id.
const props = withDefaults(
    defineProps<{
        filters: CatalogFilters;
        cities: CityOption[];
        sortOptions: SortOption[];
        layout?: 'bar' | 'stack';
        idPrefix?: string;
    }>(),
    { layout: 'bar', idPrefix: 'filter' },
);

const emit = defineEmits<{ change: [filters: CatalogFilters] }>();

const ratingOptions = [
    { value: '', label: 'Bet koks', short: 'Bet koks' },
    { value: '4.5', label: '4,5 ir daugiau', short: '4,5+' },
    { value: '4', label: '4 ir daugiau', short: '4+' },
    { value: '3', label: '3 ir daugiau', short: '3+' },
];

function update(patch: Partial<CatalogFilters>): void {
    emit('change', { ...props.filters, ...patch });
}

// computed su get/set: reikšmė visada ateina iš serverio (props), o pakeitimas iškart siunčiamas
const city = computed({
    get: () => props.filters.miestas ?? '',
    set: (value: string) => update({ miestas: value || null }),
});

const rating = computed({
    get: () => props.filters.reitingas?.toString() ?? '',
    set: (value: string) => update({ reitingas: value ? Number(value) : null }),
});

const sort = computed({
    get: () => props.filters.rikiuoti,
    set: (value: string) => update({ rikiuoti: value }),
});
</script>

<template>
    <!-- Juosta (seniau naudotas išdėstymas) -->
    <div
        v-if="layout === 'bar'"
        class="grid gap-3 rounded-xl border bg-card p-3 shadow-soft sm:grid-cols-2 lg:grid-cols-[1fr_1fr_auto_1fr] lg:items-center"
    >
        <NativeSelect v-model="city" aria-label="Miestas ar rajonas">
            <option value="">Visa Lietuva</option>
            <option
                v-for="option in cities"
                :key="option.slug"
                :value="option.slug"
            >
                {{ option.name }}
            </option>
        </NativeSelect>

        <NativeSelect v-model="rating" aria-label="Įvertinimas">
            <option
                v-for="option in ratingOptions"
                :key="option.value"
                :value="option.value"
            >
                {{
                    option.value ? `${option.label} ★` : 'Bet koks įvertinimas'
                }}
            </option>
        </NativeSelect>

        <div class="flex h-9 items-center gap-2 px-1">
            <Checkbox
                :id="`${idPrefix}-verified`"
                :model-value="filters.patikrinti"
                @update:model-value="update({ patikrinti: $event === true })"
            />
            <Label
                :for="`${idPrefix}-verified`"
                class="font-normal whitespace-nowrap"
                >Tik patikrinti</Label
            >
        </div>

        <NativeSelect v-model="sort" aria-label="Rikiavimas">
            <option
                v-for="option in sortOptions"
                :key="option.value"
                :value="option.value"
            >
                {{ option.label }}
            </option>
        </NativeSelect>
    </div>

    <!-- Stulpelis -->
    <div v-else class="space-y-6">
        <div class="space-y-2">
            <Label :for="`${idPrefix}-city`">Miestas ar rajonas</Label>
            <NativeSelect :id="`${idPrefix}-city`" v-model="city">
                <option value="">Visa Lietuva</option>
                <option
                    v-for="option in cities"
                    :key="option.slug"
                    :value="option.slug"
                >
                    {{ option.name }}
                </option>
            </NativeSelect>
        </div>

        <fieldset class="space-y-2">
            <legend class="mb-2 text-sm leading-none font-medium">
                Įvertinimas
            </legend>
            <div class="grid grid-cols-2 gap-1.5">
                <label
                    v-for="option in ratingOptions"
                    :key="option.value"
                    class="flex cursor-pointer items-center justify-center gap-1 rounded-lg border bg-card px-2 py-2 text-sm transition-colors hover:border-primary/40 has-checked:border-primary has-checked:bg-secondary has-checked:font-medium has-checked:text-secondary-foreground has-focus-visible:ring-[3px] has-focus-visible:ring-ring/40"
                >
                    <input
                        v-model="rating"
                        type="radio"
                        :name="`${idPrefix}-rating`"
                        :value="option.value"
                        class="sr-only"
                    />
                    <Star
                        v-if="option.value"
                        class="size-3.5 fill-star text-star"
                        aria-hidden="true"
                    />
                    <span aria-hidden="true">{{ option.short }}</span>
                    <span class="sr-only">{{ option.label }}</span>
                </label>
            </div>
        </fieldset>

        <div
            class="flex items-start gap-3 rounded-lg border bg-card p-3 transition-colors has-[[data-state=checked]]:border-primary/40 has-[[data-state=checked]]:bg-secondary/60"
        >
            <Checkbox
                :id="`${idPrefix}-verified`"
                class="mt-0.5"
                :model-value="filters.patikrinti"
                @update:model-value="update({ patikrinti: $event === true })"
            />
            <Label
                :for="`${idPrefix}-verified`"
                class="grid gap-1 leading-snug font-normal"
            >
                <span class="font-medium">Tik patikrinti teikėjai</span>
                <span class="text-xs text-muted-foreground"
                    >Duomenis patikrino administracija</span
                >
            </Label>
        </div>

        <div class="space-y-2">
            <Label :for="`${idPrefix}-sort`">Rikiuoti pagal</Label>
            <NativeSelect :id="`${idPrefix}-sort`" v-model="sort">
                <option
                    v-for="option in sortOptions"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </NativeSelect>
        </div>
    </div>
</template>
