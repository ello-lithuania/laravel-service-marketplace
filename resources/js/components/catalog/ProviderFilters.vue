<script setup lang="ts">
import { computed } from 'vue';
import NativeSelect from '@/components/catalog/NativeSelect.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { CatalogFilters, CityOption, SortOption } from '@/types';

// Filtrų juosta. Pati nenaršo – praneša puslapiui naujas reikšmes (change), o puslapis žino,
// kokį URL sudaryti (kategorijos puslapyje miestas – URL dalis /paslaugos/{kategorija}/{miestas}).
const props = defineProps<{
    filters: CatalogFilters;
    cities: CityOption[];
    sortOptions: SortOption[];
}>();

const emit = defineEmits<{ change: [filters: CatalogFilters] }>();

const ratingOptions = [
    { value: '4.5', label: '4,5 ★ ir daugiau' },
    { value: '4', label: '4 ★ ir daugiau' },
    { value: '3', label: '3 ★ ir daugiau' },
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
    <div
        class="grid gap-3 rounded-lg border bg-muted/40 p-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_auto_1fr] lg:items-center"
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
            <option value="">Bet koks įvertinimas</option>
            <option
                v-for="option in ratingOptions"
                :key="option.value"
                :value="option.value"
            >
                {{ option.label }}
            </option>
        </NativeSelect>

        <div class="flex h-9 items-center gap-2 px-1">
            <Checkbox
                id="filter-verified"
                :model-value="filters.patikrinti"
                @update:model-value="update({ patikrinti: $event === true })"
            />
            <Label for="filter-verified" class="font-normal whitespace-nowrap"
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
</template>
