<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { SearchX } from '@lucide/vue';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import CatalogPagination from '@/components/catalog/CatalogPagination.vue';
import ProviderCard from '@/components/catalog/ProviderCard.vue';
import ProviderFilters from '@/components/catalog/ProviderFilters.vue';
import SeoHead from '@/components/catalog/SeoHead.vue';
import { filtersToQuery, visitWithFilters } from '@/lib/catalog';
import { plural } from '@/lib/format';
import { home } from '@/routes';
import { index as categoriesIndex } from '@/routes/categories';
import { index } from '@/routes/providers';
import type {
    CatalogFilters,
    CityOption,
    Paginated,
    ProviderCard as ProviderCardType,
    SeoMeta,
    SortOption,
} from '@/types';

// Visi aktyvūs teikėjai su filtrais: /meistrai?miestas=kaunas&patikrinti=1
const props = defineProps<{
    providers: Paginated<ProviderCardType>;
    filters: CatalogFilters;
    sortOptions: SortOption[];
    cities: CityOption[];
    seo: SeoMeta;
}>();

const selectedCity = computed(() =>
    props.cities.find((city) => city.slug === props.filters.miestas),
);

function applyFilters(next: CatalogFilters): void {
    visitWithFilters(
        index.url({
            query: { ...filtersToQuery(next), miestas: next.miestas },
        }),
    );
}
</script>

<template>
    <SeoHead :seo="seo" />

    <div class="mx-auto max-w-6xl px-4 py-8 md:py-12">
        <Breadcrumbs
            :breadcrumbs="[
                { title: 'Pradžia', href: home() },
                { title: 'Meistrai', href: index() },
            ]"
        />

        <h1 class="mt-4 text-2xl font-semibold tracking-tight md:text-3xl">
            {{
                selectedCity
                    ? `Meistrai ir paslaugų teikėjai ${selectedCity.name_locative}`
                    : 'Meistrai ir paslaugų teikėjai'
            }}
        </h1>
        <p class="mt-2 max-w-2xl text-muted-foreground">
            Ieškote konkrečios paslaugos?
            <Link :href="categoriesIndex()" class="text-primary hover:underline"
                >Išsirinkite kategoriją</Link
            >
            – pamatysite tik ją teikiančius meistrus ir jų kainas.
        </p>

        <div class="mt-6 flex justify-end">
            <p class="text-sm text-muted-foreground">
                Rasta
                {{
                    plural(providers.meta.total, [
                        'teikėjas',
                        'teikėjai',
                        'teikėjų',
                    ])
                }}
            </p>
        </div>

        <ProviderFilters
            class="mt-2"
            :filters="filters"
            :cities="cities"
            :sort-options="sortOptions"
            @change="applyFilters"
        />

        <div v-if="providers.data.length" class="mt-4 grid gap-3">
            <ProviderCard
                v-for="provider in providers.data"
                :key="provider.id"
                :provider="provider"
            />
        </div>
        <div
            v-else
            class="mt-4 flex flex-col items-center gap-3 rounded-lg border border-dashed px-4 py-12 text-center"
        >
            <SearchX class="size-8 text-muted-foreground" aria-hidden="true" />
            <p class="font-medium">
                Pagal pasirinktus filtrus teikėjų neradome
            </p>
            <p class="text-sm text-muted-foreground">
                Pabandykite pasirinkti kitą miestą arba išjungti dalį filtrų.
            </p>
        </div>

        <CatalogPagination class="mt-6" :paginated="providers" />
    </div>
</template>
