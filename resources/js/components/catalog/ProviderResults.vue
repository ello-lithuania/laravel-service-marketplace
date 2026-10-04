<script setup lang="ts">
import { SearchX, SlidersHorizontal, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import CatalogPagination from '@/components/catalog/CatalogPagination.vue';
import ProviderCard from '@/components/catalog/ProviderCard.vue';
import ProviderFilters from '@/components/catalog/ProviderFilters.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { formatRating, plural } from '@/lib/format';
import type {
    CatalogFilters,
    CityOption,
    Paginated,
    ProviderCard as ProviderCardType,
    SortOption,
} from '@/types';

// Etapas 10: teikėjų sąrašas su filtrais – vienodas kategorijos, /meistrai ir paieškos puslapiuose.
// Kompiuteryje filtrai šoninėje juostoje, telefone – skydelyje (Sheet) po mygtuku „Filtrai".
// Pats nenaršo: filtrų pakeitimus perduoda puslapiui (change), o šis žino, kokį URL sudaryti.
const props = withDefaults(
    defineProps<{
        providers: Paginated<ProviderCardType>;
        filters: CatalogFilters;
        cities: CityOption[];
        sortOptions: SortOption[];
        title: string;
        createRequestUrl?: string;
        emptyTitle?: string;
        emptyText?: string;
    }>(),
    {
        createRequestUrl: '/uzklausos/nauja',
        emptyTitle: 'Pagal pasirinktus filtrus teikėjų neradome',
        emptyText:
            'Aprašykite darbą – užklausą pamatys visi tinkami teikėjai, ir jie patys atsiųs pasiūlymus.',
    },
);

const emit = defineEmits<{ change: [filters: CatalogFilters] }>();

const sheetOpen = ref(false);

const cityName = computed(
    () =>
        props.cities.find((city) => city.slug === props.filters.miestas)
            ?.name ?? null,
);

// Aktyvūs filtrai – „čipai" virš sąrašo, kuriuos galima nuimti vienu paspaudimu
const activeFilters = computed(() => {
    const chips: {
        key: string;
        label: string;
        patch: Partial<CatalogFilters>;
    }[] = [];

    if (cityName.value) {
        chips.push({
            key: 'city',
            label: cityName.value,
            patch: { miestas: null },
        });
    }

    if (props.filters.reitingas) {
        chips.push({
            key: 'rating',
            label: `${formatRating(props.filters.reitingas)} ★ ir daugiau`,
            patch: { reitingas: null },
        });
    }

    if (props.filters.patikrinti) {
        chips.push({
            key: 'verified',
            label: 'Tik patikrinti',
            patch: { patikrinti: false },
        });
    }

    return chips;
});

function change(patch: Partial<CatalogFilters>): void {
    emit('change', { ...props.filters, ...patch });
}

function clearAll(): void {
    change({ miestas: null, reitingas: null, patikrinti: false });
}

const totalText = computed(() =>
    plural(props.providers.meta.total, ['teikėjas', 'teikėjai', 'teikėjų']),
);
</script>

<template>
    <div
        class="grid gap-8 lg:grid-cols-[17rem_minmax(0,1fr)] xl:grid-cols-[18rem_minmax(0,1fr)]"
    >
        <aside class="hidden lg:block" aria-label="Filtrai">
            <div class="sticky top-24 space-y-5">
                <div class="rounded-2xl border bg-card p-5 shadow-soft">
                    <div class="mb-5 flex items-center justify-between">
                        <p class="flex items-center gap-2 font-semibold">
                            <SlidersHorizontal
                                class="size-4 text-primary"
                                aria-hidden="true"
                            />
                            Filtrai
                        </p>
                        <button
                            v-if="activeFilters.length"
                            type="button"
                            class="text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                            @click="clearAll"
                        >
                            Išvalyti
                        </button>
                    </div>
                    <ProviderFilters
                        layout="stack"
                        id-prefix="side"
                        :filters="filters"
                        :cities="cities"
                        :sort-options="sortOptions"
                        @change="emit('change', $event)"
                    />
                </div>

                <div
                    class="relative overflow-hidden rounded-2xl bg-brand-deep p-5 text-brand-deep-foreground"
                >
                    <div
                        class="pointer-events-none absolute inset-0 pattern-dots text-white/[0.05]"
                        aria-hidden="true"
                    />
                    <p
                        class="relative font-display text-lg font-semibold text-white"
                    >
                        Nenorite ieškoti patys?
                    </p>
                    <p class="relative mt-1 text-sm text-brand-deep-muted">
                        Aprašykite darbą – tinkami teikėjai patys atsiųs
                        pasiūlymus. Nemokamai.
                    </p>
                    <Button variant="cta" class="relative mt-4 w-full" as-child>
                        <a :href="createRequestUrl">Sukurti užklausą</a>
                    </Button>
                </div>

                <slot name="sidebar" />
            </div>
        </aside>

        <section class="min-w-0" aria-labelledby="results-title">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 id="results-title" class="text-2xl font-semibold">
                        {{ title }}
                    </h2>
                    <p
                        class="mt-1 text-sm text-muted-foreground"
                        aria-live="polite"
                    >
                        Rasta {{ totalText }}
                    </p>
                </div>

                <Sheet v-model:open="sheetOpen">
                    <SheetTrigger as-child>
                        <Button variant="outline" class="lg:hidden">
                            <SlidersHorizontal aria-hidden="true" />
                            Filtrai
                            <span
                                v-if="activeFilters.length"
                                class="flex size-5 items-center justify-center rounded-full bg-primary text-xs text-primary-foreground"
                                >{{ activeFilters.length }}</span
                            >
                        </Button>
                    </SheetTrigger>
                    <SheetContent
                        side="right"
                        class="w-[90%] gap-0 overflow-y-auto p-0 sm:max-w-sm"
                    >
                        <SheetHeader class="border-b px-5 py-4 text-left">
                            <SheetTitle>Filtrai</SheetTitle>
                            <SheetDescription
                                >Pasirinkite miestą, įvertinimą ir
                                rikiavimą</SheetDescription
                            >
                        </SheetHeader>
                        <div class="px-5 py-5">
                            <ProviderFilters
                                layout="stack"
                                id-prefix="sheet"
                                :filters="filters"
                                :cities="cities"
                                :sort-options="sortOptions"
                                @change="emit('change', $event)"
                            />
                        </div>
                        <SheetFooter class="mt-auto border-t px-5 py-4">
                            <Button
                                class="w-full"
                                size="lg"
                                @click="sheetOpen = false"
                            >
                                Rodyti: {{ totalText }}
                            </Button>
                        </SheetFooter>
                    </SheetContent>
                </Sheet>
            </div>

            <ul
                v-if="activeFilters.length"
                class="mt-4 flex flex-wrap gap-2"
                aria-label="Pasirinkti filtrai"
            >
                <li v-for="chip in activeFilters" :key="chip.key">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-full border border-primary/25 bg-secondary px-3 py-1 text-sm text-secondary-foreground transition-colors hover:border-primary/50"
                        :aria-label="`Nuimti filtrą: ${chip.label}`"
                        @click="change(chip.patch)"
                    >
                        {{ chip.label }}
                        <X class="size-3.5" aria-hidden="true" />
                    </button>
                </li>
            </ul>

            <slot name="before-list" />

            <ul v-if="providers.data.length" class="mt-5 grid gap-3">
                <li v-for="provider in providers.data" :key="provider.id">
                    <ProviderCard :provider="provider" />
                </li>
            </ul>
            <div
                v-else
                class="mt-5 flex flex-col items-center gap-3 rounded-2xl border border-dashed bg-card/60 px-4 py-14 text-center"
            >
                <span
                    class="flex size-14 items-center justify-center rounded-2xl bg-secondary text-primary"
                >
                    <SearchX class="size-7" aria-hidden="true" />
                </span>
                <p class="font-display text-lg font-semibold">
                    {{ emptyTitle }}
                </p>
                <p class="max-w-md text-sm text-muted-foreground">
                    {{ emptyText }}
                </p>
                <div class="mt-2 flex flex-wrap justify-center gap-2">
                    <Button variant="cta" as-child>
                        <a :href="createRequestUrl">Sukurti užklausą</a>
                    </Button>
                    <Button
                        v-if="activeFilters.length"
                        variant="outline"
                        @click="clearAll"
                    >
                        Išvalyti filtrus
                    </Button>
                </div>
            </div>

            <CatalogPagination class="mt-8" :paginated="providers" />
        </section>
    </div>
</template>
